#!/usr/bin/env bash

set -euo pipefail

APP_SERVICE="app"
DB_SERVICE="mysql"
BACKUP_DIR="backups"

EXCLUDED_DATA_TABLES=(
    "cache"
    "cache_locks"
    "sessions"
    "jobs"
    "job_batches"
    "failed_jobs"
)

echo_title() {
    echo
    echo "=== $1 ==="
}

usage() {
    cat <<EOF
Usage:
  ./backup.sh database
  ./backup.sh list
  ./backup.sh restore
  ./backup.sh restore backups/backup-file.sql

Commands:
  database    Create a SQL backup in the backups/ directory.
  list        List available SQL backups.
  restore     Restore a SQL backup. If no file is provided, a menu is shown.

EOF
}

get_env_value() {
    local key="$1"

    grep -E "^${key}=" .env \
        | tail -n 1 \
        | cut -d '=' -f2- \
        | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

require_project_root() {
    if [ ! -f ".env" ]; then
        echo "Error: .env file not found."
        echo "Run this script from the project root."
        exit 1
    fi

    if ! docker compose config >/dev/null 2>&1; then
        echo "Error: Docker Compose configuration is invalid or unavailable."
        exit 1
    fi
}

require_services() {
    if ! docker compose config --services | grep -qx "$DB_SERVICE"; then
        echo "Error: Docker Compose service '$DB_SERVICE' was not found."
        exit 1
    fi

    if ! docker compose config --services | grep -qx "$APP_SERVICE"; then
        echo "Error: Docker Compose service '$APP_SERVICE' was not found."
        exit 1
    fi
}

require_mysql_running() {
    local container_id
    local running_state

    container_id="$(docker compose ps -q "$DB_SERVICE" || true)"

    if [ -z "$container_id" ]; then
        echo "Error: MySQL container is not running."
        echo "Start the application first with: docker compose up -d"
        exit 1
    fi

    running_state="$(docker inspect -f '{{.State.Running}}' "$container_id" 2>/dev/null || echo false)"

    if [ "$running_state" != "true" ]; then
        echo "Error: MySQL container exists, but it is not running."
        echo "Start the application first with: docker compose up -d"
        exit 1
    fi
}

load_config() {
    APP_NAME_VALUE="$(get_env_value "APP_NAME" || true)"
    APP_NAME_VALUE="${APP_NAME_VALUE:-LineUp}"

    APP_VERSION="$(get_env_value "APP_VERSION" || true)"
    APP_VERSION="${APP_VERSION:-dev}"

    APP_REPOSITORY_URL="$(get_env_value "APP_REPOSITORY_URL" || true)"
    APP_REPOSITORY_URL="${APP_REPOSITORY_URL:-}"

    APP_TIMEZONE="$(get_env_value "APP_TIMEZONE" || true)"
    APP_TIMEZONE="${APP_TIMEZONE:-UTC}"

    DB_DATABASE="$(get_env_value "DB_DATABASE" || true)"
    DB_USERNAME="$(get_env_value "DB_USERNAME" || true)"
    DB_PASSWORD="$(get_env_value "DB_PASSWORD" || true)"

    if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
        echo "Error: DB_DATABASE, DB_USERNAME, or DB_PASSWORD is missing in .env."
        exit 1
    fi
}

test_mysql_connection() {
    if ! docker compose exec -T "$DB_SERVICE" mysql \
        -u"$DB_USERNAME" \
        -p"$DB_PASSWORD" \
        "$DB_DATABASE" \
        -e "SELECT 1;" >/dev/null 2>&1; then
        echo "Error: unable to connect to the database using credentials from .env."
        echo "Check DB_DATABASE, DB_USERNAME, and DB_PASSWORD."
        exit 1
    fi
}

table_exists() {
    local table="$1"

    docker compose exec -T "$DB_SERVICE" mysql \
        -u"$DB_USERNAME" \
        -p"$DB_PASSWORD" \
        "$DB_DATABASE" \
        -N -e "SHOW TABLES LIKE '$table';" 2>/dev/null | grep -qx "$table"
}

sanitize_filename_part() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9._-]/-/g' | sed 's/-\+/-/g'
}

