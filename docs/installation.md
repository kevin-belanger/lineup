# Production installation

This document explains the production installation flow for LineUp.

For normal production installations, use the automated installer from the main `README.md`.

The automated installer is the recommended installation method. It installs Git, Docker, Docker Compose, and the other required packages automatically.

The command sections below are an advanced reference for troubleshooting or custom installations.

## Docker production stack

The Docker setup uses:

- a Laravel Octane application container served by FrankenPHP;
- a scheduler container for Laravel scheduled tasks;
- a MySQL database container;
- a Redis container;
- a Caddy reverse proxy container.

The production application and scheduler containers bind mount the project directory into `/var/www/html`. The host checkout under `/opt/lineup` is therefore the deployed application code, while generated paths such as `vendor/`, `node_modules/`, and `public/build/` are prepared by the install and update scripts.

The application container listens internally on port `8000`. Caddy remains the public web entry point and reverse proxies requests to FrankenPHP.

Caddy is the public web entry point. The default `Caddyfile` can use plain HTTP on port 80, or a public domain with automatic HTTPS through Let’s Encrypt.

## Server requirements

Use a fresh Ubuntu server.

The examples below assume you are running the procedure from a root session. If you run them from a regular user account, use `sudo` for commands that install packages, write under `/opt/lineup`, or run Docker.

Install the basic tools:

```bash
sudo apt update
sudo apt install -y git openssl ca-certificates curl
```

## Install Docker

Remove conflicting old packages if they exist:

```bash
sudo apt remove -y docker.io docker-compose docker-compose-v2 docker-doc podman-docker containerd runc || true
```

Add Docker's official repository:

```bash
sudo install -m 0755 -d /etc/apt/keyrings

sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
    -o /etc/apt/keyrings/docker.asc

sudo chmod a+r /etc/apt/keyrings/docker.asc

sudo tee /etc/apt/sources.list.d/docker.sources > /dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF
```

Install Docker and Docker Compose:

```bash
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

Verify the installation:

```bash
sudo docker --version
sudo docker compose version
```

## Clone the latest stable release

Stable versions are published GitHub Releases. Retrieve the latest published release `tag_name`, then clone the repository and switch to that tag:

```bash
LATEST_TAG="$(
    curl -fsSL \
        -H "Accept: application/vnd.github+json" \
        -H "User-Agent: LineUp-installer" \
        https://api.github.com/repos/kevin-belanger/lineup/releases/latest \
        | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' \
        | head -n 1
)"

if [ -z "$LATEST_TAG" ]; then
    echo "No published GitHub Release found."
    exit 1
fi

if ! printf '%s\n' "$LATEST_TAG" | grep -Eq '^v[0-9]+\.[0-9]+\.[0-9]+.*$'; then
    echo "Invalid release tag: $LATEST_TAG"
    exit 1
fi

cd /opt
git clone https://github.com/kevin-belanger/lineup.git lineup
cd lineup

git fetch --tags

if ! git rev-parse -q --verify "refs/tags/$LATEST_TAG" >/dev/null; then
    echo "Published GitHub Release tag was not found in Git: $LATEST_TAG"
    exit 1
fi

git checkout "$LATEST_TAG"
```

Production installations should use published GitHub Releases instead of the `main` branch or unpublished Git tags.

Release `tag_name` values must start with `vX.X.X`, for example:

```text
v0.0.1
v0.1.0
v1.0.0
```

## Create the environment file

Copy the example environment file:

```bash
cp .env.example .env
```

Set the installed application version from the checked-out release `tag_name`:

```bash
sed -i "s|^APP_VERSION=.*|APP_VERSION=${LATEST_TAG}|" .env
```

Edit the `.env` file:

```bash
nano .env
```

At minimum, configure the following values.

Set the application URL to the address users will use to access LineUp:

```env
APP_URL=https://lineup.example.com
```

Generate the Laravel application key:

```bash
APP_KEY="base64:$(openssl rand -base64 32)"
sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
```

Generate a MySQL password:

```bash
DB_PASSWORD="$(openssl rand -hex 24)"
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
```

Configure the initial administrator account.

If these values are left empty, LineUp creates the default administrator account:

```text
Email: admin@example.com
Password: password
```

For production, define a custom administrator before running the seeders:

```env
ADMIN_FIRST_NAME="Admin"
ADMIN_LAST_NAME="LineUp"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-this-password
```

Configure outgoing email.

LineUp uses email for password reset and account-related messages. For production, configure SMTP in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="lineup@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Common SMTP configurations are:

- port `587` with `tls`;
- port `465` with `ssl`.

The `MAIL_FROM_ADDRESS` must be a valid sender address accepted by your SMTP provider.

Recommended production logging values are:

```env
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

## Configure Caddy

For plain HTTP on port 80:

```caddyfile
:80 {
    reverse_proxy app:8000
}
```

For a public domain with HTTPS:

```caddyfile
lineup.example.com {
    reverse_proxy app:8000
}
```

Replace `lineup.example.com` with your real domain name.

For automatic HTTPS to work:

- the domain must point to the server;
- ports 80 and 443 must be reachable from the Internet;
- the server must be publicly accessible.

### Optional third-party SSL certificates

The `ssl/` directory at the project root is mounted inside the Caddy container at:

```text
/etc/caddy/ssl
```

You can place SSL certificates in this directory on the host and use them from the `Caddyfile` when needed. Adapt the file names in the `Caddyfile` so they point to the certificate and key files used by your installation.

Example:

```caddyfile
lineup.example.com {
    tls /etc/caddy/ssl/certificat.crt /etc/caddy/ssl/certificat.key

    reverse_proxy app:8000
}
```

Using third-party certificates is optional. The directory is only made available to Caddy and does not change the default Caddy behavior.

## Start the application

Build and start the containers:

```bash
docker compose up -d --build
```

Install PHP dependencies into the mounted project directory:

```bash
docker compose exec app sh -lc 'COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction'
```

Build frontend assets:

```bash
docker run --rm --user "$(id -u):$(id -g)" -e npm_config_cache=/tmp/.npm -v "$PWD:/app" -w /app node:22-alpine sh -lc "npm ci && npm run build"
```

Prepare writable Laravel directories:

```bash
docker compose exec app sh -lc 'mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache'
```

Run the database migrations:

```bash
docker compose exec app php artisan migrate --force
```

Run the seeders:

```bash
docker compose exec app php artisan db:seed --force
```

Refresh Laravel caches:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

Verify the containers:

```bash
docker compose ps
```

The application should now be available at the `APP_URL` configured in `.env`.

## Notes

Do not run `php artisan key:generate` inside the production container to create the application key.

In this Docker setup, the `.env` file belongs to the server and is not copied into the Docker image. The `APP_KEY` value should be generated directly in the server `.env` file before starting the containers.

If the database is new, always run migrations before using the application. The application may fail with a server error if required database tables such as `cache`, `sessions`, or `jobs` do not exist yet.
