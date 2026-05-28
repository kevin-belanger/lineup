#!/usr/bin/env bash

set -euo pipefail

APP_SERVICE="app"
DB_SERVICE="mysql"
DEFAULT_TARGET_DIR="/opt/lineup"

if [ "$(basename "${BASH_SOURCE[0]}")" = "restore-template.sh" ]; then
    echo "This file is a restore template and should not be run directly."
    echo "Run restore.sh from a LineUp backup directory instead."
    exit 1
fi

BACKUP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Error: required command not found: $1"
        exit 1
    fi
}

require_docker_compose() {
    if ! docker compose version >/dev/null 2>&1; then
        echo "Error: Docker Compose is not available."
        exit 1
    fi
}

require_backup_file() {
    if [ ! -e "$1" ]; then
        echo "Error: required backup path not found: $1"
        exit 1
    fi
}

is_safe_relative_path() {
    local path="$1"

    if [ -z "$path" ] || [[ "$path" = /* ]] || [[ "$path" == *".."* ]]; then
        return 1
    fi

    return 0
}

move_existing_target() {
    local backup_target
    local timestamp
    local suffix

    if [ ! -e "$TARGET_DIR" ]; then
        return
    fi

    if [ -f "$TARGET_DIR/compose.yaml" ]; then
        echo "Stopping existing Docker Compose application..."
        (
            cd "$TARGET_DIR"
            docker compose down -v
        )
    fi

    timestamp="$(date +%Y%m%d-%H%M%S)"
    backup_target="$TARGET_DIR.before-restore-$timestamp"
    suffix=1

    while [ -e "$backup_target" ]; do
        backup_target="$TARGET_DIR.before-restore-$timestamp-$suffix"
        suffix=$((suffix + 1))
    done

    echo "Moving existing target to: $backup_target"
    mv "$TARGET_DIR" "$backup_target"
}

copy_regular_files() {
    echo "Copying restored files..."

    if [ ! -d "$BACKUP_DIR/files" ]; then
        return
    fi

    find "$BACKUP_DIR/files" -mindepth 1 -maxdepth 1 ! -name storage -exec cp -a {} "$TARGET_DIR/" \;
}

apply_deleted_tracked_files() {
    local path

    if [ ! -f "$BACKUP_DIR/deleted-tracked-files.txt" ]; then
        return
    fi

    echo "Applying deleted tracked files..."

    while IFS= read -r path || [ -n "$path" ]; do
        if ! is_safe_relative_path "$path"; then
            continue
        fi

        rm -rf "$TARGET_DIR/$path"
    done < "$BACKUP_DIR/deleted-tracked-files.txt"
}

wait_for_mysql() {
    local attempt

    echo "Waiting for MySQL..."

    for attempt in $(seq 1 60); do
        if docker compose exec -T "$DB_SERVICE" sh -c 'mysqladmin ping -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" --silent' >/dev/null 2>&1; then
            return
        fi

        sleep 2
    done

    echo "Error: MySQL did not become ready in time."
    exit 1
}

prepare_database() {
    local drop_statements

    echo "Preparing database..."

    docker compose exec -T "$DB_SERVICE" sh -c \
        'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'

    drop_statements="$(docker compose exec -T "$DB_SERVICE" sh -c \
        'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -N -B "$MYSQL_DATABASE" -e "SELECT CONCAT('\''DROP TABLE IF EXISTS `'\'' , REPLACE(TABLE_NAME, '\''`'\'', '\''``'\''), '\''`;'\'' ) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = '\''BASE TABLE'\'';"')"

    if [ -n "$drop_statements" ]; then
        {
            echo "SET FOREIGN_KEY_CHECKS=0;"
            echo "$drop_statements"
            echo "SET FOREIGN_KEY_CHECKS=1;"
        } | docker compose exec -T "$DB_SERVICE" sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
    fi
}

import_database() {
    echo "Importing SQL dump..."

    docker compose exec -T "$DB_SERVICE" sh -c \
        'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
        < "$BACKUP_DIR/database.sql"
}

restore_storage() {
    if [ ! -d "$BACKUP_DIR/files/storage" ]; then
        return
    fi

    echo "Restoring Docker storage volume..."

    docker compose up -d --build "$APP_SERVICE"

    docker compose exec -T "$APP_SERVICE" sh -c 'mkdir -p /var/www/html/storage && find /var/www/html/storage -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +'
    docker compose cp "$BACKUP_DIR/files/storage/." "$APP_SERVICE:/var/www/html/storage"
    docker compose exec -T "$APP_SERVICE" chown -R www-data:www-data /var/www/html/storage
}

clear_laravel_cache() {
    echo "Clearing Laravel caches..."

    docker compose exec -T "$APP_SERVICE" php artisan config:clear
    docker compose exec -T "$APP_SERVICE" php artisan cache:clear
    docker compose exec -T "$APP_SERVICE" php artisan route:clear
    docker compose exec -T "$APP_SERVICE" php artisan view:clear
    docker compose exec -T "$APP_SERVICE" php artisan storage:link
}

main() {
    local confirmation

    require_command git
    require_command docker
    require_docker_compose
    require_backup_file "$BACKUP_DIR/metadata.env"
    require_backup_file "$BACKUP_DIR/database.sql"
    require_backup_file "$BACKUP_DIR/files"

    # shellcheck disable=SC1091
    source "$BACKUP_DIR/metadata.env"

    TARGET_DIR="${1:-${APP_DIR:-$DEFAULT_TARGET_DIR}}"

    echo
    echo "LineUp restore"
    echo
    echo "Backup: $BACKUP_DIR"
    echo "Target: $TARGET_DIR"
    echo
    echo "This will replace the LineUp application at the target path."
    read -r -p "Type RESTORE to continue: " confirmation

    if [ "$confirmation" != "RESTORE" ]; then
        echo "Restore cancelled."
        exit 0
    fi

    mkdir -p "$(dirname "$TARGET_DIR")"
    move_existing_target

    echo "Cloning repository..."
    git clone "$REPO_URL" "$TARGET_DIR"

    (
        cd "$TARGET_DIR"

        echo "Checking out commit: $GIT_COMMIT"
        git checkout "$GIT_COMMIT"
    )

    copy_regular_files
    apply_deleted_tracked_files

    (
        cd "$TARGET_DIR"

        docker compose up -d "$DB_SERVICE" redis
        wait_for_mysql
        prepare_database
        import_database
        restore_storage

        echo "Starting LineUp..."
        docker compose up -d --build

        clear_laravel_cache
    )

    echo
    echo "Restore completed."
    echo "Restored to: $TARGET_DIR"
}

main "$@"
