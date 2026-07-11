# YADO GRAPH — 旅館カタログサービス

「旅館を眺めるだけでも楽しい旅行メディア」。楽天トラベルAPIの実データのみで成立する、
写真主役・ダークモード基調のカタログ型サービスです。予約サイトではなく、
Netflixの作品詳細のように「泊まりたい」と思わせる体験を目指しています。

- WordPress不使用 / **PHP 8.0+ + 素のHTML・CSS・JS**
- Laravel移行を見据えたレイヤ分割(Controller / Service / View)とルーティングテーブル
- テンプレートエンジン非依存(生PHPの `<?= ?>` のみ、ロジックはController/Serviceへ寄せる)

## 動かし方

1. 楽天ウェブサービスでアプリIDを取得: https://webservice.rakuten.co.jp/
2. `.env` を用意してAPIキーを設定
   ```
   cp .env.example .env
   # RAKUTEN_APP_ID=... を記入(任意で RAKUTEN_AFFILIATE_ID=...)
   ```
3. ビルトインサーバーで起動(ドキュメントルートは public/)
   ```
   php -S localhost:8000 -t public
   ```
4. ブラウザで開く
   - トップ: http://localhost:8000/
   - 詳細:   http://localhost:8000/hotel/{hotelNo}   例) /hotel/143637
   - お気に入り: http://localhost:8000/favorites
   - 閲覧履歴:   http://localhost:8000/history

> XAMPP環境の場合は `php` の代わりに `C:\xampp\php\php.exe -S localhost:8000 -t public`
> のようにフルパスで実行してください。

> APIキー未設定でも画面は壊れません(APIは空を返し、レール等は自動的に非表示になります)。

## 設計のポイント(提案書との対応)

- **Instagram「最新の様子」**: 廃止。将来SNS連携を追加する場合の挿入位置コメントを
  `resources/views/detail/index.php` のフォトギャラリー直前に残しています。
- **フォトギャラリー**: `hotelImageUrl`(施設画像)ベース。Instagram枠をここに統合。
- **クチコミ**: 評価サマリー(平均・件数)＋代表クチコミ1件。本文が取れない場合は
  楽天トラベルへの誘導文にフォールバック。
- **客室**: 部屋タイプ(構造化データなし)→ `roomImageUrl` の横スクロール
  「客室フォトギャラリー」に変更。キャプションなし。
- **館内設備**: 実在するフィールドのみでリスト化(空の項目は自動非表示)。
- **合宿免許 / レンタカー**: 旅館固有の判定はせず、全旅館共通の静的ボタン。
  リンク先は `.env` の `GASSHUKU_AFFILIATE_URL` / `RENTACAR_AFFILIATE_URL` で差し替え。
- **liveViewingCount(本日◯組が確認中)**: 初期版は非表示。CSS(`.live-note`/`.live-dot`)は
  温存してあり、将来 自社トラッキング実装時にPHPから値を渡すだけで復活できます。
- **空室確認CTA**: アフィリエイトリンク(`affiliateUrl`)のみ。`VacantHotelSearch` による
  リアルタイム空室・価格表示は第2フェーズ。
- **キャッシュ**: 一律TTL(`.env` の `CACHE_TTL_MINUTES`、既定60分)のシンプルな
  ファイルキャッシュ。`0` で無効化。楽天APIの短時間連続アクセス制限の緩衝材。

## ディレクトリ

```
public/          Webサーバー公開ルート
  index.php      フロントコントローラー
  .htaccess      全リクエストをindex.phpへ
  assets/        css(base/components/pages) / js(core/components/pages) / img
app/
  Http/Controllers/   Top / HotelDetail / Favorite / History
  Services/
    Rakuten/          RakutenApiClient / HotelDetail / HotelRanking / KeywordSearch / HotelCardMapper
    RecommendationService / SeoService / CacheService
  Support/            Router / View / Env / Config / helpers
  Config/             config.php / routes.php
resources/views/      layouts / components / top / detail / favorites / history / errors
storage/cache/        APIレスポンスのファイルキャッシュ(gitignore対象)
```

## Laravel移行の対応

