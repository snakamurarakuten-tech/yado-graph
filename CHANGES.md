# 改修内容まとめ(2026-07-10)

改修仕様書(yado-graph-改修仕様書.md)の Phase 0〜3 / 6 / 7 を実装。
検索機能(Phase 4-1)とフィルタUI(4-2)は次フェーズ。

## Phase 0 — セキュリティ・衛生
- `public/rakuten-test.php` 削除(APIキー部分露出・API叩かれ放題の診断ページ)
- JSON-LD 出力の XSS 修正: `layouts/app.php` と `detail/index.php` の json_encode に
  `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` を追加
- `storage/` 配下に混入していたプロジェクト複製一式を削除(cache/db/logs のみに)
- `.gitignore` 整備(sqlite/ログ/キャッシュを除外)、`.dockerignore` 新設

## Phase 1 — DB完全移行(リクエスト時のAPI直叩き解消)
- `HotelRepository`: コンストラクタで migrate を自動実行(冪等)。write-through が
  テーブル未作成で落ちない
- `HotelDetailService::find()`: APIフォールバックで引いた宿をその場でDB保存
  (write-through)。次回以降はDBヒット
- 鮮度14日超のDBヒットは `storage/db/refresh-queue.txt` に積み、夜間バッチが優先更新
  (stale-while-revalidate。リクエスト内では再取得しない)
- `bin/fetch-hotels.php`: refresh-queue 優先処理 / 周辺情報(Overpass)を詳細化時に
  取得してDBに同梱(リクエスト時のOverpass呼び出しを解消) / 24h超のAPIキャッシュGC
- `HotelDetailController`: 周辺情報はDB同梱値を優先、無い宿のみ従来のオンデマンド

## Phase 2 — レコメンドの重複解消
- `app/Services/RailBuilder.php` 新設: ページ内の全レールを横断して hotelNo を重複除去。
  重複除去後3件未満のレールは自動非表示
- `RecommendationService::forDetail()`: 「近くの宿(半径10km)」→「同じ県」→
  「{タグ}が評判の宿」の優先順に再構成。タグレールのタイトルを具体化
- `TopController`: 「人気の宿」→カテゴリレールを RailBuilder 経由に
- サーバー描画済みの hotelNo を `window.__seenHotelNos` で共有し、
  JSレール(/api/recommend)の exclude にマージ
- 自動生成コメントの二重表示(評価の傾向とクチコミの両方に出るバグ)を統合で解消

## Phase 3 — 詳細ページの再構成とコンテンツ増強
- セクションを16→12に整理(quickbar廃止→基本情報へ吸収、評価+クチコミ統合、
  写真+客室統合、アクセス+周辺統合、末尾の重複お気に入りボタン削除)
- **おすすめポイント**(`HighlightService` 新設): DBの県別統計(`prefStats`)と比較した
  相対評価を最大3点自動生成(県平均比・クチコミ数上位10%・価格帯・軸評価の突出)
- **温泉・お風呂セクション**: bathType / bathQuality を表示に昇格。
  泉質辞書(`app/Config/onsen_glossary.php` 新設・10泉質)の一般解説を併記
- **近くの宿レール**: `HotelRepository::nearby()`(矩形絞り込み+Haversine)
- **よくある質問**(`FaqService` 新設): IN/OUT・駐車場・アクセス・泉質・料金目安から
  自動生成し、`FAQPage` の構造化データを出力
- **タグチップ**: カテゴリページへの内部リンク(`CategoryService::findByTag()` 新設)
- 自動生成コメントの文言をステマ規制対応に修正(口コミの存在を示唆する表現を排除、
  データ出所を注記)

## Phase 6 — SEO
- `SitemapController` 新設: `/sitemap.xml`(全宿+カテゴリ+静的) と `/robots.txt`
- アフィリエイトリンクの rel を `sponsored nofollow noopener` に統一
  (カード・Hero・sticky・詳細CTA・xsell・JS生成カード)
