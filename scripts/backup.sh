#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd -P)"
cd "$APP_DIR"

COMPOSE_CMD=(docker compose --project-directory "$APP_DIR" -f "$APP_DIR/compose.yaml")
APP_SERVICE="app"
DB_SERVICE="mysql"
BACKUP_ROOT="backups"
BACKUP_FORMAT="1"

INCLUDE_PATHS=(
    ".env"
    "Caddyfile"
    "compose.override.yaml"
    "compose.override.yml"
    "Caddyfile.override"
    "Caddyfile.local"
)

EXCLUDE_PATHS=(
    ".git"
    "backups"
    "vendor"
    "node_modules"
    "public/build"
    "public/hot"
    "public/storage"
    "storage"
)

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

    if [ ! -f "scripts/restore-template.sh" ]; then
        echo "Error: scripts/restore-template.sh file not found."
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

shell_quote() {
    local value="$1"

    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    printf '"%s"' "$value"
}

get_repo_url() {
    local url

    url="$(git config --get remote.origin.url || true)"

    if [[ "$url" =~ ^git@github.com:(.+)$ ]]; then
        url="https://github.com/${BASH_REMATCH[1]}"
    fi

    printf '%s' "$url"
}

get_git_ref() {
    local ref

    ref="$(git describe --tags --exact-match 2>/dev/null || true)"

    if [ -z "$ref" ]; then
        ref="$(git symbolic-ref --short -q HEAD || true)"
    fi

    printf '%s' "$ref"
}

is_excluded_path() {
    local path="$1"
    local exclude

    path="${path#./}"
    path="${path%/}"

    for exclude in "${EXCLUDE_PATHS[@]}"; do
        exclude="${exclude%/}"

        if [ "$path" = "$exclude" ] || [[ "$path" == "$exclude/"* ]]; then
            return 0
        fi
    done

    return 1
}

