FROM php:8.2-cli

# 楽天APIクライアント(curl)＋自前DB(pdo_sqlite)に必要な拡張
RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libsqlite3-dev \
        pkg-config \
    && docker-php-ext-install curl pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

# Renderは環境変数 PORT でリッスンポートを渡してくる
ENV PORT=10000
# ビルトインサーバーの同時接続耐性(改修7-7)。php -S は既定で1リクエストずつ処理するため。
ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 10000

# CSSを1ファイルに結合(改修: 22リクエスト→1)
RUN php bin/build-css.php

CMD ["sh", "/app/docker-entrypoint.sh"]
