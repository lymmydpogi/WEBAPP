# syntax=docker/dockerfile:1
# Symfony 7 + Webpack Encore — production image for Railway

FROM composer:2 AS vendor
WORKDIR /app
RUN apk add --no-cache git unzip
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /app/vendor ./vendor
COPY webpack.config.js postcss.config.js tailwind.config.js ./
COPY assets ./assets
RUN npm run build

FROM php:8.2-cli-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
        openssl \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        wkhtmltopdf \
        fontconfig \
        xfonts-base \
        xfonts-75dpi \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl opcache zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && mkdir -p var/cache var/log var/sessions public/uploads/avatars config/jwt \
    && printf '%s\n' 'APP_ENV=prod' 'APP_DEBUG=0' > .env \
    && openssl genrsa -out config/jwt/private.pem 2048 2>/dev/null \
    && openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem 2>/dev/null \
    && chmod 600 config/jwt/private.pem \
    && chown -R www-data:www-data var public/uploads config/jwt .env

ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV PORT=8080

USER www-data

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
