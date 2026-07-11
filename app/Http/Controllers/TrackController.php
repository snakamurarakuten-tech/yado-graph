<?php
declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * クリック計測(依頼P0-2)。
 * カード/CTAクリックを JSONL でファイルに追記する(DB不要の簡易ログ)。
 * カテゴリ別・宿別の集計は後からログを読めば可能。
 * GA4を使う場合はフロント側(track.js)で gtag も併発火する。
 *
 * POST /api/track  body: {event, category, hotelNo}
 * 応答は 204(No Content)。計測失敗でも画面挙動は止めない。
 */
final class TrackController
{
    /** リクエストボディの上限(バイト)。超過は 413 で弾く(改修7-1) */
    private const MAX_BODY_BYTES = 4096;

    /** 1日のログファイル上限(バイト)。超えたら書き込み停止(ディスク充填DoS対策) */
    private const MAX_LOG_BYTES = 10 * 1024 * 1024;

    public function hit(): void
    {
        // 改修7-1: 巨大ボディを弾く(Content-Length申告と実測の両方でガード)
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > self::MAX_BODY_BYTES) {
            http_response_code(413);
            return;
        }

        // sendBeaconはtext/plainで飛んでくるため生ボディをJSONとして読む
        $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            http_response_code(413);
            return;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        $enabled = (bool) config('tracking.enabled', true);
        if ($enabled) {
            $this->append([
                'ts'       => date('c'),
                'event'    => $this->clean((string) ($data['event'] ?? '')),
                'category' => $this->clean((string) ($data['category'] ?? '')),
                'hotelNo'  => $this->clean((string) ($data['hotelNo'] ?? '')),
                'ref'      => $this->clean((string) ($_SERVER['HTTP_REFERER'] ?? '')),
                'ua'       => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 180),
            ]);
        }

        http_response_code(204);
    }

    /** 日別JSONLに1行追記。 */
    private function append(array $row): void
    {
        $dir = (string) config('tracking.log_path');
        if ($dir === '') {
            return;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/clicks-' . date('Y-m-d') . '.jsonl';
        // 改修7-1: 当日のログが上限を超えたら書き込み停止(ディスク充填対策)
        if (is_file($file) && (int) filesize($file) > self::MAX_LOG_BYTES) {
            return;
        }
        @file_put_contents(
            $file,
            json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private function clean(string $v): string
    {
        $v = preg_replace('/[\r\n\t]+/', ' ', $v) ?? $v;
        return mb_substr(trim($v), 0, 120);
    }
}
