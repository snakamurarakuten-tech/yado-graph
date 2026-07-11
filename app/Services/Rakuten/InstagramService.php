<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

/**
 * Instagram連携(機能追加メモ1)。
 *
 * 方式: ハッシュタグ検索画面へのディープリンク。
 *   https://www.instagram.com/explore/tags/{正規化した宿名}/
 *
 * 旧実装(Graph APIで投稿を取得して自サイト内に表示)は廃止。
 * 理由: ハッシュタグ検索結果の自動取得・埋め込みは技術的(X-Frame-Options)にも
 * 規約的(自動収集の禁止)にも不可能で、oEmbedは投稿URLの人力収集が前提のため
 * 4万件規模では非現実的。詳細は docs/機能追加メモ 参照。
 *
 * 割り切り: ハッシュタグに投稿が0件の宿もある(実在確認はしない)。
 * 未ログインユーザーはInstagram側でログインを求められる場合がある。
 */
final class InstagramService
{
    /**
     * 宿のハッシュタグ検索URLを返す。組み立てられない場合は null(ボタン非表示)。
     *
     * @param array<string,mixed> $hotel 正規化済み $hotel
     */
    public function hashtagUrl(array $hotel): ?string
    {
        $tag = self::normalizeTag((string) ($hotel['hotelName'] ?? ''));
        if ($tag === '' || mb_strlen($tag) < 2) {
            return null;
        }
        return 'https://www.instagram.com/explore/tags/' . rawurlencode($tag) . '/';
    }

    /**
     * 宿名 → ハッシュタグ文字列の正規化(メモ1 TODO対応)。
     *
     * ルール:
     *  1. NFKC正規化(全角英数→半角、㈱等の合成文字を展開)
     *  2. 括弧内の注記を除去 例: 「加賀屋(和倉温泉)」→「加賀屋」
     *  3. ハッシュタグで使えない文字(空白・記号)を除去
     *     ※「◯◯温泉」等の接頭辞は残す: 短くすると同名別施設のノイズが増えるため、
     *       フルネーム側に倒して「見つかった投稿=その宿」の信頼性を優先する
     */
    public static function normalizeTag(string $name): string
    {
        if ($name === '') {
            return '';
        }
        // 1) NFKC(intl拡張が無い環境ではスキップして続行)
        if (class_exists(\Normalizer::class)) {
            $name = \Normalizer::normalize($name, \Normalizer::FORM_KC) ?: $name;
        }
        // 2) 括弧と中身を除去(全角・半角)
        $name = (string) preg_replace('/[(\(【\[][^)\)】\]]*[)\)】\]]/u', '', $name);
        // 3) ハッシュタグに使える文字だけ残す(日本語・英数字)。
        //    空白・中点・記号・絵文字などはすべて除去。
        $name = (string) preg_replace('/[^0-9A-Za-z\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}\x{3005}\x{30FC}]/u', '', $name);
        return trim($name);
    }
}
