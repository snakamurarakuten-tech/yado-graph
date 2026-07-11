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
