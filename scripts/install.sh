#!/usr/bin/env bash

set -euo pipefail

REPOSITORY_URL="https://github.com/kevin-belanger/lineup.git"
GITHUB_LATEST_RELEASE_URL="https://api.github.com/repos/kevin-belanger/lineup/releases/latest"
INSTALL_DIR="/opt/lineup"
VERSION_TAG_PATTERN='^v[0-9]+\.[0-9]+\.[0-9]+.*$'

APP_SERVICE="app"
DEFAULT_APP_NAME="LineUp"
DEFAULT_ADMIN_FIRST_NAME="Administrator"
DEFAULT_ADMIN_LAST_NAME=""
DEFAULT_ADMIN_EMAIL="admin@example.com"

DOCKER_CMD="docker"
COMPOSE_CMD="docker compose"

echo
echo "=== LineUp production installer ==="
echo
echo "This script is for a fresh production installation only."
echo "Do not use it to update an existing LineUp installation."
echo

confirm() {
    local prompt="$1"
    local default="${2:-no}"
    local answer

    if [ "$default" = "yes" ]; then
        read -r -p "$prompt [Y/n]: " answer

        case "$answer" in
            ""|y|Y|yes|YES)
                return 0
                ;;
            *)
                return 1
                ;;
        esac
    else
        read -r -p "$prompt [y/N]: " answer

        case "$answer" in
            y|Y|yes|YES)
                return 0
                ;;
            *)
                return 1
                ;;
        esac
    fi
}

ask_value() {
    local prompt="$1"
    local default_value="${2:-}"
    local value

    if [ -n "$default_value" ]; then
        read -r -p "$prompt [$default_value]: " value
        printf '%s' "${value:-$default_value}"
    else
        read -r -p "$prompt: " value
        printf '%s' "$value"
    fi
}

ask_required_value() {
    local prompt="$1"
    local value=""

    while [ -z "$value" ]; do
        read -r -p "$prompt: " value

        if [ -z "$value" ]; then
            echo "This value is required." >&2
        fi
    done

    printf '%s' "$value"
}

ask_secret_value() {
    local prompt="$1"
    local value=""

    while [ -z "$value" ]; do
        read -r -s -p "$prompt: " value
        printf '\n' >&2

        if [ -z "$value" ]; then
            echo "This value is required." >&2
        fi
    done

    printf '%s' "$value"
}

generate_password() {
    openssl rand -base64 18 | tr -d '\n'
}

escape_sed_replacement() {
    printf '%s' "$1" | sed -e 's/[\/&]/\\&/g'
}