| 現構成 | 移行後 |
|---|---|
| `app/Http/Controllers/*` | そのまま |
| `app/Services/*` | そのまま(Service Container登録を追加) |
| `resources/views/*.php` | `.blade.php` へ(構文差分のみ) |
| `app/Config/routes.php` | `routes/web.php` |
| `app/Services/CacheService` | `Cache` ファサード |
| `public/index.php` | Laravel標準へ置換 |
| `.env` | そのまま流用可 |

## 公開前のチェック

- `resources/views/detail/index.php` 等に開発用メモは残していません(dev-noteはモックのみ)。
- 楽天トラベルAPIの利用規約・アフィリエイト表記に沿ってCTA周りの文言をご確認ください。

## 今回の修正・機能追加(依頼対応)

### A. 不具合修正
- **A-1 CTAリンク修正**: `AffiliateLinkBuilder` を新設。CTAの遷移先から画像API
  (`img.travel.rakuten.co.jp/...`)を確実に排除し、`RAKUTEN_AFFILIATE_ID` 設定時は
  楽天公式仕様の `hb.afl.rakuten.co.jp/hgc/{ID}/?pc=...&m=...` 形式へラップ。
  APIが既にアフィリエイトURLを返す場合はそれを優先。CTA文言は
  「楽天トラベルで空室・料金を見る」に統一(詳細本文・Hero・sticky共通)。
  ※ 当サイトでは料金を掲載しない方針のため、「空室・料金は楽天トラベルで確認する」
  行動を促す文言とした(遷移先で実際に料金を確認できるため虚偽にはならない)。
- **A-2 下部固定メニュー**: 既存の3タブ(ホーム/お気に入り/履歴)を踏襲。お気に入り件数
  バッジも維持。

### B. コンテンツ強化
- **B-4 Instagram埋め込み**: `InstagramService` を新設。施設名からハッシュタグを導出し、
  Graph APIで直近投稿を取得→「いいね閾値」「直近◯ヶ月」でフィルタ→自動スクロールの
  カルーセル表示。トークン未設定時は枠ごと自動非表示。
- **B-5 バッジ自動生成**: 条件テーブル `app/Config/badges.php`(設定ファイル駆動)＋
  `BadgeService`。評価・クチコミ数・料金帯・設備タグの組み合わせで自動付与。
  タグ推定は `HotelTagger`＋`app/Config/taxonomy.php` のキーワード辞書。
- **B-6 感情訴求型の棚**: `ShelfService`。`taxonomy.php` の shelves 定義から毎回
  ランダムに数本を選び、並び順もシャッフル(「毎回同じ並びにならない」)。

### C. 回遊・再訪
- **C-7 履歴パーソナライズ**: 各棚に `data-tag` を付与し、履歴・お気に入り・オンボーディング
  選択のタグ嗜好(`core/prefs.js`)でトップの棚を並べ替え(`rail-personalize.js`)。
  「あなた好みの宿」レールも `/api/recommend` から動的描画。
- **C-8 お気に入り類似レコメンド**: お気に入りのタグ傾向から未閲覧の類似宿を
  `/api/recommend` 経由で「好みが近い宿」として表示。
- **C-9 今日の1軒**: `/surprise` ルート。条件を問わずランダム1軒へ302リダイレクト。
  トップHeroにシャッフルボタンを追加。

### D. SEO・信頼性
- **D-10 meta description テンプレート化**: `SeoService::buildDescription()`。楽天の説明文を
  流用せず「{都道府県}にある評価{評価点}の宿「{宿名}」。{設備タグ}が魅力。」で一意生成。
- **D-11 構造化データ**: `Hotel` + `AggregateRating` に加え `geo` / `priceRange` / `url` を付与。
- **D-12 更新日時**: カード・詳細CTAに「最終更新：YYYY年M月」(楽天API取得日)を表示。

### 初回オンボーディング(Netflix風)
- 初回訪問時のみ、目的軸×こだわり軸のカードから好みを複数選択させるモーダル
  (`components/onboarding.php` / `onboarding.js`)。最低2つ以上で開始。選択結果は
  `prefs`(localStorage)に保存し、上記C-7/C-8のパーソナライズ初期値になる。
  選択肢は `taxonomy.php` の onboarding 定義で管理。