- `AggregateRating` の JSON-LD を削除(セルフサービングレビュー・ポリシー対応)。
  代わりに FAQPage でリッチリザルトを狙う
- title を「{宿名}の魅力・評価・写真まとめ|{県}の旅館」形式に、description に
  相対評価ハイライトを組み込み(サイト独自の一文)
- カード上の「最終更新」を削除(詳細ページのみに)

## デザイン・ブランド
- ロゴを実装(`components/logo.php` = 金の円環+太陽+折れ線+水面のSVGマーク+
  レタースペーシング広めの明朝ワードマーク)。トップHero・詳細topnav・フッターに配置
- `favicon.svg`(ダーク角丸+金マーク)と `theme-color` を追加
- Heroの宿名・キャッチを明朝(Noto Serif JP)に
- 新CSS `components/brand.css`(ロゴ/タグチップ/ハイライト/泉質/FAQ/自動コメント、
  `--gold` トークン、prefers-reduced-motion 対応)
- 全CSS/JSにキャッシュバスティング(`asset()` ヘルパー新設、`?v=filemtime`)
- Hero1枚目に `fetchpriority="high"`(LCP改善)、2枚目以降 lazy

## Phase 7 — その他
- `/api/track`: 4KB超ボディを413で拒否+日別ログ10MB超で書き込み停止(ディスク充填対策)
- `AffiliateLinkBuilder`: hb.afl 判定をホスト名の厳密比較に(URL紛れ込み対策)
- `Env::get()`: '0'/'1'/'on'/'off' も bool 変換
- `errors/500.php` 新設(404文言の流用をやめる)
- `Dockerfile`: `PHP_CLI_SERVER_WORKERS=4`
- `daily()`(今日の一軒): 件数変動で同日中に結果が変わる問題をハッシュ最小値方式で解消

## 運用手順(必須)
1. `.env` を用意(RAKUTEN_APP_ID / RAKUTEN_AFFILIATE_ID / APP_URL)
2. 初回取り込み: `php bin/fetch-hotels.php --all --pages=5 --limit=500`
3. cron 登録(毎日3時): `0 3 * * * cd /path/to/yado-graph && php bin/fetch-hotels.php --all >> storage/logs/batch.log 2>&1`
4. Search Console に `https://<ドメイン>/sitemap.xml` を送信

## Phase 4 — 検索とフィルタ(追加実装)
- **フリーワード検索** `/search` 新設(`SearchController` + `search/index.php` + `search.js`)
  - キーワード(空白区切りAND)・都道府県プルダウン(`HotelRepository::prefList()` 新設)・
    価格帯チップ・並び替え(人気順/評価順/クチコミ数順)
  - `/api/search` で「もっと見る」+ 無限スクロール
  - 検索結果は `noindex,follow`(薄いページの量産防止。レイアウトに robots メタ対応を追加)
  - 未入力時はキーワードヒントのチップとカテゴリへの導線を表示
  - DB未構築時は楽天キーワード検索へフォールバック
- **ボトムナビを4タブ化**(ホーム / さがす / お気に入り / 閲覧履歴)。
  トップの上部タブにも「検索」リンクを追加
- **カテゴリページの絞り込み・並び替えUI**: 価格帯チップとソートチップを追加。
  `CategoryService::fetch()` のDB経路が price を無視していたバグも修正
  (価格帯キー→min/max 変換の `priceBand()` / `normalizeSort()` を新設し検索と共用)。
  無限スクロール(`category-feed.js`)にも sort を引き継ぎ

## Phase 4-3 / 4-4 / 5 残項目(追加実装)
- **スケルトンUI**: JSレール(あなた好み/好みが近い宿/共有リスト)がフェッチ完了まで
  シマー付きプレースホルダを表示。失敗・0件時は枠ごと片付ける。
  `prefers-reduced-motion` ではアニメーション停止
