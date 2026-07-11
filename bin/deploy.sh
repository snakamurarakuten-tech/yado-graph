#!/bin/sh
# =====================================================================
# mixhost等での更新デプロイ(SSHで実行): sh bin/deploy.sh
#  1. git pull で最新コードを取得
#  2. CSSを1ファイルに再結合
#  3. 構文チェック(全PHP)— エラーがあればその場で分かる
# DB・content/・.env には触れない(安全)。
# =====================================================================
set -e
cd "$(dirname "$0")/.."
echo "[deploy] git pull"
git pull --ff-only
echo "[deploy] build css"
php bin/build-css.php
echo "[deploy] lint"
find app resources bin -name "*.php" -exec php -l {} \; | grep -v "No syntax errors" || true
echo "[deploy] done $(date '+%Y-%m-%d %H:%M')"
