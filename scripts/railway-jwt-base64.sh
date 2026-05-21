#!/usr/bin/env sh
# Encode JWT PEM files for Railway variables JWT_PRIVATE_KEY_BASE64 / JWT_PUBLIC_KEY_BASE64
set -e
cd "$(dirname "$0")/.."
if [ ! -f config/jwt/private.pem ]; then
  echo "Run: php bin/console lexik:jwt:generate-keypair" >&2
  exit 1
fi
echo "JWT_PRIVATE_KEY_BASE64=$(base64 -w0 config/jwt/private.pem 2>/dev/null || base64 config/jwt/private.pem | tr -d '\n')"
echo "JWT_PUBLIC_KEY_BASE64=$(base64 -w0 config/jwt/public.pem 2>/dev/null || base64 config/jwt/public.pem | tr -d '\n')"
