#!/bin/sh
set -e
cd /app

php bin/console cache:clear --env=prod --no-debug 2>/dev/null || true

PORT="${PORT:-8080}"
exec php -S "0.0.0.0:${PORT}" -t public