- **お気に入りの共有**: 「このリストを共有する」ボタン(Web Share API、
  非対応環境はクリップボードコピー+トースト)。共有URL `/favorites?share=<hotelNoのCSV>` を
  受け取った側は `/api/hotels`(新設・DBのみ参照で乱用されてもAPIレート消費なし)から
  カードを取得して閲覧できる(相手のリストは上書きしない)。
  `HotelRepository::findMany()` 新設(順序保持・上限30・数字以外は除外)
- **デザイン細部(改修5-3/5-4)**: セクション区切りの罫線を廃止して余白のみで分節、
  ボトムナビのタップ領域を48px以上に拡大

## 起動時自動シード(Render 無料枠対応 → 本番移行対応)
- `docker-entrypoint.sh` 新設 + Dockerfile の CMD を entrypoint 経由に変更
  - 起動時にDB件数を確認し、SEED_MIN(既定20)未満ならバックグラウンドで
    フルシード(`--all --pages=SEED_PAGES --limit=SEED_LIMIT`)。サーバー起動はブロックしない
  - DBが十分でも最古データが REFRESH_DAYS(既定7日)超なら差分更新(`--detail`)を裏で実行
  - RAKUTEN_APP_ID 未設定時はシードをスキップして警告のみ(サイトは起動する)
  - 進捗は `storage/logs/batch.log`
- `bin/db-status.php` 新設(CLI専用: 件数と最古データの経過日数を出力)
- 環境変数で調整可能: SEED_MIN / SEED_PAGES / SEED_LIMIT / REFRESH_DAYS / REFRESH_LIMIT
- 本番移行(案B)時: Persistent Disk を /app/storage にマウントすればDBが永続化され
  シードは自動スキップ。Cron Job に `php bin/fetch-hotels.php --all` を日次登録すれば完成

## 機能追加メモ対応 + 詳細ページ増強(第2弾)
### メモ1: Instagram連携
- Graph API方式(トークン必須で実質死にコード)を廃止し、ハッシュタグ検索への
  ディープリンクボタンに置き換え(`InstagramService::hashtagUrl()`)
- 宿名の正規化: NFKC → 括弧内注記の除去 → 記号・空白除去。「◯◯温泉」等の接頭辞は
  同名別施設のノイズ回避のため残す方針
- ボタンは写真セクション末尾・アウトラインの控えめトーン。`data-track="instagram_link"`
### メモ2: 口コミ
- 代表クチコミの抽出を HotelExtractor に実装(候補キー複数対応・220字で文末丸め)。
  見出しは「お客様の声(抜粋・1件)」で1件のみと明示
- クチコミ0件の宿を3層で除外: バッチ取り込み時にスキップ+パージ /
  `EXCLUDE_NO_REVIEW` 既定true(一覧・検索・レコメンドの保険) /
  詳細ページは表示するが noindex + sitemap から除外(共有リンク経由の閲覧は拒否しない)
### メモ3: ホテルタイプ除外
- `HotelFilterService` + `app/Config/hotel_filters.php` 新設。判定順:
  0件除外 → チェーン系宿名(東横イン等19語) → 異常低額(1,000円未満) →
  旅館らしさ認定(温泉/旅館/露天等) → 中間層は掲載側に倒す
- 「温泉大浴場つきビジネスホテル」対策でチェーン判定を風呂判定より先に
- `hotelClassCode` をDBに保存開始 + `--class-report` バッチオプション新設
  (コード値の分布と宿名例を出力=対応表づくり用)
- `RAKUTEN_SQUEEZE_CONDITION` 環境変数フック(既定OFF・対応値確認後に有効化)
- バッチに `--purge`(掲載条件を満たさない既存行の削除)を追加。--all にも組み込み
### 詳細ページ増強
- **軸別評価×県平均の比較バー**(増強1): 6軸をバー+県平均マーク+差分テキストで表示。
  `prefAxisStats()`(SQLite JSON1で集計)新設。図だけだったレーダーを検索エンジンにも
  読めるテキストに
