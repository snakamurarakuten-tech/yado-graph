<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Viewヘルパー。
 * render() はテンプレートを共通レイアウト(layouts/app)に流し込んで出力する。
 * Laravel移行時: テンプレートを .blade.php 化し、このクラスの呼び出しを view() に置換。
 */
final class View
{
    private const VIEW_ROOT = BASE_PATH . '/resources/views';

    /** レイアウト込みでページを描画 */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $content = self::fetch($template, $data);

        if ($layout === null) {
            echo $content;
            return;
        }
        // レイアウト側では $content と、ページ側の $data(seo, pageCss, pageJs等)を参照できる
        echo self::fetch($layout, $data + ['content' => $content]);
    }

    /** components/ 配下の再利用パーツを描画 */
    public static function component(string $name, array $props = []): void
    {
        echo self::fetch('components/' . $name, $props);
    }

    private static function fetch(string $template, array $data): string
    {
        $path = self::VIEW_ROOT . '/' . $template . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}
