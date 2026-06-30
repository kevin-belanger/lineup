#!/usr/bin/env bash

set -euo pipefail

APP_SERVICE="app"
SCHEDULER_SERVICE="scheduler"
DB_SERVICE="mysql"
DEFAULT_TARGET_DIR="/opt/lineup"
RESTORE_TEMP_DIR=""
DOCKER_CMD="docker"
COMPOSE_CMD="docker compose"
NODE_IMAGE="node:22-alpine"

if [ "$(basename "${BASH_SOURCE[0]}")" = "restore-template.sh" ]; then
    echo "This file is a restore template and should not be run directly."
    echo "Run restore.sh from a LineUp backup directory instead."
    exit 1
fi

BACKUP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"

cleanup_restore_temp_dir() {
    if [ -n "$RESTORE_TEMP_DIR" ] && [ -d "$RESTORE_TEMP_DIR" ]; then
        rm -rf "$RESTORE_TEMP_DIR"
    fi
}

trap cleanup_restore_temp_dir EXIT

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Error: required command not found: $1"
        exit 1
    fi
}

require_root() {
    if [ "$EUID" -ne 0 ]; then
        echo "This restore script must be run as root. Use: sudo ./restore.sh"
        exit 1
    fi
}

require_ubuntu() {
    if [ ! -f /etc/os-release ]; then
        echo "Error: unable to detect operating system."
        exit 1
    fi

    # shellcheck disable=SC1091
    . /etc/os-release

    if [ "${ID:-}" != "ubuntu" ]; then
        echo "Error: this restore script currently supports Ubuntu only."
        echo "Detected OS: ${PRETTY_NAME:-unknown}"
        exit 1
    fi
}

install_base_packages() {
    echo
    echo "Installing or updating base packages..."

    apt update
    apt install -y git openssl ca-certificates curl iproute2
}

install_docker() {
    local conflicting_packages=(
        docker.io
        docker-compose
        docker-compose-v2
        docker-doc
        podman-docker
        containerd
        runc
    )
    local installed_conflicting_packages=()
    local package
    local docker_suite

    echo
    echo "Installing or updating Docker from the official Docker repository..."

    for package in "${conflicting_packages[@]}"; do
        if dpkg-query -W -f='${Status}' "$package" 2>/dev/null | grep -q "install ok installed"; then
            installed_conflicting_packages+=("$package")
        fi
    done

    if [ "${#installed_conflicting_packages[@]}" -gt 0 ]; then
        apt remove -y "${installed_conflicting_packages[@]}"
    else
        echo "No conflicting Docker packages are installed."
    fi

    install -m 0755 -d /etc/apt/keyrings

    curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
        -o /etc/apt/keyrings/docker.asc

    chmod a+r /etc/apt/keyrings/docker.asc

    # shellcheck disable=SC1091
    . /etc/os-release

    docker_suite="${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}"

    if [ -z "$docker_suite" ]; then
        echo "Error: unable to determine the Ubuntu release codename for Docker."
        exit 1
    fi

    tee /etc/apt/sources.list.d/docker.sources > /dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${docker_suite}
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

    apt update
    apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    echo
    echo "Installed Docker versions:"

    $DOCKER_CMD --version
    $COMPOSE_CMD version
}