- **近くの宿との比較テーブル**(増強2): 自宿+近隣3軒の総合/クチコミ/風呂/料金/距離。
  toCard に bathValue を追加
- **「◯◯市での位置づけ」**(増強3): 住所から市区町村を導出(郡+町村対応)して
  city 列に保存。掲載軒数・クチコミ数順位・エリア平均比の文章を自動生成。
  `cityStats()` 新設 — 将来のエリア×テーマページの集計基盤を兼ねる
- **こんな人におすすめチップ**(増強4): 軸評価・タグ・県内価格帯からルール生成
- 効能(bathBenefits)は既存の温泉セクションで表示(増強5)
### その他
- OGP画像フォールバック: 写真の無いページはブランドロゴPNG(1200×630)を出力
- CSS物理結合: `bin/build-css.php` で22ファイル→app.css 1本(Dockerビルドで自動生成、
  無ければ個別読込にフォールバックするため開発体験は不変)
- sitemap からクチコミ0件を除外 / .env.example に新環境変数を追記

## 本番実データQAでの修正(hotel/40786 で発見)
- **お客様の声のHTML混入を修正**: APIの口コミ本文に「つづきはこちら」のHTMLリンクと
  投稿日時が埋め込まれていた → strip_tags + 日時/リンク文言/生URLの除去を追加
- **館内・基本情報の二重表示を修正**: facilities配列に含まれるIN/OUT・駐車場・泉質・
  最寄り駅が basics/温泉/アクセスセクションと重複 → 既出キーを除外して表示
- **「レビューなし」カードの混入を修正**: reviewCount>0 かつ reviewAverage=0 の宿が
  実在(検索・レール・掲載フィルタすべてで評価0も除外するよう強化 + パージ対象化)
- 周辺スポットのカンマ羅列をチップ表示に整形
- 「同じ◯◯県の宿」レールの一覧リンクを /search?pref=県名 へ(従来は全カテゴリ)
- 補足: CTAの `img.travel.rakuten.co.jp/image/tr/api/...?f_no=` は楽天APIが公式に返す
  hotelInformationUrl(宿ページへのリダイレクト)で正常と確認済み

## スマホUX修正(実機フィードバック対応)
- **画面全体の横パン防止**: html/body/scroll-area に overflow-x:hidden を追加、
  比較テーブルの負マージンを廃止(横にはみ出す要素がbodyのスクロールに伝播しない)
- **トップの上部タブが反応しない問題を修正**: クリック時の座標計算を
  getBoundingClientRect ベースに変更+計算不正時は scrollIntoView にフォールバック。
  scrollArea 不在時も document.scrollingElement で動作。トップの初期化処理を
  try/catch で分離し、1つの失敗が他(タブ)を巻き込まないように
- **レーダーチャートを廃止**: 軸別比較バー(県平均つき)と重複するため一本化。
  Chart.js の読み込みも削除(外部JS 1本削減)
- **地図をGoogleマップ埋め込みに置換**: 表示が不安定だった Leaflet/OSM から、
  APIキー不要の Google Maps embed(iframe)+「Googleマップで開く」リンクへ。
  Leaflet の CSS/JS 読み込みも削除。周辺スポット(Overpassデータ)の
  OSM帰属表示は維持

## 地図・タブの根本修正(実機フィードバック第2弾)
- **地図「見つかりませんでした」の根本原因を修正**: 楽天APIの座標は既定で
  「日本測地系・秒単位」(例: 133166.3)で返るのに度として扱っていた。
  1) 全APIリクエストに datumType=1(世界測地系・10進度)を付与
  2) HotelExtractor に正規化を追加(90/180度超の値は 秒→度 + Tokyo Datum→WGS84 簡易変換)
  3) 旧形式でDBに残った座標も詳細ページ読み出し時に正規化
  → 実データ(ハワイアンズ 133166.3,506953.02 → 36.9937,140.8169)で検証済み。
  地図のピン・近隣検索・距離計算・位置づけセクションがすべて正しく動くようになる