is_safe_relative_path() {
    local path="$1"

    if [ -z "$path" ] || [[ "$path" = /* ]] || [[ "$path" == *".."* ]]; then
        return 1
    fi

    return 0
}

copy_repo_path() {
    local path="$1"
    local target

    path="${path#./}"

    if ! is_safe_relative_path "$path"; then
        return
    fi

    if is_excluded_path "$path"; then
        return
    fi

    if [ ! -e "$path" ] && [ ! -L "$path" ]; then
        return
    fi

    target="$FILES_DIR/$path"
    mkdir -p "$(dirname "$target")"
    cp -a "$path" "$target"
}

add_deleted_path() {
    local path="$1"

    path="${path#./}"

    if ! is_safe_relative_path "$path"; then
        return
    fi

    if is_excluded_path "$path"; then
        return
    fi

    printf '%s\n' "$path" >> "$DELETED_TRACKED_FILE.tmp"
}

copy_explicit_paths() {
    local path

    echo "Copying explicit files..."

    for path in "${INCLUDE_PATHS[@]}"; do
        copy_repo_path "$path"
    done
}

copy_git_local_state() {
    local entry
    local status
    local path
    local old_path

    echo "Copying local Git changes..."

    while IFS= read -r -d '' entry <&3; do
        status="${entry:0:2}"
        path="${entry:3}"

        if [[ "$status" == R* ]] || [[ "$status" == C* ]]; then
            if IFS= read -r -d '' old_path <&3; then
                if [[ "$status" == R* ]]; then
                    add_deleted_path "$old_path"
                fi
            fi
        fi

        if [ -e "$path" ] || [ -L "$path" ]; then
            copy_repo_path "$path"
        elif [[ "$status" == *D* ]]; then
            add_deleted_path "$path"
        fi
    done 3< <(git status --porcelain=v1 -z --untracked-files=all)
}

copy_untracked_files_ignored_by_git() {
    local path
    local tracked_path
    declare -A tracked_paths=()

    echo "Copying untracked files..."

    while IFS= read -r -d '' tracked_path; do
        tracked_paths["$tracked_path"]=1
    done < <(git ls-files -z)

    while IFS= read -r -d '' path; do
        path="${path#./}"

        if [ -z "${tracked_paths[$path]+x}" ]; then
            copy_repo_path "$path"
        fi
    done < <(
        find . \
            \( \
                -path './.git' \
                -o -path './backups' \
                -o -path './vendor' \
                -o -path './node_modules' \
                -o -path './public/build' \
                -o -path './public/hot' \
                -o -path './public/storage' \
                -o -path './storage' \
            \) -prune \
            -o \( -type f -o -type l \) -print0
    )
}

create_database_dump() {
    echo "Creating SQL dump..."

    "${COMPOSE_CMD[@]}" exec -T "$DB_SERVICE" sh -c \
        'mysqldump --protocol=tcp -h127.0.0.1 -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" --single-transaction --routines --triggers --default-character-set=utf8mb4 --add-drop-table --no-tablespaces "$MYSQL_DATABASE"' \
        > "$BACKUP_DIR/database.sql"
}

copy_storage_from_container() {
    local key_file

    echo "Copying persistent Docker storage files..."

    mkdir -p "$FILES_DIR/storage"

    if "${COMPOSE_CMD[@]}" exec -T "$APP_SERVICE" test -d /var/www/html/storage/app; then
        "${COMPOSE_CMD[@]}" cp "$APP_SERVICE:/var/www/html/storage/app" "$FILES_DIR/storage/"
    fi

    while IFS= read -r key_file; do
        "${COMPOSE_CMD[@]}" cp "$APP_SERVICE:/var/www/html/storage/$key_file" "$FILES_DIR/storage/"
    done < <(
        "${COMPOSE_CMD[@]}" exec -T "$APP_SERVICE" sh -c \
            'find /var/www/html/storage -maxdepth 1 -type f -name "*.key" -printf "%f\n"'
    )
}

create_metadata() {
    local repo_url
    local git_commit
    local git_ref
    local created_at

    repo_url="$(get_repo_url)"
    git_commit="$(git rev-parse HEAD)"
    git_ref="$(get_git_ref)"
    created_at="$(date -Iseconds)"

    {
        printf 'APP_NAME=%s\n' "$(shell_quote "LineUp")"
        printf 'BACKUP_FORMAT=%s\n' "$(shell_quote "$BACKUP_FORMAT")"
        printf 'CREATED_AT=%s\n' "$(shell_quote "$created_at")"
        printf 'APP_DIR=%s\n' "$(shell_quote "$APP_DIR")"
        printf 'REPO_URL=%s\n' "$(shell_quote "$repo_url")"
        printf 'GIT_COMMIT=%s\n' "$(shell_quote "$git_commit")"
        printf 'GIT_REF=%s\n' "$(shell_quote "$git_ref")"
    } > "$BACKUP_DIR/metadata.env"
}

create_manifest() {
    (
        cd "$BACKUP_DIR"
        find . -type f | sed 's#^\./##' | sort
    ) > "$BACKUP_DIR/manifest.txt"
}

main() {
    local timestamp

    echo_title "LineUp backup"

    require_command git
    require_command docker
    require_project_root
    require_docker_compose
    require_service "$APP_SERVICE"
    require_service "$DB_SERVICE"
    require_running_container "$APP_SERVICE"
    require_running_container "$DB_SERVICE"

    timestamp="$(date +%Y%m%d-%H%M%S)"
    BACKUP_DIR="$BACKUP_ROOT/lineup-backup-$timestamp"
    FILES_DIR="$BACKUP_DIR/files"
    DELETED_TRACKED_FILE="$BACKUP_DIR/deleted-tracked-files.txt"

    mkdir -p "$FILES_DIR"
    : > "$DELETED_TRACKED_FILE.tmp"

    create_database_dump
    create_metadata
    copy_explicit_paths
    copy_git_local_state
    copy_untracked_files_ignored_by_git
    copy_storage_from_container

    sort -u "$DELETED_TRACKED_FILE.tmp" > "$DELETED_TRACKED_FILE"
    rm -f "$DELETED_TRACKED_FILE.tmp"

    cp "scripts/restore-template.sh" "$BACKUP_DIR/restore.sh"
    chmod +x "$BACKUP_DIR/restore.sh"
    create_manifest

    echo
    echo "Backup completed."
    echo "$BACKUP_DIR"
}

main "$@"
