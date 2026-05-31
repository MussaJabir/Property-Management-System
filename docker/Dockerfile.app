# syntax=docker/dockerfile:1.7
#
# PMS production image. Multi-stage to keep the runtime small:
#   1. composer  — installs PHP deps (no-dev, optimised autoload)
#   2. node      — builds Vite assets (CSS bundle, public/build/)
#   3. runtime   — PHP-FPM + a thin in-container nginx (supervisord-managed)
#
# Tag = git SHA in CI. Built by GitHub Actions, pushed to ghcr.io/<owner>/pms.

# ─────────── 1. composer stage ───────────
FROM composer:2.7 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
COPY database/ database/
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# ─────────── 2. node / vite stage ───────────
FROM node:22-alpine AS node
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources/ resources/
COPY vite.config.js postcss.config.js* tailwind.config.js* ./
COPY public/ public/
RUN npm run build

# ─────────── 3. runtime ───────────
FROM php:8.4-fpm-alpine AS runtime

RUN set -eux; \
    apk add --no-cache \
        bash curl unzip ca-certificates tzdata supervisor nginx \
        icu-libs libpq libpng libjpeg-turbo libwebp freetype libzip oniguruma \
        postgresql-client; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev postgresql-dev libpng-dev libjpeg-turbo-dev \
        libwebp-dev freetype-dev libzip-dev oniguruma-dev linux-headers; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
    docker-php-ext-install -j$(nproc) \
        bcmath gd intl mbstring opcache pcntl pdo_pgsql pgsql zip; \
    pecl install redis-6.1.0 && docker-php-ext-enable redis; \
    apk del .build-deps; \
    rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV TZ=Africa/Dar_es_Salaam

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-pms.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-pms.conf
COPY docker/nginx-internal.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

COPY . .
COPY --from=composer /app/vendor ./vendor
COPY --from=node /app/public/build ./public/build

RUN set -eux; \
    mkdir -p storage/framework/{cache/data,sessions,testing,views} storage/logs bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache; \
    php artisan config:clear || true; \
    php artisan view:clear || true; \
    php artisan route:clear || true; \
    mkdir -p /var/log/supervisor /run/nginx; \
    chown -R www-data:www-data /var/log/nginx /run/nginx

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

# In-container nginx listens on 8080; host nginx proxy-passes here.
EXPOSE 8080
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