- **上部タブの多段フォールバック**: 旧iOS Safariが scrollTo(options) を
  「エラーも出さず無視する」既知の非対応に対応。実際に動いたかを80ms後に計測し、
  動いていなければ scrollTop 直接代入。さらに rail セクションに scroll-margin-top を
  設定し、JSが完全に死んでいてもネイティブアンカーで着地できるように

## UXフィードバック第3弾
- **オンボーディングのサイズ調整**: シートを dvh 基準(iOSのURLバーで下が切れない)+
  タイル/余白のコンパクト化+フッターを不透過スティッキーに → 決定ボタンが常に見える
- **「2つ選んでレコメンド」をDBファースト化**: /api/recommend が毎回楽天APIを
  叩いていた(遅い・掲載フィルタ非適用)のを、DBからタグ検索・評価順マージに変更。
  除外(表示済み宿)も正しく効く。DB未構築時のみ従来のAPIフォールバック
- **クチコミ全件へのテキストリンク(CTR対策)**: 注記文「〜でご覧いただけます」を
  「クチコミ◯件をすべて読む(楽天トラベル)→」のアンダーライン付きリンクに変更。
  遷移先は楽天のクチコミ専用ページ(APIのreviewUrl)をアフィリエイトラップ
  (AffiliateLinkBuilder::wrapUrl 新設)。data-track="review_more" で計測可能
- **NO IMAGE対策**: カード画像に hotelThumbnailUrl フォールバックを追加+
  画像が1枚も無い宿を掲載対象外に(hotel_filters.exclude_no_image、パージ対象)
- **「評価から見る強みと注意点」を追加(増強6)**: 軸データから ◎強み(4.3以上の上位2軸)と
  △注意点(最下位軸が3.9以下かつ差0.5以上のときのみ)を正直に自動生成。
  マイナス面も書くことで比較サイトとしての信頼性に寄与

## 増強①: 温泉地ガイド辞書
- `app/Config/onsen_areas.php` 新設: 全国の主要温泉地60箇所のガイド辞書
  (登別〜霧島まで各地方をカバー。泉質の傾向・雰囲気・立地の一般的事実のみ記述)
- `OnsenAreaService` 新設: 宿名・住所から温泉地を判定。県制約つきマッチングで
  同名地名の誤マッチを防止(草津市/滋賀、南房総白浜/千葉などで非表示になることを
  テストで担保)。複数該当時はより具体的な名称を優先
- 詳細ページに「◯◯温泉という温泉地」セクションを追加(温泉・お風呂の直後)。
  解説文+「◯◯温泉の宿をさがす」内部リンク(検索ページへ・回遊とSEOの内部リンク)
- この辞書は将来のエリア×テーマページ(/area/...)の材料を兼ねる

## 増強②: エリア(温泉地)ページ
- URL: `/area/{key}`(温泉地トップ)と `/area/{key}/{tag}`(温泉地×テーマ)。
  romajiキーを onsen_areas.php の全61温泉地に付与
- ページ構成: パンくず → H1「◯◯温泉の旅館おすすめランキング◯選【年】」→
  辞書の解説文 → 統計カード(掲載/平均評価/料金中央値)→ テーマチップ
  (露天風呂/食事/絶景/静けさ/一人旅・該当3軒以上のみ表示)→
  ランキング一覧(順位バッジ+ハイライト1行の自動生成)→ エリアFAQ(自動生成)