> 設定ファイルの要点: タグ・棚・オンボーディングの語彙は `app/Config/taxonomy.php`、
> バッジ条件は `app/Config/badges.php` に集約。コード変更なしで追加・調整できます。

## 改善仕様書(P0〜P3)対応

> 方針の整合: 元の「価格・空室は非表示(送客Cookie目的)」と、本仕様の「カードから直接楽天へ」
> 「価格帯フィルター」を両立させるため、**カード上に価格の数値は出さず**、直接楽天トラベルへ飛ぶ
> CTA(＝Cookie付与)を追加し、**価格帯フィルターは返却データの最低料金での絞り込みのみ**(数値非表示)
> としています。

### P0 送客導線
- **P0-1 カード直接CTA**: 各カードのサムネ上に「楽天トラベルで見る」ボタン(ホバーで浮き出す/タップ可)を追加し、
  アフィリエイトURLへ直接遷移(1タップ送客)。詳細ページはファーストビュー内にも予約CTAを追加。CTA文言は
  「楽天トラベルで空室・料金を見る」に統一。ネストアンカー回避のため card-link(全面)とcard-cta(前面)を分離。
- **P0-2 CTR計測**: `data-track` 属性＋`core/track.js`。クリックを `/api/track`(日別JSONLの簡易ログ、
  `TrackController`)へ送信＋GA4(測定ID設定時は `gtag` イベント)。カテゴリ別・宿別に後から集計可能。

### P1 カテゴリ網羅性・フィルタ
- **P1-3 カテゴリ自動増殖**: `app/Config/categories.php` の固定ラベル辞書＋楽天検索条件でカテゴリを生成
  (一人旅/絶景/温泉/ペット可/送迎あり 等)。1行足すだけで増える。
- **P1-4 一覧・もっと見る**: `/categories`(全カテゴリ) と `/category/{key}`(単体・ページング/無限スクロール)。
  各カルーセル右上の「一覧はこちら」から遷移。追加読み込みは `/api/category/{key}`。
- **P1-5 価格帯フィルター**: カテゴリページに価格帯チップ(お手頃〜特別)。`minCharge` で絞り込み(数値非表示)。
  エリアは読み込み済みカードから都道府県チップを生成しクライアント絞り込み。

### P2 データ品質
- **P2-6 宿名重複の解消**: 画像 `alt` を装飾扱い(空)にし、宿名はテキスト側で1回だけ出力。
- **P2-7 評価欠損の統一**: レビュー0件は「レビューなし」を機械的に表示(既定)。`EXCLUDE_NO_REVIEW=true` で一覧から除外に切替可。
- **P2-8 今日の一軒を決定的に**: `YYYY-MM-DD` をシード(crc32)にして人気プールから1軒選出。同日中は不変。
  ランダム提案は別ボタン「気まぐれで選ぶ」(/surprise)に分離。

### P3 規約・運用
- **P3-9 楽天規約**: フッターに「Supported by 楽天ウェブサービス」、画像に楽天トラベルのクレジット表記。
  キャッシュTTLは `RAKUTEN_CACHE_TTL`(既定60分)で鮮度を担保。
- **P3-10 ナビ整備**: 共通フッター(`site-footer`)から全カテゴリ一覧・お気に入り・履歴へ導線。

### 追加UI
- **各カルーセルに「一覧はこちら」**: トップ・詳細の全レール右上にリンク(→カテゴリページ)。
- **トップ上部のスクロール連動タブ**: セクション見出しをタブ化し、タップで対象レールへスムーズスクロール＋
  スクロール位置に応じて現在タブをハイライト(`top-tabs.js`)。

> カテゴリ辞書・価格帯は `app/Config/categories.php`、計測ログは `storage/logs/` に出力されます。

## 追加のフィードバック対応(2巡目)