create_database_backup() {
    echo_title "LineUp database backup"

    require_project_root
    require_services
    require_mysql_running
    load_config
    test_mysql_connection

    mkdir -p "$BACKUP_DIR"

    local timestamp
    local app_name_slug
    local version_slug
    local backup_file
    local temp_dump
    local ignore_args=()
    local existing_runtime_tables=()

    timestamp="$(date +%Y%m%d-%H%M%S)"
    app_name_slug="$(sanitize_filename_part "$APP_NAME_VALUE")"
    version_slug="$(sanitize_filename_part "$APP_VERSION")"

    backup_file="$BACKUP_DIR/${app_name_slug}-database-backup-${version_slug}-${timestamp}.sql"
    temp_dump="$(mktemp)"

    for table in "${EXCLUDED_DATA_TABLES[@]}"; do
        if table_exists "$table"; then
            existing_runtime_tables+=("$table")
            ignore_args+=("--ignore-table=${DB_DATABASE}.${table}")
        fi
    done

    echo "Creating database backup..."
    echo "Database: $DB_DATABASE"
    echo "Application version: $APP_VERSION"
    echo "Output: $backup_file"

    {
        echo "-- LineUp database backup"
        echo "-- Application: $APP_NAME_VALUE"
        echo "-- App version: $APP_VERSION"
        if [ -n "$APP_REPOSITORY_URL" ]; then
            echo "-- Repository: $APP_REPOSITORY_URL"
        else
            echo "-- Repository:"
        fi
        echo "-- Generated at: $(date '+%Y-%m-%d %H:%M:%S') $APP_TIMEZONE"
        echo "-- Database: $DB_DATABASE"
        echo
        echo "SET FOREIGN_KEY_CHECKS=0;"
        echo
    } > "$backup_file"

    if [ "${#existing_runtime_tables[@]}" -gt 0 ]; then
        {
            echo "-- Runtime table structures"
            echo "-- Rows for these tables are intentionally excluded from this backup."
            echo
        } >> "$backup_file"

        docker compose exec -T "$DB_SERVICE" mysqldump \
            -u"$DB_USERNAME" \
            -p"$DB_PASSWORD" \
            --single-transaction \
            --default-character-set=utf8mb4 \
            --add-drop-table \
            --no-tablespaces \
            --no-data \
            "$DB_DATABASE" \
            "${existing_runtime_tables[@]}" > "$temp_dump"

        cat "$temp_dump" >> "$backup_file"

        {
            echo
            for table in "${existing_runtime_tables[@]}"; do
                echo "-- Runtime data for table \`$table\` was excluded from this backup."
            done
            echo
        } >> "$backup_file"
    fi

    docker compose exec -T "$DB_SERVICE" mysqldump \
        -u"$DB_USERNAME" \
        -p"$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        --default-character-set=utf8mb4 \
        --add-drop-table \
        --no-tablespaces \
        "${ignore_args[@]}" \
        "$DB_DATABASE" > "$temp_dump"

    cat "$temp_dump" >> "$backup_file"
    rm -f "$temp_dump"

    {
        echo
        echo "SET FOREIGN_KEY_CHECKS=1;"
    } >> "$backup_file"

    echo
    echo "Backup completed."
    echo "$backup_file"
}

list_backups() {
    echo_title "Available backups"

    if [ ! -d "$BACKUP_DIR" ]; then
        echo "No backup directory found."
        return
    fi

    mapfile -t backups < <(find "$BACKUP_DIR" -maxdepth 1 -type f -name "*.sql" | sort -r)

    if [ "${#backups[@]}" -eq 0 ]; then
        echo "No SQL backup found in $BACKUP_DIR/."
        return
    fi

    local index=1
    for file in "${backups[@]}"; do
        echo "$index) $file"
        index=$((index + 1))
    done
}