- 構造化データ: ItemList + FAQPage。sitemap には該当3軒以上のURLのみ登録
- thin content 回避: 該当宿3軒未満の組み合わせはページ自体を生成しない(404)
- DB: hotels に onsen_area 列を追加(upsert時に OnsenAreaService で自動判定・
  既存DBへは冪等ALTER)。エリアページは `WHERE onsen_area=` の一発クエリ
- 詳細ページの「◯◯温泉という温泉地」のリンク先を検索からエリアページに差し替え
  (文言も「◯◯温泉の宿ランキングを見る」に)

## AI生成コンテンツ(Gemini・完全無料枠運用)
設計方針: 「LLMに調べさせない、書かせるだけ」。事実の出所は自DBと検証済み公式HPに限定し、
生成→人間レビュー→git push(=承認・永続化)のフローで公開する。
Render揮発FS対策として生成物はDBではなくリポジトリ内 content/ に保存。

### この宿のこだわり(公式サイトより)
- `bin/generate-official-summary.php`: 公式HP発見(Custom Search JSON API・無料100/日)
  → 三重検証(予約サイト等30ドメイン除外 / 宿名照合 / DBの市区町村照合。
  通らなければ「書かない」) → Gemini(検索なし・素材は本文のみ)で執筆
  → バリデーション → content/official/{hotelNo}.json 保存
- 詳細ページに「この宿のこだわり」セクション(出典リンク+取得月を明記)
- 【無料の担保】日次カウンターで90クエリ/日到達時に自動停止(課金構造なし)

### テーマ特集ページ
- `bin/generate-feature.php`: テーマ指定 or 季節からGeminiが3案提案→対話選択。
  候補20軒はDBから抽出(タグ/県/評価順)、LLMは候補から選んで書くだけ
- バリデーション: hotelNoが候補内か / 数値が素材内か / 禁止表現なし → 不合格は破棄
- `published: false` のドラフトとして保存。レビュー後 true にして push で公開
- `/features`(一覧)+ `/features/{slug}`(本文・ItemList構造化データ・宿カードはDB解決)
- sitemap自動登録・トップ上部タブに「特集」リンク
- サンプルドラフト同梱: content/features/test-rotenburo-hokuriku.json(フォーマット見本)

### 共通基盤
- `GeminiClient`(JSONモード・モデルはGEMINI_MODELで変更可)
- `CustomSearchClient`(日次無料枠カウンター内蔵)
- `AiOutputValidator`(数値の素材照合 / 価格・効能断定・営業状態など禁止パターン)
- `ContentStore`(content/配下の読み出し)
- 必要な環境変数: GEMINI_API_KEY / GOOGLE_CSE_KEY / GOOGLE_CSE_CX(.env.example に取得先を記載)

## 規約対応・自動運転・SEO増強・mixhost移行(最終アップデート)
### 楽天トラベル画像のクレジット表記(API規約対応)
- 全画像(カード/Hero/ギャラリー/客室/ランキング/特集)の左下に「楽天トラベル」を
  オーバーレイ表示。カードは img を .thumb-wrap でラップ、その他はCSSのみで対応。
  No Image には表示しない(:has ガード)
### 特集の完全自動運転
- app/Config/feature_themes.php: 月別テーマプール48本(タグ・編集方針つき)
- generate-feature.php --auto --publish: プールから未使用テーマを自動選択→生成→
  バリデーション→即公開→メール通知。不合格・候補不足は保存せず通知(安全側)。
  プール全消化後はGemini提案にフォールバック
- bin/weekly-content.php: 週次オーケストレーター(特集1本+こだわり25軒+サマリー通知)。
  特集はGeminiのみ・こだわりはCSE日次90上限で自動停止=無料枠内で自動調整
