#!/bin/sh
set -e

cd /app

# ── Railway MySQL: build DATABASE_URL from MYSQL* when not preset (URL-encode credentials) ──
if [ -z "$DATABASE_URL" ] && [ -n "$MYSQLHOST" ]; then
    export DATABASE_URL="$(php -r 'echo sprintf(
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

# ── JWT key files (Lexik) ──
mkdir -p config/jwt

if [ -n "$JWT_PRIVATE_KEY_BASE64" ] && [ -n "$JWT_PUBLIC_KEY_BASE64" ]; then
    echo "$JWT_PRIVATE_KEY_BASE64" | base64 -d > config/jwt/private.pem
    echo "$JWT_PUBLIC_KEY_BASE64" | base64 -d > config/jwt/public.pem
elif [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
    echo "Generating JWT key pair (set JWT_*_BASE64 in Railway to persist across deploys)..." >&2
    openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
    openssl pkey -in config/jwt/private.pem -pubout -out config/jwt/public.pem
fi

export JWT_SECRET_KEY="${JWT_SECRET_KEY:-/app/config/jwt/private.pem}"
export JWT_PUBLIC_KEY="${JWT_PUBLIC_KEY:-/app/config/jwt/public.pem}"

php bin/console cache:clear --env=prod --no-warmup 2>/dev/null || true
php bin/console cache:warmup --env=prod

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "Running database migrations..." >&2
    php bin/console doctrine:migrations:migrate --no-interaction --env=prod
fi

PORT="${PORT:-8080}"
echo "Starting PHP server on 0.0.0.0:${PORT}" >&2
exec php -S "0.0.0.0:${PORT}" -t public
