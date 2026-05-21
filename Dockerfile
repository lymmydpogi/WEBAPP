# syntax=docker/dockerfile:1
# Symfony 7 + Webpack Encore — production image for Railway

FROM composer:2 AS vendor
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev \
    && docker-php-ext-install intl zip \
    && rm -rf /var/lib/apt/lists/*
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
ENV npm_config_audit=false 
ENV npm_config_fund=false in terminal
COPY --from=vendor /app/vendor ./vendor
COPY webpack.config.js postcss.config.js tailwind.config.js ./
COPY assets ./assets
RUN npm run build

FROM php:8.2-cli-alpine AS runtime
RUN apk add --no-cache \
        icu-dev libzip-dev oniguruma-dev \
        wkhtmltopdf ttf-freefont \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl opcache zip \
    && apk del icu-dev libzip-dev oniguruma-dev

WORKDIR /app

COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && mkdir -p var/cache var/log public/uploads/avatars config/jwt \
    && chown -R www-data:www-data var public/uploads config/jwt

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    PORT=8080

USER www-data

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