- Notifier(NOTIFY_EMAIL へ mail()送信+notify.log)
### SEO/ユーザー面の追加
- /areas 温泉地一覧ハブ(県ごとにチップ・掲載3軒以上のみ)+ sitemap登録
- エリアページに BreadcrumbList 構造化データ、トップに WebSite 構造化データ
- フッターに主要導線ナビ(温泉地/カテゴリ/特集/検索)
- バッチにログ自動掃除(クリックログ60日・CSEカウンター7日・通知ログ5MB)
### mixhost移行
- docs/MIGRATION-mixhost.md: 契約直後の設定/配置(docroot→public推奨と代替)/
  .env/初期シード/cron3本/DNS切替/Search Console/運用保守/改善ロードマップ
- bin/deploy.sh: git pull + CSS結合 + 全PHP構文チェックの一発デプロイ

## 公式サイト発見をGeminiグラウンディングへ移行(CSE新規閉鎖対応)
- 背景: Custom Search JSON API は新規顧客へのアクセス付与を終了しており、
  新規プロジェクトでは有効化・キー・CXが正しくても 403 PERMISSION_DENIED になる
  (既存顧客も2027年1月までの移行が案内されている)
- 対応: 「この宿のこだわり」のURL発見ステップを Gemini の検索グラウンディングに変更
  (SEARCH_PROVIDER=gemini 既定 / 旧アカウントは =cse で従来動作)
- 安全設計は不変: グラウンディングは「URL候補の発見」のみに使用し、記事本文は
  自前で取得・住所照合済みの公式HP本文から検索なしの通常生成で書く。
  リダイレクトプロキシURLは取得後の最終URLで除外判定・検証を実施
- 無料枠保護: グラウンディングにも日次カウンター(GROUNDING_DAILY_CAP・既定100)
- GeminiClient::generateGrounded()/parseGrounded()(単体テスト済み)、
  GOOGLE_CSE_KEY/CX は不要に(レガシー用に残置)

## 失敗宿の再挑戦とコンテンツ鮮度対応(指摘5・6)
### 指摘5: 失敗した宿の再挑戦(generate-official-summary.php)
- _state.json を新形式に(reason/attempts/lastAt)。過去に公式HP発見失敗した宿も
  OFFICIAL_RETRY_COOLDOWN_DAYS(既定30日)経過かつ OFFICIAL_RETRY_MAX(既定3回)未満なら
  自動で再挑戦。公式サイトが後から作られた/前回の検索がたまたま外した場合を拾える
- not_in_db は試行99=恒久スキップ。成功時は state から除去(生成物ファイルが真実)
- 旧形式(文字列)の state も安全に新形式へ移行
### 指摘6: データ更新への追従
- 特集: 宿カードは元々 findMany でDB解決しているため、評価・クチコミ数・掲載終了は
  自動追従(掲載終了で3軒未満になれば404)。注記でその旨を明示
- 特集に鮮度表示: 公開から180日超で「情報が古い可能性」の注記を自動表示
- こだわり文: OFFICIAL_REFRESH_DAYS(既定0=無効)を設定すると、生成済み記事も
  指定日数経過で公式HPから取り直して再生成。詳細ページは従来どおり取得月を明示

## 運用堅牢化: cron多重起動防止
- app/Support/Lock.php(flockベースの多重起動防止)を新設
- fetch-hotels / weekly-content / generate-official-summary の3バッチ冒頭で
  ロックを取得。前回分の実行が長引いて次回cronと重なっても、2つ目は静かに終了。
  API二重消費・DB競合・_state.json 破損を防ぐ(単体動作確認済み)

## 全件取得フロー改善・クロスセル非表示
- bin/fetch-hotels.php に --full を新設(エリア総当たり列挙→詳細化→パージの通し)。
  --all(カテゴリ4種の軽い列挙)では全国を網羅できないため、初回全件取得用に分離。
  --enumerate-area を --full に連動(日次 --all は従来どおり軽いまま)
- 詳細ページの「旅をもっと楽しむ」(レンタカー・合宿免許)を、env に有効なURLが
  設定された項目のみ表示に変更。未整備の現状は両方 '#' 既定=セクションごと非表示
