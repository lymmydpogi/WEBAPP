#!/bin/sh
set -e

export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

echo "Starting with APP_ENV=$APP_ENV APP_DEBUG=$APP_DEBUG" >&2

# Railway may set APP_ENV=dev; production image has no real .env — force prod
if [ "$APP_ENV" = "dev" ] || [ "$APP_ENV" = "development" ]; then
    export APP_ENV=prod
    export APP_DEBUG=0
    echo "Overriding APP_ENV to prod (production container)." >&2
fi

cd /app

# ── Railway MySQL: build DATABASE_URL from MYSQL* when not preset (URL-encode credentials) ──
if [ -z "$DATABASE_URL" ] && [ -n "$MYSQLHOST" ]; then
    export DATABASE_URL="$(env APP_ENV="${APP_ENV}" APP_DEBUG="${APP_DEBUG}" php -r 'echo sprintf(
        "mysql://%s:%s@%s:%s/%s?serverVersion=%s&charset=utf8mb4",
        rawurlencode(getenv("MYSQLUSER") ?: ""),
        rawurlencode(getenv("MYSQLPASSWORD") ?: ""),
        getenv("MYSQLHOST"),
        getenv("MYSQLPORT") ?: "3306",
        getenv("MYSQLDATABASE"),
        getenv("MYSQL_VERSION") ?: "8.0"
    );')"
fi

if [ -z "$DATABASE_URL" ]; then
    echo "ERROR: DATABASE_URL is not set (and MYSQLHOST is missing). Link a Railway MySQL service or set DATABASE_URL." >&2
    exit 1
fi

# ── JWT key files (Lexik) — baked in at image build; override via Railway base64 vars ──
mkdir -p config/jwt

if [ -n "$JWT_PRIVATE_KEY_BASE64" ] && [ -n "$JWT_PUBLIC_KEY_BASE64" ]; then
    echo "Loading JWT keys from Railway variables..." >&2
    echo "$JWT_PRIVATE_KEY_BASE64" | base64 -d > config/jwt/private.pem
    echo "$JWT_PUBLIC_KEY_BASE64" | base64 -d > config/jwt/public.pem
    chmod 600 config/jwt/private.pem 2>/dev/null || true
elif [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
    echo "Generating JWT key pair (quiet; set JWT_*_BASE64 in Railway to persist across deploys)..." >&2
    openssl genrsa -out config/jwt/private.pem 2048 2>/dev/null
    openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem 2>/dev/null
    chmod 600 config/jwt/private.pem 2>/dev/null || true
fi

export JWT_SECRET_KEY="${JWT_SECRET_KEY:-/app/config/jwt/private.pem}"
export JWT_PUBLIC_KEY="${JWT_PUBLIC_KEY:-/app/config/jwt/public.pem}"

env APP_ENV="${APP_ENV}" APP_DEBUG="${APP_DEBUG}" \
    php bin/console cache:clear --env=prod --no-debug --no-warmup 2>/dev/null || true

env APP_ENV="${APP_ENV}" APP_DEBUG="${APP_DEBUG}" \
    php bin/console cache:warmup --env=prod --no-debug

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "Running database migrations..." >&2
    env APP_ENV="${APP_ENV}" APP_DEBUG="${APP_DEBUG}" \
        php bin/console doctrine:migrations:migrate --no-interaction --env=prod --no-debug
fi

echo "Starting PHP server on 0.0.0.0:${PORT:-8080} (APP_ENV=${APP_ENV})" >&2
exec env APP_ENV="${APP_ENV}" APP_DEBUG="${APP_DEBUG}" \
    php -S "0.0.0.0:${PORT:-8080}" -t public
