<?php
declare(strict_types=1);

namespace App\Support;

/**
 * .env の簡易ローダー。
 * Laravel移行時は vlucas/phpdotenv (Laravel同梱) に置き換わるが、
 * .env ファイル自体はそのまま流用できる。
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return match (strtolower($value)) {
            'true', '1', 'on', 'yes'  => true,
            'false', '0', 'off', 'no' => false,
            default => $value,
        };
    }
}