- docs に全件取得の運用手順(--full/nohup/日次limit/Overpass)を追記

## エリア列挙の網羅性向上(最終ページ自動追尾)
- --area-pages 未指定時、各エリアの最終ページ(30件未満が返るまで)を自動追尾。
  従来の既定3ページ(90件)では人気エリアを取りこぼしていた問題を解消。
  楽天API上限(page100×hits30=1エリア最大3,000件)まで取り切る
- 30件未満を検知したら即次エリアへ(無駄打ちなし)。--area-pages 指定時は従来通り頭打ち
- docs に「fullでも全部は取れない」の構造的理由(API上限・楽天未掲載)を明記

## 楽天API新仕様(2026年2月刷新)対応の明文化
- .env.example に RAKUTEN_ACCESS_KEY(pk_形式)を追記。新APIは applicationId(UUID)と
  accessKey の両方が必須で、「許可されたWebサイト」にAPP_URLのドメイン登録も必要
- コード(RakutenApiClient/config)は openapi.rakuten.co.jp/engine/api・accessKey・
  Origin/Referer 送信に対応済み。運用時は .env に両キーの設定を忘れないこと

## GetAreaClass非対応への対応(都道府県フォールバック)
- 新API(2026刷新)でGetAreaClassが "API Configuration not found" になるアプリ設定でも
  全件取得できるよう、47都道府県の固定コード辞書(rakuten_prefectures.php)を追加
- AreaClassService: GetAreaClassが空を返したら都道府県辞書に自動フォールバック
  (SimpleHotelSearchは都道府県単位で最大3,000件返せるため全国網羅可能)
- AreaHotelSearchService: smallClassCode 空なら省略し県単位検索に対応
- SimpleHotelSearch(宿検索)自体は新API仕様で正常動作を確認済み

## UI修正・レスポンシブ対応・SEO追加(最新)
### 修正
- 合宿免許・レンタカー: env に http(s) URLが設定された時のみ表示(現状は非表示)。
  表示条件を str_starts_with('http') に堅牢化し、#やコメント混入でも誤表示しない
- 楽天トラベルクレジット: user-select/touch-action:none で掴んで動く問題を解消。
  ヒーローに overflow:hidden、hero-scroller に touch-action:pan-x で縦ドラッグ暴走を防止
- トップのタブ: scrollIntoView + scroll-margin-top 方式に変更し確実にスクロール
- オンボーディング: シートを flex 化し、グリッドだけスクロール・フッターボタン常時表示
### PC/タブレット対応
- 640-899px(タブレット縦)は端末フレーム風、900px以上は最大1200px幅の通常レイアウト
- 900px以上で下部固定ナビを非表示(フッターナビ・上部タブで代替)、本文は最大1080px中央寄せ
### SEO追加(リスクゼロ)
- WebSite に SearchAction(sitelinks searchbox 狙い)、Organization 構造化データを追加
- ※ Hotel の AggregateRating は従来通り不採用(セルフサービングレビュー・ポリシー配慮)
### cron
- docs に最新推奨cron構成を追記(日次は --enumerate-area で47都道府県フォールバック活用)

## iOS Safari のスクロール問題を根本修正
- reset.css: html/body を position:fixed + overflow:hidden で固定。
  iOS Safari は body がスクロール可能だと掴んだ場所に関係なくページ全体が
  引っ張られる(バウンス)ため、スクロールは .scroll-area だけに限定した
- .scroll-area に overscroll-behavior:contain と scroll-behavior:smooth を付与
- top-tabs.js: scrollIntoView は iOS で無視されることがあるため、
  .scroll-area の scrollTop を rect 差分から自前計算する方式に変更
  (なめらかさは CSS の scroll-behavior が担当)
- オンボーディング表示中は下部ナビ・CTA を非表示に(:has() + body.ob-active の二重化)。
  シート下部のボタンとナビが物理的に重なって押しづらい問題を解消
