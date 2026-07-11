<?php
declare(strict_types=1);

namespace App\Services\Ai;

/**
 * 運用通知(自動生成の結果・要対応の連絡)。
 * NOTIFY_EMAIL が設定されていれば PHP mail() で送信(mixhost等の共用サーバーで動作)。
 * 未設定・送信失敗でも storage/logs/notify.log には必ず残す。
 */
final class Notifier
{
    public function send(string $subject, string $body): void
    {
        $line = '[' . date('Y-m-d H:i:s') . "] {$subject}\n{$body}\n----\n";
        @file_put_contents(BASE_PATH . '/storage/logs/notify.log', $line, FILE_APPEND | LOCK_EX);

        $to = (string) config('ai.notify_email');
        if ($to === '') {
            return;
        }
        $siteName = (string) config('app.name', 'YADO GRAPH');
        $headers = "From: noreply@" . (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost') . "\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";
        @mb_send_mail($to, "[{$siteName}] {$subject}", $body, $headers);
    }
}