quote_env_value() {
    local value="$1"

    if [[ "$value" =~ [[:space:]#\"\'\$] ]]; then
        value="${value//\\/\\\\}"
        value="${value//\"/\\\"}"
        echo "\"$value\""
    else
        echo "$value"
    fi
}

set_env_value() {
    local key="$1"
    local value="$2"
    local quoted_value
    local escaped_value

    quoted_value="$(quote_env_value "$value")"
    escaped_value="$(escape_sed_replacement "$quoted_value")"

    if grep -qE "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${escaped_value}|" .env
    else
        echo "${key}=${quoted_value}" >> .env
    fi
}

fetch_latest_release_tag() {
    local body
    local http_status
    local response
    local release_tag

    if ! response="$(curl -sSL \
        -H "Accept: application/vnd.github+json" \
        -H "User-Agent: LineUp-installer" \
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
        echo "Expected tags like v0.0.1, v0.1.0, or v1.0.0." >&2
        exit 1
    fi

    printf '%s' "$release_tag"
}

require_ubuntu() {
    if [ ! -f /etc/os-release ]; then
        echo "Error: unable to detect operating system."
        exit 1
    fi

    # shellcheck disable=SC1091
    . /etc/os-release

    if [ "${ID:-}" != "ubuntu" ]; then
        echo "Error: this installer currently supports Ubuntu only."
        echo "Detected OS: ${PRETTY_NAME:-unknown}"
        exit 1
    fi
}

require_root() {
    if [ "$EUID" -ne 0 ]; then
        echo "This installer must be run as root. Use: sudo ./install.sh"
        exit 1
    fi
}

check_fresh_install() {
    if [ -e "$INSTALL_DIR" ]; then
        echo "Error: $INSTALL_DIR already exists."
        echo "This installer only supports fresh installations."
        echo "Use update.sh for existing installations."
        exit 1
    fi
}

check_ports() {
    local used_ports=""

    if ss -tulpn | grep -E ':(80|443)\s' >/tmp/lineup-used-ports.txt 2>/dev/null; then
        used_ports="$(cat /tmp/lineup-used-ports.txt)"
    fi

    rm -f /tmp/lineup-used-ports.txt

    if [ -n "$used_ports" ]; then
        echo "Error: port 80 or 443 is already in use."
        echo
        echo "$used_ports"
        echo
        echo "Stop the service using these ports before installing LineUp."
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

clone_latest_release() {
    echo
    echo "Retrieving latest published GitHub Release..."

    LATEST_TAG="$(fetch_latest_release_tag)"

    echo "Latest published release: $LATEST_TAG"
    echo
    echo "Cloning LineUp..."

    git clone "$REPOSITORY_URL" "$INSTALL_DIR"
    chown -R root:root "$INSTALL_DIR"

    cd "$INSTALL_DIR"

    git fetch --tags

    if ! git rev-parse -q --verify "refs/tags/$LATEST_TAG" >/dev/null; then
        echo "Error: published GitHub Release tag was not found in Git: $LATEST_TAG"
        exit 1
    fi

    git checkout "$LATEST_TAG"

    echo "Selected release: $LATEST_TAG"
}

create_env_file() {
    echo
    echo "Creating .env file..."

    if [ ! -f ".env.example" ]; then
        echo "Error: .env.example was not found in $INSTALL_DIR."
        exit 1
    fi

    cp .env.example .env

    set_env_value "APP_VERSION" "$LATEST_TAG"
    set_env_value "APP_ENV" "production"
    set_env_value "APP_DEBUG" "false"
    set_env_value "LOG_CHANNEL" "daily"
    set_env_value "LOG_LEVEL" "warning"
    set_env_value "LOG_DAILY_DAYS" "14"
    set_env_value "SEED_DEMO_DATA" "false"
}

configure_application() {
    echo
    echo "Application configuration"
    echo

    APP_NAME_VALUE="$(ask_value "Application name" "$DEFAULT_APP_NAME")"
    APP_URL_VALUE="$(ask_required_value "Application URL, including http:// or https://")"

    APP_KEY_VALUE="base64:$(openssl rand -base64 32)"
    DB_PASSWORD_VALUE="$(openssl rand -hex 24)"

    set_env_value "APP_NAME" "$APP_NAME_VALUE"
    set_env_value "APP_URL" "$APP_URL_VALUE"
    set_env_value "APP_KEY" "$APP_KEY_VALUE"
    set_env_value "DB_PASSWORD" "$DB_PASSWORD_VALUE"

    if ! grep -qE '^DB_DATABASE=' .env; then
        set_env_value "DB_DATABASE" "lineup"
    fi

    if ! grep -qE '^DB_USERNAME=' .env; then
        set_env_value "DB_USERNAME" "lineup"
    fi
}

configure_caddy() {
    echo
    echo "Caddy configuration"
    echo
    echo "If you choose public HTTPS with a domain name, the domain DNS must already point to this server's public IP address."
    echo "Ports 80 and 443 must also be reachable from the Internet for automatic Let's Encrypt certificates to work."
    echo

    if confirm "Use a public HTTPS domain with automatic Let's Encrypt certificates?" "yes"; then
        CADDY_DOMAIN="$(ask_required_value "Domain name")"

        cat > Caddyfile <<EOF
${CADDY_DOMAIN} {
    reverse_proxy app:80
}
EOF

        echo
        echo "Caddy configured for HTTPS domain: $CADDY_DOMAIN"
        echo "Make sure DNS points to this server and ports 80 and 443 are reachable from the Internet."
    else
        cat > Caddyfile <<EOF
:80 {
    reverse_proxy app:80
}
EOF

        echo
        echo "Caddy configured for plain HTTP on port 80."
    fi
}

configure_initial_admin() {
    echo
    echo "Initial administrator account"
    echo

    ADMIN_FIRST_NAME_VALUE="$(ask_value "Initial admin first name" "$DEFAULT_ADMIN_FIRST_NAME")"
    ADMIN_LAST_NAME_VALUE="$(ask_value "Initial admin last name" "$DEFAULT_ADMIN_LAST_NAME")"
    ADMIN_EMAIL_VALUE="$(ask_value "Initial admin email" "$DEFAULT_ADMIN_EMAIL")"

    if confirm "Generate a secure initial admin password automatically?" "yes"; then
        ADMIN_PASSWORD_VALUE="$(generate_password)"
        ADMIN_PASSWORD_WAS_GENERATED="yes"
    else
        ADMIN_PASSWORD_VALUE="$(ask_secret_value "Initial admin password")"
        ADMIN_PASSWORD_WAS_GENERATED="no"
    fi

    set_env_value "ADMIN_FIRST_NAME" "$ADMIN_FIRST_NAME_VALUE"
    set_env_value "ADMIN_LAST_NAME" "$ADMIN_LAST_NAME_VALUE"
    set_env_value "ADMIN_EMAIL" "$ADMIN_EMAIL_VALUE"
    set_env_value "ADMIN_PASSWORD" "$ADMIN_PASSWORD_VALUE"
}

configure_mail() {
    echo
    echo "Email configuration"
    echo
    echo "LineUp uses email for features such as password reset and account-related messages."
    echo "If SMTP is not configured, the application will not be able to send emails and some features will not work correctly."
    echo

    if confirm "Configure SMTP now?" "yes"; then
        MAIL_HOST_VALUE="$(ask_required_value "SMTP host")"
        MAIL_PORT_VALUE="$(ask_value "SMTP port" "587")"
        MAIL_ENCRYPTION_VALUE="$(ask_value "SMTP encryption" "tls")"
        MAIL_USERNAME_VALUE="$(ask_required_value "SMTP username")"
        MAIL_PASSWORD_VALUE="$(ask_secret_value "SMTP password")"
        MAIL_FROM_ADDRESS_VALUE="$(ask_required_value "Mail from address")"
        MAIL_FROM_NAME_VALUE="$(ask_value "Mail from name" "$APP_NAME_VALUE")"

        set_env_value "MAIL_MAILER" "smtp"
        set_env_value "MAIL_SCHEME" "null"
        set_env_value "MAIL_HOST" "$MAIL_HOST_VALUE"
        set_env_value "MAIL_PORT" "$MAIL_PORT_VALUE"
        set_env_value "MAIL_USERNAME" "$MAIL_USERNAME_VALUE"
        set_env_value "MAIL_PASSWORD" "$MAIL_PASSWORD_VALUE"
        set_env_value "MAIL_ENCRYPTION" "$MAIL_ENCRYPTION_VALUE"
        set_env_value "MAIL_FROM_ADDRESS" "$MAIL_FROM_ADDRESS_VALUE"
        set_env_value "MAIL_FROM_NAME" "$MAIL_FROM_NAME_VALUE"

        MAIL_CONFIGURED="yes"
    else
        set_env_value "MAIL_MAILER" "log"
        set_env_value "MAIL_SCHEME" "null"
        set_env_value "MAIL_HOST" "127.0.0.1"
        set_env_value "MAIL_PORT" "2525"
        set_env_value "MAIL_USERNAME" "null"
        set_env_value "MAIL_PASSWORD" "null"
        set_env_value "MAIL_ENCRYPTION" "null"
        set_env_value "MAIL_FROM_ADDRESS" "no-reply@example.com"
        set_env_value "MAIL_FROM_NAME" "$APP_NAME_VALUE"

        MAIL_CONFIGURED="no"
    fi
}

start_containers() {
    echo
    echo "Building and starting containers..."

    $COMPOSE_CMD up -d --build
}

run_laravel_setup() {
    echo
    echo "Running database migrations..."

    $COMPOSE_CMD exec "$APP_SERVICE" php artisan migrate --force

    echo
    echo "Running initial seeders..."

    $COMPOSE_CMD exec "$APP_SERVICE" php artisan db:seed --force

    echo
    echo "Refreshing Laravel cache..."

    $COMPOSE_CMD exec "$APP_SERVICE" php artisan optimize:clear
    $COMPOSE_CMD exec "$APP_SERVICE" php artisan config:cache
    $COMPOSE_CMD exec "$APP_SERVICE" php artisan route:cache
    $COMPOSE_CMD exec "$APP_SERVICE" php artisan view:cache
}

show_status() {
    echo
    echo "Container status:"
    $COMPOSE_CMD ps
}

print_summary() {
    echo
    echo "=== LineUp installation completed ==="
    echo
    echo "Installed version: $LATEST_TAG"
    echo "Project directory: $INSTALL_DIR"
    echo "Application URL: $APP_URL_VALUE"
    echo
    echo "Initial administrator:"
    echo "Email: $ADMIN_EMAIL_VALUE"

    if [ "$ADMIN_PASSWORD_WAS_GENERATED" = "yes" ]; then
        echo "Password: $ADMIN_PASSWORD_VALUE"
        echo
        echo "Save this password now. It will not be shown again."
    else
        echo "Password: the password entered during installation"
    fi

    echo
    if [ "$MAIL_CONFIGURED" = "yes" ]; then
        echo "SMTP was configured."
    else
        echo "SMTP was not configured."
        echo "Password reset and email-based features will not send real emails until mail settings are configured in .env."
    fi
}

main() {
    require_root

    if ! confirm "Continue with a fresh LineUp installation?"; then
        echo "Installation cancelled."
        exit 0
    fi

    require_ubuntu
    check_fresh_install
    install_base_packages
    check_ports
    install_docker
    clone_latest_release
    create_env_file
    configure_application
    configure_caddy
    configure_initial_admin
    configure_mail
    start_containers
    run_laravel_setup
    show_status
    print_summary
}

main "$@"