select_backup_from_menu() {
    if [ ! -d "$BACKUP_DIR" ]; then
        echo "Error: no backup directory found."
        exit 1
    fi

    mapfile -t backups < <(find "$BACKUP_DIR" -maxdepth 1 -type f -name "*.sql" | sort -r)

    if [ "${#backups[@]}" -eq 0 ]; then
        echo "Error: no SQL backup found in $BACKUP_DIR/."
        exit 1
    fi

    echo "Available backups:"
    echo

    local index=1
    for file in "${backups[@]}"; do
        echo "$index) $file"
        index=$((index + 1))
    done

    echo
    read -r -p "Choose a backup number: " choice

    if ! [[ "$choice" =~ ^[0-9]+$ ]]; then
        echo "Error: invalid selection."
        exit 1
    fi

    if [ "$choice" -lt 1 ] || [ "$choice" -gt "${#backups[@]}" ]; then
        echo "Error: selection out of range."
        exit 1
    fi

    SELECTED_BACKUP="${backups[$((choice - 1))]}"
}

restore_database_backup() {
    local sql_file="${1:-}"

    echo_title "LineUp database restore"

    require_project_root
    require_services
    require_mysql_running
    load_config
    test_mysql_connection

    if [ -z "$sql_file" ]; then
        select_backup_from_menu
        sql_file="$SELECTED_BACKUP"
    fi

    if [ ! -f "$sql_file" ]; then
        echo "Error: SQL file not found: $sql_file"
        exit 1
    fi

    local backup_version
    backup_version="$(grep -m 1 '^-- App version:' "$sql_file" | sed 's/^-- App version:[[:space:]]*//' || true)"
    backup_version="${backup_version:-unknown}"

    if [ "$backup_version" = "unknown" ]; then
        echo "Error: backup version not found in SQL header."
        echo "Expected a line like:"
        echo "-- App version: v0.0.5"
        exit 1
    fi

    echo
    echo "Restore summary:"
    echo "SQL file: $sql_file"
    echo "Database: $DB_DATABASE"
    echo "Installed application version: $APP_VERSION"
    echo "Backup version: $backup_version"
    echo

    echo "WARNING: This operation will replace the current application data."
    echo "All current LineUp data in database '$DB_DATABASE' may be deleted and replaced by the SQL backup."
    echo

    read -r -p "Type RESTORE to continue: " confirmation

    if [ "$confirmation" != "RESTORE" ]; then
        echo "Restore cancelled."
        exit 0
    fi

    if [ "$APP_VERSION" != "$backup_version" ]; then
        echo
        echo "WARNING: The backup version does not match the installed application version."
        echo
        echo "Installed version: $APP_VERSION"
        echo "Backup version: $backup_version"
        echo
        echo "It is recommended to install the matching LineUp version before restoring this backup."
        echo "Restoring a backup from another version may break the application or cause data inconsistencies."
        echo

        read -r -p "Type I UNDERSTAND to continue anyway: " version_confirmation

        if [ "$version_confirmation" != "I UNDERSTAND" ]; then
            echo "Restore cancelled."
            exit 0
        fi
    fi

    echo
    echo "Importing SQL backup into MySQL..."

    docker compose exec -T "$DB_SERVICE" mysql \
        -u"$DB_USERNAME" \
        -p"$DB_PASSWORD" \
        "$DB_DATABASE" < "$sql_file"

    echo "Running database migrations..."

    docker compose exec "$APP_SERVICE" php artisan migrate --force

    echo "Refreshing Laravel cache..."

    docker compose exec "$APP_SERVICE" php artisan optimize:clear
    docker compose exec "$APP_SERVICE" php artisan config:cache
    docker compose exec "$APP_SERVICE" php artisan route:cache
    docker compose exec "$APP_SERVICE" php artisan view:cache

    echo
    echo "Database restore completed."
    echo "Restored backup version: $backup_version"
}

COMMAND="${1:-}"

case "$COMMAND" in
    database)
        create_database_backup
        ;;
    list)
        list_backups
        ;;
    restore)
        restore_database_backup "${2:-}"
        ;;
    ""|-h|--help|help)
        usage
        ;;
    *)
        echo "Error: unknown command: $COMMAND"
        usage
        exit 1
        ;;
esac