- **金額の非表示**: JSON-LDの `priceRange` とカテゴリの価格帯フィルターUIを撤去(価格は一切出さない方針を徹底)。
- **トップのカードCTA撤去**: トップの各カードから「楽天トラベルで見る」ボタンを非表示(`showCta=false`)。カテゴリ一覧ページでは維持。
- **上部タブ**: ヒーローより上・最上部に固定(sticky top:0・不透明背景)。タップで対象レールへスムーズスクロール。並び順は実レール順に同期(パーソナライズ後のズレを解消)。
- **詳細ページの文言重複解消**: `catchCopy` と `hotelSpecial` が同一楽天フィールドだったため、「この宿について」はヒーローのキャッチと異なる場合のみ表示。
- **詳細ページの画像最大化**: レスポンス全体(roomInfo含む)を再帰走査し、施設/客室画像を可能な限り収集(地図除外・サイズ違い重複排除)。
- **下部メニューの固定**: フレームをflexカラム化し、`bottom-stack` を在フロー最下段に。全ページ・全ビューポートで確実にピン留め。
- **フッターの重複解消**: 下部固定メニューと重複する「カテゴリ一覧/お気に入り/履歴」リンクをフッターから削除(楽天クレジットのみ残置)。
- **エリアタブの視認性**: 選択中チップの背景を透明にしていた指定を撤去し、アクティブ時も文字が読めるよう修正。
- **サムネのバッジ視認性**: 暗いスクリム＋白文字＋影＋トーンの左アクセントで、明るい画像上でも必ず読めるよう改善。
- **ヒーロー消失の防止**: 「今日の一軒」をランキングが空でもカテゴリ側の宿から必ず選出。

## 改善指示書対応(3巡目: DB化・自動生成・周辺情報・地図・SEO)

### セクション2 — データ取得の設計変更(DB化)
- 楽天APIを毎回叩かず、**夜間バッチで自前DB(SQLite)へ取り込み → サイト表示はDBのみ参照**に変更。
  DBが空の間は自動でAPI直叩きにフォールバックするため、段階移行できる。
- バッチ: `php bin/fetch-hotels.php --all`(列挙→詳細化)。全API呼び出し間に `sleep(1.1秒)`(楽天の1秒1リクエスト制限順守)。日次〜週次でcron実行を想定。
  - `--enumerate`: カテゴリ横断で宿を列挙し軽量保存
  - `--detail [--limit=N]`: 各宿を `HotelDetailSearch` で詳細化(6軸評価・設備・全画像・周辺)
- 保存フィールド: `hotelSpecial` / `aboutLeisure` / `bathType`・`bathQuality`・`bathBenefits` / `roomFacilities`・`hotelFacilities` / `reviewAverage`・`reviewCount`＋**軸別評価6種** / 緯度経度 / 施設・客室画像(通常＋サムネイル)。
- **DB化により「全宿を横断した絞り込み・並び替え」が可能に**(都道府県・レビュー有無・並び順)。従来のキーワード1ページ内制約が外れる。

### セクション1 — 不具合修正
- 詳細ページの画像を `HotelDetailSearch` レスポンス全体(roomInfo含む)から通常＋サムネイルまで漏れなく収集(地図除外・サイズ違い重複排除)。
- クチコミ欄の「本文は楽天〜」を、軸別評価からの**自動生成コメント**に置換。

### セクション3 — コンテンツ自動生成(LLM非依存)
- **3-1 レーダーチャート**: 6軸評価をChart.jsのradarで可視化(Netflixトーン)。
- **3-2 テンプレ文生成**: 突出軸(平均+0.3以上)を抽出→軸→語彙変換→文型テンプレ(1軸/2軸/弱軸言及/締め 計18種)を`hotelNo`シードで決定的に合成。AI頻出語の**禁止語フィルタ**で当たれば再構成。設定は `app/Config/review_templates.php`。

### セクション4 — 周辺情報
- `aboutLeisure` があれば優先。無ければ **Overpass API(OSM)** で半径2.5km内の `tourism`/`leisure` を取得し、Haversine距離でソート。スポット3件未満のエリアはセクションごと非表示。「© OpenStreetMap contributors」表記。緯度経度グリッド単位でキャッシュ。

### 地図の修正
- 表示が不安定だったGoogle Maps埋め込みを、**Leaflet + OpenStreetMapタイル**に置換(APIキー不要・帰属表示付き)。

### セクション6 — SEO・信頼性
- 詳細ページに `Hotel` + `AggregateRating` + `BreadcrumbList` のJSON-LD、パンくず表示。
- 運営者情報(`/about`)・プライバシーポリシー(`/privacy`)ページ。
- フッター等に「本サイトはアフィリエイト広告を利用しています」を明記(ステマ規制対応)。
