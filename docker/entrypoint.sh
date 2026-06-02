#!/bin/sh
#
# Container entrypoint for the PMS app image.
#
# Runs as root on every container start, BEFORE supervisord (which spawns
# php-fpm + nginx as www-data). Handles the things that depend on the
# mounted `storage` volume and therefore cannot be fully fixed at image
# build time:
#
#   1. Ensure the storage/ tree exists in the (possibly pre-existing) volume
#      and is owned by www-data — php-fpm writes logs, cache, sessions, and
#      uploaded media here.
#   2. Ensure the public storage symlink exists (baked at build time, but
#      re-created defensively here in case of an odd volume/image state).
#   3. Ensure nginx's request-body temp dir is writable by the www-data
#      worker — otherwise uploads > the in-memory buffer 500 before PHP.

set -e

APP_ROOT="/var/www/html"

# 1. Storage tree: create the dirs Laravel/Livewire/Spatie need, then chown.
#    The named volume mounts on top of storage/, so the image's build-time
#    chown does not apply to a pre-existing volume — redo it here.
mkdir -p \
    "$APP_ROOT/storage/app/public" \
    "$APP_ROOT/storage/app/private/livewire-tmp" \
    "$APP_ROOT/storage/framework/cache/data" \
    "$APP_ROOT/storage/framework/sessions" \
    "$APP_ROOT/storage/framework/views" \
    "$APP_ROOT/storage/logs"
chown -R www-data:www-data "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" 2>/dev/null || true
chmod -R ug+rwX "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" 2>/dev/null || true

# 2. Public storage symlink. Baked at build time; recreate defensively (as
#    root — www-data cannot write into the root-owned public/ directory).
if [ ! -L "$APP_ROOT/public/storage" ]; then
    ln -sfn "$APP_ROOT/storage/app/public" "$APP_ROOT/public/storage"
fi

# 3. nginx temp dirs writable by the www-data worker (volume-independent, but
#    cheap to re-assert in case the base image changes ownership defaults).
mkdir -p /var/lib/nginx/tmp/client_body /var/lib/nginx/tmp/proxy /var/lib/nginx/tmp/fastcgi
chown -R www-data:www-data /var/lib/nginx 2>/dev/null || true

# Hand off to CMD (supervisord).
exec "$@"
