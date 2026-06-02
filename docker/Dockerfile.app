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
# The composer base image ships PHP 8.3 without intl/pcntl/bcmath/gd/exif,
# while our lockfile targets PHP 8.4 + those extensions. We don't need them
# resolved here — vendor/ is just code that runs in the PHP 8.4 runtime stage
# below, which DOES have them — so skip platform checks during install.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --ignore-platform-reqs

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
        bcmath exif gd intl mbstring opcache pcntl pdo_pgsql pgsql zip; \
    pecl install redis-6.1.0 && docker-php-ext-enable redis; \
    apk del .build-deps; \
    rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENV TZ=Africa/Dar_es_Salaam

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-pms.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-pms.conf
COPY docker/nginx-internal.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# su-exec lets the entrypoint optionally drop privileges to www-data for any
# artisan housekeeping it runs at boot.
RUN apk add --no-cache su-exec

WORKDIR /var/www/html

COPY . .
COPY --from=composer /app/vendor ./vendor
COPY --from=node /app/public/build ./public/build

RUN set -eux; \
    mkdir -p storage/framework/{cache/data,sessions,testing,views} storage/logs bootstrap/cache; \
    mkdir -p storage/app/public storage/app/private; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache; \
    # Public storage symlink, baked at BUILD time as root. public/ is part of
    # the image (NOT the mounted volume — only storage/ is), so this symlink
    # persists and resolves at runtime against the volume-backed target.
    # Doing it here avoids the runtime `storage:link` which fails because
    # www-data cannot write into the root-owned public/ directory.
    ln -sfn /var/www/html/storage/app/public public/storage; \
    php artisan config:clear || true; \
    php artisan view:clear || true; \
    php artisan route:clear || true; \
    # Publish Filament-bundled CSS/JS/fonts into public/. Without this the
    # admin + operator panels render unstyled HTML. Idempotent — re-published
    # on every deploy via the workflow as well.
    php artisan filament:assets || true; \
    mkdir -p /var/log/supervisor /run/nginx; \
    chown -R www-data:www-data /var/log/nginx /run/nginx; \
    # nginx runs its worker as www-data (see nginx-internal.conf), so its
    # request-body temp dir must be writable by www-data. Without this, file
    # uploads larger than the in-memory buffer fail with HTTP 500
    # ("client_body ... Permission denied") before ever reaching PHP/Livewire.
    mkdir -p /var/lib/nginx/tmp/client_body /var/lib/nginx/tmp/proxy /var/lib/nginx/tmp/fastcgi; \
    chown -R www-data:www-data /var/lib/nginx

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

# In-container nginx listens on 8080; host nginx proxy-passes here.
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
