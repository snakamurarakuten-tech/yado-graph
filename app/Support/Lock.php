<?php
declare(strict_types=1);

namespace App\Support;

/**
 * バッチの多重起動防止(flockベース)。
 * cronの実行が前回分と重なると、API二重消費・DB競合・state破損の原因になる。
 * 使い方:
 *   $lock = Lock::acquire('fetch-hotels');
 *   if ($lock === null) { echo "既に実行中"; exit; }
 *   // ... 処理 ...(スクリプト終了時にプロセス終了で自動解放)
 */
final class Lock
{
    /** @var resource */
    private $handle;

    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    /** 取得できなければ null(=別プロセスが実行中)。 */
    public static function acquire(string $name): ?self
    {
        $dir = BASE_PATH . '/storage/locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $name) . '.lock';
        $h = fopen($file, 'c');
        if ($h === false) {
            return null;
        }
        if (!flock($h, LOCK_EX | LOCK_NB)) {
            fclose($h);
            return null;
        }
        // PID を書いておく(調査用)
        ftruncate($h, 0);
        fwrite($h, (string) getmypid());
        fflush($h);
        return new self($h);
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }
}
