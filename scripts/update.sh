#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_DIR"

APP_SERVICE="app"
VERSION_TAG_PATTERN='^v[0-9]+\.[0-9]+\.[0-9]+.*$'
GITHUB_LATEST_RELEASE_URL="https://api.github.com/repos/kevin-belanger/lineup/releases/latest"

echo "=== LineUp update ==="

usage() {
    echo "Usage:"
    echo "  ./update.sh"
    echo "  ./update.sh <branch>"
    echo "  ./update.sh <branch> <commit>"
}

if [ ! -d ".git" ]; then
    echo "Error: this script must be located in the scripts directory of a Git repository."
    exit 1
fi

if [ ! -f ".env" ]; then
    echo "Error: .env file not found in project root: $PROJECT_DIR"
    exit 1
fi

echo "Project directory: $PROJECT_DIR"

if [ "$#" -gt 2 ]; then
    echo "Error: too many arguments."
    usage
    exit 1
fi

fetch_latest_release_tag() {
    local body
    local http_status
    local response
    local release_tag

    if ! response="$(curl -sSL \
        -H "Accept: application/vnd.github+json" \
        -H "User-Agent: LineUp-updater" \
        -w '\n%{http_code}' \
        "$GITHUB_LATEST_RELEASE_URL")"; then
        echo "Error: unable to retrieve the latest published GitHub Release from GitHub." >&2
        exit 1
    fi

    http_status="${response##*$'\n'}"
    body="${response%$'\n'*}"

    if [ "$http_status" = "404" ]; then
        echo "Error: no published GitHub Release was found for LineUp." >&2
        exit 1
    fi

    if [ "$http_status" != "200" ]; then
        echo "Error: unable to retrieve the latest published GitHub Release from GitHub. HTTP status: $http_status" >&2
        exit 1
    fi

    release_tag="$(printf '%s\n' "$body" | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n 1)"

    if [ -z "$release_tag" ]; then
        echo "Error: no published GitHub Release was found for LineUp." >&2
        exit 1
    fi

    if ! printf '%s\n' "$release_tag" | grep -Eq "$VERSION_TAG_PATTERN"; then
        echo "Error: latest published GitHub Release has an invalid tag: $release_tag" >&2
        echo "Expected tags like v0.1.0, v1.0.0, v1.2.3-beta or v1.2.3-rc.1." >&2
        exit 1
    fi

    printf '%s' "$release_tag"
}

require_clean_worktree() {
    if [ -n "$(git status --porcelain)" ]; then
        echo "Error: the repository has uncommitted local changes."
        echo "Commit or discard them before updating."
        exit 1
    fi
}

require_docker_compose_service() {
    if ! docker compose config --services | grep -qx "$APP_SERVICE"; then
        echo "Error: Docker Compose service '$APP_SERVICE' was not found."
        exit 1
    fi
}

escape_sed_replacement() {
    printf '%s' "$1" | sed -e 's/[\/&]/\\&/g'
}

set_app_version() {
    local version="$1"
    local escaped_version

    escaped_version="$(escape_sed_replacement "$version")"

    echo "Setting APP_VERSION in .env..."
    if grep -q '^APP_VERSION=' .env; then
        sed -i "s/^APP_VERSION=.*/APP_VERSION=$escaped_version/" .env
    else
        echo "APP_VERSION=$version" >> .env
    fi
}

run_application_update() {
    require_docker_compose_service

    echo "Building and recreating containers..."
    docker compose up -d --build --force-recreate

    echo "Running database migrations..."
    docker compose exec -T "$APP_SERVICE" php artisan migrate --force

    echo "Refreshing Laravel cache..."
    docker compose exec -T "$APP_SERVICE" php artisan optimize:clear
    docker compose exec -T "$APP_SERVICE" php artisan config:cache
    docker compose exec -T "$APP_SERVICE" php artisan route:cache
    docker compose exec -T "$APP_SERVICE" php artisan view:cache
}

run_stable_update() {
    local latest_tag

    if ! command -v curl >/dev/null 2>&1; then
        echo "Error: required command not found: curl"
        exit 1
    fi

    require_clean_worktree

    echo "Retrieving latest published GitHub Release..."
    latest_tag="$(fetch_latest_release_tag)"

    echo "Fetching Git tags..."
    git fetch --tags origin

    if ! git rev-parse -q --verify "refs/tags/$latest_tag" >/dev/null; then
        echo "Error: published GitHub Release tag was not found in Git: $latest_tag"
        exit 1
    fi

    CURRENT_VERSION="$(grep '^APP_VERSION=' .env | cut -d '=' -f2- || true)"
    CURRENT_VERSION="${CURRENT_VERSION:-dev}"

    CURRENT_GIT_TAG="$(git describe --tags --exact-match 2>/dev/null || true)"
    CURRENT_COMMIT="$(git rev-parse --short HEAD)"

    echo "Installed version: $CURRENT_VERSION"
    echo "Current Git tag: ${CURRENT_GIT_TAG:-none}"
    echo "Current commit: $CURRENT_COMMIT"
    echo "Latest published release: $latest_tag"

    if [ "$CURRENT_VERSION" = "$latest_tag" ] && [ "$CURRENT_GIT_TAG" = "$latest_tag" ]; then
        echo "LineUp is already up to date."
        exit 0
    fi

    echo "Updating to $latest_tag..."
    git checkout "$latest_tag"

    set_app_version "$latest_tag"
    run_application_update

    echo "Update completed."
    echo "Installed version: $latest_tag"
}

remote_branch_exists_exactly() {
    local branch="$1"

    git ls-remote --exit-code --heads origin "refs/heads/$branch" >/dev/null 2>&1
}

run_branch_update() {
    local branch="$1"
    local requested_commit="${2:-}"
    local remote_ref
    local commit_sha
    local installed_commit
    local installed_version

    if [ -z "$branch" ]; then
        echo "Error: branch name is required."
        usage
        exit 1
    fi

    echo
    echo "Warning: branch update mode is not a stable release."
    echo "The installed version will come directly from a Git branch."
    echo "Recommended stable mode: ./update.sh"
    echo

    echo "Checking remote branch: $branch"
    if ! remote_branch_exists_exactly "$branch"; then
        echo "Error: remote branch not found on origin: $branch"
        echo "Branch names are case-sensitive. Use the exact branch name from origin."
        usage
        exit 1
    fi

    remote_ref="refs/remotes/origin/$branch"

    echo "Fetching branch from origin..."
    git fetch origin "+refs/heads/$branch:$remote_ref"

    require_clean_worktree

    if [ -n "$requested_commit" ]; then
        echo "Checking commit: $requested_commit"

        if ! commit_sha="$(git rev-parse --verify "$requested_commit^{commit}" 2>/dev/null)"; then
            echo "Error: commit not found: $requested_commit"
            usage
            exit 1
        fi

        if ! git merge-base --is-ancestor "$commit_sha" "$remote_ref"; then
            echo "Error: commit $requested_commit does not belong to origin/$branch."
            usage
            exit 1
        fi

        echo "Updating to commit $commit_sha from origin/$branch..."
        git checkout --detach "$commit_sha"
    else
        echo "Updating to latest commit from origin/$branch..."
        git checkout --detach "$remote_ref"
    fi

    installed_commit="$(git rev-parse --short HEAD)"
    installed_version="$branch $installed_commit"

    set_app_version "$installed_version"
    run_application_update

    echo "Update completed."
    echo "Installed version: $installed_version"
}

if [ "$#" -eq 0 ]; then
    run_stable_update
else
    run_branch_update "$@"
fi
