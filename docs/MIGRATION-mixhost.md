# mixhost 移行手順と運用マニュアル

## 0. mixhost で良いか(結論: 良い)

このサイトは「素のPHP + SQLite + cron」で動くので、mixhost(LiteSpeed・PHP選択可・
cron可・SSH可・ディスク永続)は要件を全部満たします。Render無料枠の弱点
(スリープ・揮発FS)が両方解消されるのが最大の利点です。
同格の代替は エックスサーバー / ロリポップ ハイスピード / さくらのレンタルサーバ で、
どれでも動きます(手順もほぼ同じ)。VPSは管理の手間に見合わないので不要です。

## 1. 契約直後にやること(コントロールパネル)

1. **PHPバージョン**: 8.2 以上を選択
2. **PHP拡張**: `pdo_sqlite` / `sqlite3` / `mbstring` / `curl` / `intl` が有効か確認
   (mixhostは既定で有効なことが多い。「PHP Extensions」画面でチェック)
3. **独自ドメイン追加 + 無料SSL**(Let's Encrypt 自動)

## 2. アプリの配置(重要: ドキュメントルート)

セキュリティ上、**公開されるのは public/ だけ**にします。

### 推奨: ドキュメントルートを public に向ける
1. SSH で接続し、ホームにクローン:
   ```
   cd ~
   git clone <あなたのリポジトリURL> yado-graph
   ```
2. cPanel「アドオンドメイン」(またはドメイン設定)で、対象ドメインの
   **ドキュメントルートを `/home/ユーザー名/yado-graph/public`** に設定

### 代替: ドキュメントルートを変えられない場合
`public_html/ドメイン名/` に以下の .htaccess を置き、リポジトリは
`~/yado-graph` に置いたままシンボリックリンクを張る:
```
ln -s ~/yado-graph/public/* ~/public_html/ドメイン名/
ln -s ~/yado-graph/public/.htaccess ~/public_html/ドメイン名/.htaccess
```
(index.php 内の BASE_PATH は実体パスを解決するのでそのまま動きます)

## 3. 初期設定(SSH)

```sh
cd ~/yado-graph
cp .env.example .env
vi .env    # 下記を設定
php bin/build-css.php                       # CSSを1ファイルに結合
php bin/fetch-hotels.php --all --pages=5 --limit=800   # 初期取り込み(20〜30分)
php bin/db-status.php                       # 件数を確認
```

.env の必須項目:
```
APP_URL=https://あなたのドメイン
RAKUTEN_APP_ID=...
RAKUTEN_AFFILIATE_ID=...
GEMINI_API_KEY=...        # ※チャット等に貼ったキーは必ず再発行してから設定
GOOGLE_CSE_KEY=...        # AIza… 形式のAPIキー(Custom Search JSON APIを有効化)
GOOGLE_CSE_CX=...         # プログラム可能検索エンジンのID
NOTIFY_EMAIL=あなたのメール  # 自動生成の結果通知
GA4_MEASUREMENT_ID=...    # 任意
```
※ .env と storage/ は public/ の外にあるため、推奨構成なら外部から読めません。

## 4. cron 登録(cPanel「Cron ジョブ」)

PHPのフルパスは mixhost では `/usr/local/bin/php`(`which php` で確認)。

```
# 毎日3時: 宿データの取り込み・詳細化・パージ・GC
0 3 * * *  cd /home/ユーザー名/yado-graph && /usr/local/bin/php bin/fetch-hotels.php --all >> storage/logs/batch.log 2>&1

# 毎週月曜4時: 特集の自動公開 + こだわり文の生成(無料枠内・結果はメール通知)
0 4 * * 1  cd /home/ユーザー名/yado-graph && /usr/local/bin/php bin/weekly-content.php >> storage/logs/content.log 2>&1

# 毎日5時: こだわり文の追加生成(検索無料枠90/日の範囲で自動停止)
0 5 * * *  cd /home/ユーザー名/yado-graph && /usr/local/bin/php bin/generate-official-summary.php --limit=25 >> storage/logs/content.log 2>&1
```

※ mixhost では content/ が永続ディスクなので、生成物の git push は不要になります
  (リポジトリへのバックアップとしてやっても良い)。

## 5. 切り替えと移行後すぐやること

1. DNS を mixhost に向ける(反映まで数時間)
2. https でトップ・/search・/areas・任意の /hotel/ が開くことを確認
3. **Search Console**: 新ドメインでプロパティ登録 → `https://ドメイン/sitemap.xml` 送信
4. Render 側のサービスを停止(旧URLが生きているとコピーコンテンツになる)
5. NOTIFY_EMAIL 宛に届く週次レポートで自動生成の稼働を確認

## 6. 日常の運用・保守

- **コード更新**: ローカルで修正 → git push → サーバーで `sh bin/deploy.sh`
  (pull + CSS再結合 + 全PHP構文チェックまで一発)
- **状態確認**: `php bin/db-status.php`(件数と鮮度) / `tail storage/logs/batch.log`
- **自動生成の品質管理**: 特集は自動公開後にメールが届くので目を通す。
  問題があれば `content/features/○○.json` の `"published"` を false に
- **バックアップ(月1推奨)**: `tar czf ~/backup-$(date +%Y%m).tar.gz storage/db content .env`
  (DB・生成コンテンツ・設定の3点が資産。コードはgitにある)
- **ログ肥大**: バッチが自動で掃除(クリックログ60日・通知ログ5MB)。放置でOK

## 7. 移行後の改善ロードマップ(優先順)

1. 掲載数の拡大: cron の `--pages=8 --limit=2000` などに引き上げ
   → エリアページの生成数が一気に増える(現状の最重要レバー)
2. こだわり文をクチコミ上位から蓄積(週次で自動。3ヶ月で約1,000軒)
3. Search Console のクエリデータが溜まったら(3ヶ月後)、
   流入のあるエリア/テーマに特集・辞書を追加投資
4. アクセスが伸びてきたら OGP 画像の宿別生成、パンくずの拡充などの磨き込み

## 8. Search Console 登録のタイミングと残タスク

### いつ登録するか
「サイトが独自ドメインで表示される」ようになった直後が正解。具体的には:
1. mixhost の vhost 修復が完了し https://yadograph.jp が表示される
2. トップ・/search・/areas・任意の /hotel/・/sitemap.xml が 200 で開ける
→ この2つが確認できたら即 Search Console 登録+sitemap 送信

コンテンツ(こだわり文・特集)が0本でも登録は早いほうが良い(インデックス開始が
早まる)。中身は後から自動で増えるので、登録を待つ必要はない。

### 登録後にやること(順番)
1. Search Console でドメインプロパティ登録(DNS TXT 認証。お名前.com側でTXT追加)
2. https://yadograph.jp/sitemap.xml を送信
3. Render 側サービスを停止(重複コンテンツ回避。表示確認の後で)
4. robots.txt の Sitemap 行が本番ドメインを指しているか確認

## 9. 現時点の残タスク一覧(優先順)

- [ ] **mixhost vhost 修復**(サポート対応待ち)← サイト表示の唯一のブロッカー
- [ ] Gemini 無料枠 429 の解消 → 翌日リセット後に generate-official-summary を再テスト
      (グラウンディング枠だけ切れている場合は知識ベースフォールバックが自動で動く)
- [ ] サイト表示後: Search Console 登録・sitemap 送信・Render 停止
- [ ] 掲載数の拡大: cron の fetch-hotels を --enumerate-area 併用に切替、--pages/--limit 増
- [ ] こだわり文の初回品質レビュー(--hotel=8886 が通ったら中身を確認)
- [ ] 対象がシティホテル中心なので、旅館・温泉宿を優先する絞り込みの検討(任意)

## 10. 自動運用パラメータ早見(.env)

- OFFICIAL_LIMIT: 週次のこだわり生成上限
- OFFICIAL_RETRY_COOLDOWN_DAYS / OFFICIAL_RETRY_MAX: 失敗宿の再挑戦制御
- OFFICIAL_REFRESH_DAYS: 生成済み記事の再取得間隔(0=無効。運用が安定してから 180 程度を推奨)
- GROUNDING_DAILY_CAP: グラウンディングの日次上限(無料枠保護)

## 11. 全件取得の運用(重要・網羅性)

### 初回の全件取得は --full を使う
`--all` は「カテゴリ横断列挙(4カテゴリ)+詳細化」で、日次向けに軽く作ってある。
これだけでは楽天の全国の宿を網羅できない(数百件どまり)。
初回の全件取得は、エリア総当たり列挙を含む --full を使う:
```
# 初回のみ(数時間かかる。画面を閉じても続くよう nohup 推奨)
nohup php bin/fetch-hotels.php --full --limit=0 >> storage/logs/full.log 2>&1 &
# 進捗確認
tail -f storage/logs/full.log
php bin/db-status.php
```
- `--area-pages` を指定しない場合、各エリアの最終ページまで自動追尾する
  (楽天API上限=1エリア最大3,000件まで。人気エリアも取りこぼさない)
- お試しや時短で件数を絞りたいときだけ `--area-pages=3` のように指定
- `--limit=0`: 詳細化を全件無制限で実行(0=無制限。初回はこれ)

### 「fullでも全部は取れない」について
2つの上限がある。(1) 楽天API自体の上限で、1エリアあたり最大3,000件
(page100×hits30)まで。上記の自動追尾でこの上限までは取り切る。
(2) そもそも楽天トラベル未掲載の宿は取得不可(仕様上どうにもならない)。
実用上は (1) を取り切れば十分な網羅性になる。取りこぼしが心配なら、
別の観点(--enumerate のカテゴリ列挙)も併用すると穴が埋まりやすい。

### 日次cronは --all のまま + limitを明示
日次は差分更新なので軽い --all でよいが、`--limit` 未指定だと詳細化が既定300件で
打ち切られる。新規宿が多い時期は数字を上げる:
```
0 3 * * *  cd ~/yado-graph && /usr/local/bin/php bin/fetch-hotels.php --all --limit=500 >> storage/logs/batch.log 2>&1
```
新規列挙は --all のカテゴリ列挙で拾えるが、取りこぼしが気になる場合は
月1で --full を回すと穴が埋まる(cronに月次で1本足してもよい)。

### GraphAPI(周辺情報)について
詳細化(--detail/--all/--full)の中で SurroundingsService が Overpass(OSM)から
周辺スポットを取得する。ここは楽天ではなくOSMで、1秒sleepでレート順守済み。
全件だと時間がかかる主因はここなので、初回は気長に(nohup推奨)。
