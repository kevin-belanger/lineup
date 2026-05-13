# Manual production installation

This is the detailed manual installation procedure for LineUp.

For most installations, use the automated installer from the main `README.md` instead.

## Docker production stack

The Docker setup uses:

- a Laravel application container;
- a scheduler container for Laravel scheduled tasks;
- a MySQL database container;
- a Redis container;
- a Caddy reverse proxy container.

Caddy is the public web entry point. The default `Caddyfile` can use plain HTTP on port 80, or a public domain with automatic HTTPS through Let’s Encrypt.

## Server requirements

Use a fresh Ubuntu server.

Install the basic tools:

```bash
sudo apt update
sudo apt install -y git openssl ca-certificates curl
```

## Install Docker

Remove conflicting old packages if they exist:

```bash
sudo apt remove -y docker.io docker-compose docker-compose-v2 docker-doc podman-docker containerd runc
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

Allow your user to use Docker:

```bash
sudo usermod -aG docker $USER
```

Log out, then log back in.

Verify the installation:

```bash
docker --version
docker compose version
```

## Clone the project

Clone the repository and switch to the latest release tag:

```bash
cd /home
git clone https://github.com/kevin-belanger/lineup.git lineup
cd lineup

git fetch --tags

LATEST_TAG="$(git tag --sort=-v:refname | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+.*$' | head -n 1)"

if [ -z "$LATEST_TAG" ]; then
    echo "No valid release tag found."
    exit 1
fi

git checkout "$LATEST_TAG"
```

Production installations should use tagged releases instead of the `main` branch.

Release tags must start with `vX.X.X`, for example:

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

Set the installed application version from the checked-out release tag:

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
ADMIN_NAME="Admin LineUp"
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
    reverse_proxy app:80
}
```

For a public domain with HTTPS:

```caddyfile
lineup.example.com {
    reverse_proxy app:80
}
```

Replace `lineup.example.com` with your real domain name.

For automatic HTTPS to work:

- the domain must point to the server;
- ports 80 and 443 must be reachable from the Internet;
- the server must be publicly accessible.

## Start the application

Build and start the containers:

```bash
docker compose up -d --build
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

Do not run `php artisan key:generate` manually inside the production container to create the application key.

In this Docker setup, the `.env` file belongs to the server and is not copied into the Docker image. The `APP_KEY` value should be generated directly in the server `.env` file before starting the containers.

If the database is new, always run migrations before using the application. The application may fail with a server error if required database tables such as `cache`, `sessions`, or `jobs` do not exist yet.
