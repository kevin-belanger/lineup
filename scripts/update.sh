#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_DIR"

APP_SERVICE="app"
VERSION_TAG_PATTERN='^v[0-9]+\.[0-9]+\.[0-9]+.*$'

echo "=== LineUp update ==="

if [ ! -d ".git" ]; then
    echo "Error: this script must be located in the scripts directory of a Git repository."
    exit 1
fi

if [ ! -f ".env" ]; then
    echo "Error: .env file not found in project root: $PROJECT_DIR"
    exit 1
fi

if ! docker compose config --services | grep -qx "$APP_SERVICE"; then
    echo "Error: Docker Compose service '$APP_SERVICE' was not found."
    exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
    echo "Error: the repository has uncommitted local changes."
    echo "Commit or discard them before updating."
    exit 1
fi

echo "Project directory: $PROJECT_DIR"

echo "Fetching latest version tags..."
git fetch --tags origin

LATEST_TAG="$(git tag --sort=-v:refname | grep -E "$VERSION_TAG_PATTERN" | head -n 1 || true)"

if [ -z "$LATEST_TAG" ]; then
    echo "Error: no valid version tag found."
    echo "Expected tags like v0.1.0, v1.0.0, v1.2.3-beta or v1.2.3-rc.1."
    exit 1
fi

CURRENT_VERSION="$(grep '^APP_VERSION=' .env | cut -d '=' -f2- || true)"
CURRENT_VERSION="${CURRENT_VERSION:-dev}"

CURRENT_GIT_TAG="$(git describe --tags --exact-match 2>/dev/null || true)"
CURRENT_COMMIT="$(git rev-parse --short HEAD)"

echo "Installed version: $CURRENT_VERSION"
echo "Current Git tag: ${CURRENT_GIT_TAG:-none}"
echo "Current commit: $CURRENT_COMMIT"
echo "Latest version: $LATEST_TAG"

if [ "$CURRENT_VERSION" = "$LATEST_TAG" ] && [ "$CURRENT_GIT_TAG" = "$LATEST_TAG" ]; then
    echo "LineUp is already up to date."
    exit 0
fi

echo "Updating to $LATEST_TAG..."
git checkout "$LATEST_TAG"

echo "Setting APP_VERSION in .env..."
if grep -q '^APP_VERSION=' .env; then
    sed -i "s/^APP_VERSION=.*/APP_VERSION=$LATEST_TAG/" .env
else
    echo "APP_VERSION=$LATEST_TAG" >> .env
fi

echo "Building and starting containers..."
docker compose up -d --build

echo "Running database migrations..."
docker compose exec "$APP_SERVICE" php artisan migrate --force

echo "Refreshing Laravel cache..."
docker compose exec "$APP_SERVICE" php artisan optimize:clear
docker compose exec "$APP_SERVICE" php artisan config:cache
docker compose exec "$APP_SERVICE" php artisan route:cache
docker compose exec "$APP_SERVICE" php artisan view:cache

echo "Update completed."
echo "Installed version: $LATEST_TAG"