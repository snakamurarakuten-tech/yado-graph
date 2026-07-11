#!/bin/sh
# =====================================================================
# 起動エントリポイント(改修: Render無料枠の揮発FS対応 → 本番移行対応)
#
# 起動フロー:
#   1. storage 配下のディレクトリを保証(Persistent Diskの初回マウントでも動く)
#   2. DBの件数と鮮度を確認
#      - 件数 < SEED_MIN        → バックグラウンドでフルシード(--all)
#      - 古さ > REFRESH_DAYS 日 → バックグラウンドで詳細の差分更新(--detail)
#      ※ どちらもサーバー起動をブロックしない。進捗は storage/logs/batch.log
#   3. PHPビルトインサーバーを起動
#
# 環境変数(Render のダッシュボードから調整可能):
#   SEED_MIN     … この件数未満ならフルシード実行(既定 20)
#   SEED_PAGES   … シード時のカテゴリあたり取得ページ数(既定 3)
#   SEED_LIMIT   … シード時の詳細化上限件数(既定 200)
#   REFRESH_DAYS … 最古データがこの日数を超えたら差分更新(既定 7)
#   REFRESH_LIMIT… 差分更新の上限件数(既定 100)
#
# 本番移行(案B)時:
#   - Persistent Disk を /app/storage にマウント → DBが永続化され、
#     再起動時は件数チェックでシードがスキップされる
#   - Render Cron Job に「php bin/fetch-hotels.php --all」を日次登録すれば、
#     このスクリプトの鮮度チェックはお守りとして残るだけになる
# =====================================================================
set -u

SEED_MIN="${SEED_MIN:-20}"
SEED_PAGES="${SEED_PAGES:-3}"
SEED_LIMIT="${SEED_LIMIT:-200}"
REFRESH_DAYS="${REFRESH_DAYS:-7}"
REFRESH_LIMIT="${REFRESH_LIMIT:-100}"

cd /app

# 1) storage の実体を保証(Persistent Diskを空でマウントした直後でも壊れない)
mkdir -p storage/cache storage/db storage/logs

# 2) DB状態: "<件数> <最古の経過日数>"
STATUS="$(php bin/db-status.php 2>/dev/null || echo '0 9999')"
COUNT="$(echo "$STATUS" | awk '{print $1}')"
AGE="$(echo "$STATUS" | awk '{print $2}')"
echo "[entrypoint] hotels=${COUNT} oldest=${AGE}d (seed_min=${SEED_MIN}, refresh_days=${REFRESH_DAYS})"

if [ "${RAKUTEN_APP_ID:-}" = "" ]; then
  echo "[entrypoint] WARN: RAKUTEN_APP_ID が未設定のためシードをスキップします"
elif [ "$COUNT" -lt "$SEED_MIN" ]; then
  echo "[entrypoint] DBが空/少ないため、バックグラウンドでフルシードを開始します(数分かかります)"
  nohup php bin/fetch-hotels.php --all "--pages=${SEED_PAGES}" "--limit=${SEED_LIMIT}" \
    >> storage/logs/batch.log 2>&1 &
elif [ "$AGE" -gt "$REFRESH_DAYS" ]; then
  echo "[entrypoint] データが古いため、バックグラウンドで差分更新を開始します"
  nohup php bin/fetch-hotels.php --detail "--limit=${REFRESH_LIMIT}" \
    >> storage/logs/batch.log 2>&1 &
fi

# 3) サーバー起動(フォアグラウンド)
exec php -S "0.0.0.0:${PORT:-10000}" -t public router.php
