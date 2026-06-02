#!/bin/sh
#
# Container entrypoint for the PMS app image.
#
# Runs once on every container start, BEFORE supervisord (which spawns
# php-fpm + nginx). Handles two things that cannot be baked into the
# image because they depend on the mounted volume:
#
#   1. chown the storage/ + bootstrap/cache/ trees to www-data.
#      The image's `chown` in the Dockerfile is overridden by the
#      named volume `pms_app_storage` that mounts on top of storage/.
#      Without this, php-fpm (running as www-data) cannot write to
#      storage/logs, storage/framework, or storage/app — Spatie Media
#      uploads fail with "Permission denied", and Laravel cannot log
#      its own startup errors.
#
#   2. Create the public storage symlink (`public/storage` -> `storage/app/public`)
#      so files uploaded via the `public` disk are reachable via URL.
#      Idempotent — skipped if the symlink already exists.

set -e

APP_ROOT="/var/www/html"

# 1. Fix volume ownership/permissions.
if [ -d "$APP_ROOT/storage" ]; then
    chown -R www-data:www-data "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" 2>/dev/null || true
    chmod -R ug+rwX "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" 2>/dev/null || true
fi

# 2. Public storage symlink (required for the `public` filesystem disk).
if [ ! -L "$APP_ROOT/public/storage" ]; then
    su-exec www-data php "$APP_ROOT/artisan" storage:link 2>/dev/null \
        || php "$APP_ROOT/artisan" storage:link 2>/dev/null \
        || true
fi

# Hand off to CMD (supervisord).
exec "$@"
