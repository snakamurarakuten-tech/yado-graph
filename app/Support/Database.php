<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * SQLite への薄いPDOラッパ(セクション2: 自前DB)。
 * Laravel移行時は DB ファサード / Eloquent に置き換えられる。
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $path = (string) config('db.path');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // 読み取り主体・同時書き込み耐性のためのPRAGMA
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        self::$pdo = $pdo;
        return $pdo;
    }

    /** テスト用にコネクションを差し替える。 */
    public static function swap(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
