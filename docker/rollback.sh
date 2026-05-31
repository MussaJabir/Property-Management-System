#!/usr/bin/env bash
#
# Rollback to a previously-deployed image SHA.
#
# Usage:
#   ./rollback.sh <short-sha>           # roll to a specific SHA
#   ./rollback.sh --list                # list recent deployed SHAs (from docker images)
#
# The deploy workflow tags every image with the git short-SHA AND latest.
# Rolling back = re-tagging an older SHA as the one compose pulls.

set -euo pipefail

IMAGE="ghcr.io/mussajabir/pms"

if [[ "${1:-}" == "--list" ]]; then
    docker image ls "$IMAGE" --format 'table {{.Tag}}\t{{.CreatedAt}}\t{{.Size}}' \
        | grep -vE '^TAG\b|^latest\b'
    exit 0
fi

if [[ -z "${1:-}" ]]; then
    echo "Usage: $0 <short-sha>  |  $0 --list"
    exit 1
fi

TARGET_SHA="$1"

if ! docker image inspect "${IMAGE}:${TARGET_SHA}" >/dev/null 2>&1; then
    echo "Image ${IMAGE}:${TARGET_SHA} not present locally — pulling…"
    docker pull "${IMAGE}:${TARGET_SHA}"
fi

cd /opt/pms

export PMS_TAG="$TARGET_SHA"

echo "Rolling app + scheduler back to ${TARGET_SHA}…"
docker compose -f docker-compose.production.yml --env-file .env up -d app scheduler

echo "Waiting for health…"
for i in $(seq 1 30); do
    status=$(docker inspect --format='{{.State.Health.Status}}' pms-app-1 2>/dev/null || echo "starting")
    [ "$status" = "healthy" ] && break
    sleep 2
done

if [ "$status" != "healthy" ]; then
    echo "⚠  app container did not become healthy. Check logs:"
    echo "   docker compose -f docker-compose.production.yml logs --tail=100 app"
    exit 1
fi

echo "⚠  IMPORTANT: if the SHA you rolled back to predates a migration, you"
echo "    may need to manually run migrate:rollback. See DEPLOYMENT.md."
echo "✅ Rolled back to ${TARGET_SHA}"
