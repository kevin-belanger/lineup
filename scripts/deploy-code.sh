#!/usr/bin/env bash

set -euo pipefail

DEFAULT_PROJECT_DIR="/opt/lineup"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ -n "${LINEUP_DIR:-}" ]; then
    PROJECT_DIR="$(cd "$LINEUP_DIR" && pwd -P)"
elif [ -f "$SCRIPT_DIR/../compose.yaml" ] && [ -d "$SCRIPT_DIR/../.git" ]; then
    PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd -P)"
else
    PROJECT_DIR="$DEFAULT_PROJECT_DIR"
fi

cd "$PROJECT_DIR"

COMPOSE_CMD=(docker compose --project-directory "$PROJECT_DIR" -f "$PROJECT_DIR/compose.yaml")
APP_SERVICE="app"
SCHEDULER_SERVICE="scheduler"
NODE_IMAGE="node:22-alpine"

echo "=== LineUp code deploy ==="

usage() {
    echo "Usage:"
    echo "  ./scripts/deploy-code.sh"
    echo "  ./scripts/deploy-code.sh <branch>"
    echo
    echo "Environment options:"
    echo "  LINEUP_DIR=/path  Project directory. Defaults to /opt/lineup when the script is not inside the project."
    echo "  SKIP_PULL=1      Do not pull code before deploying."
    echo "  SKIP_ASSETS=1    Do not rebuild public/build."
    echo "  SKIP_COMPOSER=1  Do not run composer install in the app container."
    echo "  USE_HOST_NPM=1   Build assets with host npm instead of a temporary Node container."
}

echo_title() {
    echo
    echo "=== $1 ==="
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Error: required command not found: $1"
        exit 1
    fi
}

require_project_root() {
    if [ ! -d ".git" ]; then
        echo "Error: this script must run from a LineUp Git repository."
        exit 1
    fi

    if [ ! -f ".env" ]; then
        echo "Error: .env file not found."
        exit 1
    fi

    if [ ! -f "compose.yaml" ]; then
        echo "Error: compose.yaml file not found."
        exit 1
    fi
}

require_clean_worktree() {
    if [ -n "$(git status --porcelain)" ]; then
        echo "Error: the repository has uncommitted local changes."
        echo "Commit, stash, or discard them before deploying."
        exit 1
    fi
}

require_docker_compose() {
    if ! "${COMPOSE_CMD[@]}" version >/dev/null 2>&1; then
        echo "Error: Docker Compose is not available."
        exit 1
    fi

    if ! "${COMPOSE_CMD[@]}" config >/dev/null 2>&1; then
        echo "Error: Docker Compose configuration is invalid or unavailable."
        exit 1
    fi
}

require_service() {
    local service="$1"

    if ! "${COMPOSE_CMD[@]}" config --services | grep -qx "$service"; then
        echo "Error: Docker Compose service '$service' was not found."
        exit 1
    fi
}

require_running_container() {
    local service="$1"
    local container_id
    local running_state

    container_id="$("${COMPOSE_CMD[@]}" ps -q "$service" || true)"

    if [ -z "$container_id" ]; then
        echo "Error: Docker Compose service '$service' is not running."
        echo "Start LineUp first with: docker compose up -d"
        exit 1
    fi

    running_state="$(docker inspect -f '{{.State.Running}}' "$container_id" 2>/dev/null || echo false)"

    if [ "$running_state" != "true" ]; then
        echo "Error: Docker Compose service '$service' exists, but is not running."
        echo "Start LineUp first with: docker compose up -d"
        exit 1
    fi
}

pull_code() {
    local branch="${1:-}"

    if [ "${SKIP_PULL:-0}" = "1" ]; then
        echo_title "Skipping Git pull"
        return
    fi

    echo_title "Updating Git checkout"
    require_clean_worktree

    if [ -n "$branch" ]; then
        git fetch origin "$branch"
        git checkout "$branch"
    else
        branch="$(git branch --show-current)"
    fi

    if [ -z "$branch" ]; then
        echo "Error: unable to determine the current Git branch."
        echo "Pass the branch explicitly: ./scripts/deploy-code.sh main"
        exit 1
    fi

    git pull --ff-only origin "$branch"
    require_clean_worktree
}

build_assets() {
    if [ "${SKIP_ASSETS:-0}" = "1" ]; then
        echo_title "Skipping asset build"
        return
    fi

    echo_title "Building frontend assets"

    if [ "${USE_HOST_NPM:-0}" = "1" ]; then
        if ! command -v npm >/dev/null 2>&1; then
            echo "Error: USE_HOST_NPM=1 was set, but npm was not found on the host."
            exit 1
        fi

        npm ci
        npm run build
    else
        echo "Building assets with $NODE_IMAGE..."
        docker run --rm \
            --user "$(id -u):$(id -g)" \
            -e npm_config_cache=/tmp/.npm \
            -v "$PROJECT_DIR:/app" \
            -w /app \
            "$NODE_IMAGE" \
            sh -lc "npm ci && npm run build"
    fi

    if [ ! -f "public/build/manifest.json" ]; then
        echo "Error: public/build/manifest.json was not created."
        exit 1
    fi
}

install_php_dependencies() {
    if [ "${SKIP_COMPOSER:-0}" = "1" ]; then
        echo "Skipping composer install."
        return
    fi

    echo_title "Refreshing PHP dependencies"
    "${COMPOSE_CMD[@]}" exec -T "$APP_SERVICE" sh -lc \
        'cd /var/www/html && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction'
}

prepare_writable_directories() {
    echo_title "Preparing writable Laravel directories"
    "${COMPOSE_CMD[@]}" exec -T "$APP_SERVICE" sh -lc \
        'cd /var/www/html && mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache'
}

refresh_app_cache() {
    echo_title "Refreshing Laravel caches"
    "${COMPOSE_CMD[@]}" exec -T "$APP_SERVICE" php artisan optimize:clear
    "${COMPOSE_CMD[@]}" exec -T "$APP_SERVICE" php artisan config:cache
    "${COMPOSE_CMD[@]}" exec -T "$APP_SERVICE" php artisan route:cache
    "${COMPOSE_CMD[@]}" exec -T "$APP_SERVICE" php artisan view:cache
}

restart_services() {
    echo_title "Restarting application services"
    "${COMPOSE_CMD[@]}" restart "$APP_SERVICE" "$SCHEDULER_SERVICE"
}

if [ "$#" -gt 1 ]; then
    echo "Error: too many arguments."
    usage
    exit 1
fi

require_command git
require_command docker
require_project_root
require_docker_compose
require_service "$APP_SERVICE"
require_service "$SCHEDULER_SERVICE"
require_running_container "$APP_SERVICE"
require_running_container "$SCHEDULER_SERVICE"

echo "Project directory: $PROJECT_DIR"

pull_code "${1:-}"
build_assets
install_php_dependencies
prepare_writable_directories
refresh_app_cache
restart_services

echo
echo "Code deploy completed."
