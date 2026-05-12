#!/usr/bin/env bash

set -euo pipefail

APP_SERVICE="app"
DB_SERVICE="mysql"

SQL_FILE="${1:-}"

echo "=== LineUp database restore ==="

if [ -z "$SQL_FILE" ]; then
    echo "Usage: ./restoredata.sh backup.sql"
    exit 1
fi

if [ ! -f "$SQL_FILE" ]; then
    echo "Error: SQL file not found: $SQL_FILE"
    exit 1
fi

if [ ! -f ".env" ]; then
    echo "Error: .env file not found."
    echo "Run this script from the project root."
    exit 1
fi

if ! docker compose config >/dev/null 2>&1; then
    echo "Error: Docker Compose configuration is invalid or unavailable."
    exit 1
fi

if ! docker compose config --services | grep -qx "$DB_SERVICE"; then
    echo "Error: Docker Compose service '$DB_SERVICE' was not found."
    exit 1
fi

if ! docker compose config --services | grep -qx "$APP_SERVICE"; then
    echo "Error: Docker Compose service '$APP_SERVICE' was not found."
    exit 1
fi

MYSQL_CONTAINER_ID="$(docker compose ps -q "$DB_SERVICE" || true)"

if [ -z "$MYSQL_CONTAINER_ID" ]; then
    echo "Error: MySQL container is not running."
    echo "Start the application first with: docker compose up -d"
    exit 1
fi

MYSQL_RUNNING_STATE="$(docker inspect -f '{{.State.Running}}' "$MYSQL_CONTAINER_ID" 2>/dev/null || echo false)"

if [ "$MYSQL_RUNNING_STATE" != "true" ]; then
    echo "Error: MySQL container exists, but it is not running."
    echo "Start the application first with: docker compose up -d"
    exit 1
fi

get_env_value() {
    local key="$1"

    grep -E "^${key}=" .env \
        | tail -n 1 \
        | cut -d '=' -f2- \
        | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

APP_VERSION="$(get_env_value "APP_VERSION" || true)"
APP_VERSION="${APP_VERSION:-dev}"

DB_DATABASE="$(get_env_value "DB_DATABASE" || true)"
DB_USERNAME="$(get_env_value "DB_USERNAME" || true)"
DB_PASSWORD="$(get_env_value "DB_PASSWORD" || true)"

if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
    echo "Error: DB_DATABASE, DB_USERNAME, or DB_PASSWORD is missing in .env."
    exit 1
fi

BACKUP_VERSION="$(grep -m 1 '^-- App version:' "$SQL_FILE" | sed 's/^-- App version:[[:space:]]*//' || true)"
BACKUP_VERSION="${BACKUP_VERSION:-unknown}"

if [ "$BACKUP_VERSION" = "unknown" ]; then
    echo "Error: backup version not found in SQL header."
    echo "Expected a line like:"
    echo "-- App version: v0.0.3"
    exit 1
fi

echo "Checking database connection..."

if ! docker compose exec -T "$DB_SERVICE" mysql \
    -u"$DB_USERNAME" \
    -p"$DB_PASSWORD" \
    "$DB_DATABASE" \
    -e "SELECT 1;" >/dev/null 2>&1; then
    echo "Error: unable to connect to the database using credentials from .env."
    echo "Check DB_DATABASE, DB_USERNAME, and DB_PASSWORD."
    exit 1
fi

echo
echo "Restore summary:"
echo "SQL file: $SQL_FILE"
echo "Database: $DB_DATABASE"
echo "Installed application version: $APP_VERSION"
echo "Backup version: $BACKUP_VERSION"
echo

echo "WARNING: This operation will replace the current application data."
echo "All current LineUp data in database '$DB_DATABASE' may be deleted and replaced by the SQL backup."
echo

read -r -p "Type RESTORE to continue: " CONFIRMATION

if [ "$CONFIRMATION" != "RESTORE" ]; then
    echo "Restore cancelled."
    exit 0
fi

if [ "$APP_VERSION" != "$BACKUP_VERSION" ]; then
    echo
    echo "WARNING: The backup version does not match the installed application version."
    echo
    echo "Installed version: $APP_VERSION"
    echo "Backup version: $BACKUP_VERSION"
    echo
    echo "It is recommended to install the matching LineUp version before restoring this backup."
    echo "Restoring a backup from another version may break the application or cause data inconsistencies."
    echo

    read -r -p "Type I UNDERSTAND to continue anyway: " VERSION_CONFIRMATION

    if [ "$VERSION_CONFIRMATION" != "I UNDERSTAND" ]; then
        echo "Restore cancelled."
        exit 0
    fi
fi

echo
echo "Importing SQL backup into MySQL..."

docker compose exec -T "$DB_SERVICE" mysql \
    -u"$DB_USERNAME" \
    -p"$DB_PASSWORD" \
    "$DB_DATABASE" < "$SQL_FILE"

echo "Running database migrations..."

docker compose exec "$APP_SERVICE" php artisan migrate --force

echo "Refreshing Laravel cache..."

docker compose exec "$APP_SERVICE" php artisan optimize:clear
docker compose exec "$APP_SERVICE" php artisan config:cache
docker compose exec "$APP_SERVICE" php artisan route:cache
docker compose exec "$APP_SERVICE" php artisan view:cache

echo
echo "Database restore completed."
echo "Restored backup version: $BACKUP_VERSION"