require_docker_compose() {
    if ! $COMPOSE_CMD version >/dev/null 2>&1; then
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

require_metadata_value() {
    local name="$1"
    local value="${!name:-}"

    if [ -z "$value" ]; then
        echo "Error: backup metadata is missing required value: $name"
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
            $COMPOSE_CMD down -v
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

move_backup_outside_target_if_needed() {
    local backup_abs
    local target_abs

    if [ ! -d "$TARGET_DIR" ]; then
        return
    fi

    backup_abs="$(cd "$BACKUP_DIR" && pwd -P)"
    target_abs="$(cd "$TARGET_DIR" && pwd -P)"

    case "$backup_abs" in
        "$target_abs" | "$target_abs"/*)
            echo "Copying backup to a temporary restore location..."
            RESTORE_TEMP_DIR="$(mktemp -d)"
            cp -a "$BACKUP_DIR" "$RESTORE_TEMP_DIR/backup"
            BACKUP_DIR="$RESTORE_TEMP_DIR/backup"
            ;;
    esac
}

remove_target_volumes() {
    echo "Removing existing Docker volumes for target project..."

    $COMPOSE_CMD down -v --remove-orphans
}

wait_for_mysql() {
    local attempt

    echo "Waiting for MySQL..."

    for attempt in $(seq 1 60); do
        if $COMPOSE_CMD exec -T "$DB_SERVICE" sh -c 'mysql --protocol=tcp -h127.0.0.1 -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT 1;"' >/dev/null 2>&1; then
            return
        fi

        sleep 2
    done

    echo "Error: MySQL did not become ready in time."
    exit 1
}

prepare_database() {
    local escaped_table
    local drop_statements
    local table
    local table_type

    echo "Preparing database..."

    drop_statements="$(
        while IFS=$'\t' read -r table table_type; do
            if [ "$table_type" != "BASE TABLE" ]; then
                continue
            fi

            escaped_table="${table//\`/\`\`}"
            printf 'DROP TABLE IF EXISTS `%s`;\n' "$escaped_table"
        done < <(
            $COMPOSE_CMD exec -T "$DB_SERVICE" sh -c \
                'mysql --protocol=tcp -h127.0.0.1 -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -N -B "$MYSQL_DATABASE" -e "SHOW FULL TABLES;"'
        )
    )"

    if [ -n "$drop_statements" ]; then
        {
            echo "SET FOREIGN_KEY_CHECKS=0;"
            echo "$drop_statements"
            echo "SET FOREIGN_KEY_CHECKS=1;"
        } | $COMPOSE_CMD exec -T "$DB_SERVICE" sh -c 'mysql --protocol=tcp -h127.0.0.1 -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
    fi
}

import_database() {
    echo "Importing SQL dump..."

    $COMPOSE_CMD exec -T "$DB_SERVICE" sh -c \
        'mysql --protocol=tcp -h127.0.0.1 -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
        < "$BACKUP_DIR/database.sql"
}

restore_storage() {
    if [ ! -d "$BACKUP_DIR/files/storage" ]; then
        return
    fi

    local key_file

    echo "Restoring persistent Laravel storage files..."

    $COMPOSE_CMD up -d --build "$APP_SERVICE"

    $COMPOSE_CMD exec -T "$APP_SERVICE" sh -c 'mkdir -p /var/www/html/storage && rm -rf /var/www/html/storage/app && find /var/www/html/storage -maxdepth 1 -type f -name "*.key" -exec rm -f -- {} +'

    if [ -d "$BACKUP_DIR/files/storage/app" ]; then
        $COMPOSE_CMD cp "$BACKUP_DIR/files/storage/app" "$APP_SERVICE:/var/www/html/storage/"
    fi

    while IFS= read -r -d '' key_file; do
        $COMPOSE_CMD cp "$key_file" "$APP_SERVICE:/var/www/html/storage/"
    done < <(find "$BACKUP_DIR/files/storage" -maxdepth 1 -type f -name "*.key" -print0)

    $COMPOSE_CMD exec -T "$APP_SERVICE" sh -c 'chown -R www-data:www-data /var/www/html/storage/app 2>/dev/null || true; find /var/www/html/storage -maxdepth 1 -type f -name "*.key" -exec chown www-data:www-data {} +'
}

build_assets() {
    echo "Building frontend assets..."

    $DOCKER_CMD run --rm \
        --user "$(id -u):$(id -g)" \
        -e npm_config_cache=/tmp/.npm \
        -v "$TARGET_DIR:/app" \
        -w /app \
        "$NODE_IMAGE" \
        sh -lc "npm ci && npm run build"

    if [ ! -f "$TARGET_DIR/public/build/manifest.json" ]; then
        echo "Error: public/build/manifest.json was not created."
        exit 1
    fi
}

install_php_dependencies() {
    echo "Installing PHP dependencies..."

    $COMPOSE_CMD exec -T "$APP_SERVICE" sh -lc \
        'cd /var/www/html && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction'
}

prepare_writable_directories() {
    echo "Preparing writable Laravel directories..."

    $COMPOSE_CMD exec -T "$APP_SERVICE" sh -lc \
        'cd /var/www/html && mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache'
}

clear_laravel_cache() {
    echo "Clearing Laravel caches..."

    $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan config:clear
    $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan cache:clear
    $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan route:clear
    $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan view:clear
    $COMPOSE_CMD exec -T "$APP_SERVICE" php artisan storage:link
}

restart_application_services() {
    echo "Restarting application services..."

    $COMPOSE_CMD restart "$APP_SERVICE" "$SCHEDULER_SERVICE"
}

main() {
    local confirmation

    require_root
    require_ubuntu
    require_backup_file "$BACKUP_DIR/metadata.env"
    require_backup_file "$BACKUP_DIR/database.sql"
    require_backup_file "$BACKUP_DIR/files"

    # shellcheck disable=SC1091
    source "$BACKUP_DIR/metadata.env"

    require_metadata_value "REPO_URL"
    require_metadata_value "GIT_COMMIT"

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

    install_base_packages
    install_docker

    require_command git
    require_command "$DOCKER_CMD"
    require_docker_compose

    mkdir -p "$(dirname "$TARGET_DIR")"
    move_backup_outside_target_if_needed
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

        remove_target_volumes
        $COMPOSE_CMD up -d "$DB_SERVICE" redis
        wait_for_mysql
        prepare_database
        import_database
        restore_storage
        build_assets

        echo "Starting LineUp..."
        $COMPOSE_CMD up -d --build

        install_php_dependencies
        prepare_writable_directories
        clear_laravel_cache
        restart_application_services
    )

    echo
    echo "Restore completed."
    echo "Restored to: $TARGET_DIR"
}

main "$@"
