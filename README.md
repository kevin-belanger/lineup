# LineUp

LineUp is a Laravel application used to manage student help requests in a classroom or training center.

Students can create support requests, choose a subject, add a comment, and wait in a queue. Teachers can view pending requests, take charge of a request, pause it, complete it, or return it to the queue.

Administrators can manage application settings, users, classrooms, subjects, and other configuration options.

The application is designed for environments where students work individually and teachers need a clear view of who needs help, where they are seated, and what they are working on.

## Production installation with Docker

This is the recommended production installation method.

The Docker setup uses:

- a Laravel application container;
- a scheduler container for Laravel scheduled tasks;
- a MySQL database container;
- a Redis container;
- a Caddy reverse proxy container.

Caddy is used as the public web entry point. By default, the provided `Caddyfile` uses plain HTTP on port 80. It also contains commented examples for internal domain names and public HTTPS with automatic Let's Encrypt certificates.

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

```bash
git clone https://github.com/kevin-belanger/lineup.git lineup
cd lineup
```

## Create the environment file

Copy the example environment file:

```bash
cp .env.example .env
```

## Set APP_URL in the environment file

Edit `.env` and set the application URL:

```bash
nano .env
```

Set APP_URL to the address that users will use to access the application.

Example:
APP_URL=https://lineup.example.com


## Generate required secret values

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

## Initial administrator account

By default, a fresh installation creates this administrator account:

```text
Email: admin@example.com
Password: password
```

Change this password immediately after the first login.

You can also define a custom initial administrator in `.env` before installation:

```env
LINEUP_ADMIN_NAME="Admin LineUp"
LINEUP_ADMIN_EMAIL=admin@example.com
LINEUP_ADMIN_PASSWORD=change-this-password
```

If these values are left empty, the default administrator account will be used.

## Configure Caddy

The default `Caddyfile` works with HTTP on port 80:

```caddyfile
:80 {
    reverse_proxy app:80
}
```

This is enough for a local test, an intranet server, or a server accessed directly by IP address.

For a public domain with HTTPS, edit the `Caddyfile` and use the commented HTTPS example:

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

The application should now be available at the `APP_URL` configured in `.env`.

## Useful commands

View running containers:

```bash
docker compose ps
```

View logs:

```bash
docker compose logs -f
```

View application logs:

```bash
docker compose exec app tail -n 100 storage/logs/laravel.log
```

Restart the containers:

```bash
docker compose restart
```

Rebuild after code changes:

```bash
docker compose up -d --build
```

Run migrations after an update:

```bash
docker compose exec app php artisan migrate --force
```

## Updating the application

LineUp production updates are deployed through Git version tags, not through every commit on the `main` branch.

Official release tags must use this format:

```text
vX.X.X
```

Examples:

```text
v0.0.1
v0.1.0
v1.0.0
```

Tags may also include an optional suffix after the version number, for example:

```text
v0.1.0-beta
v1.0.0-rc.1
```

To update an existing production installation, connect to the server, go to the project directory, and run:

```bash
./update.sh
```

The update script will:

- fetch the latest Git tags;
- find the latest valid version tag;
- switch the project to that version;
- update the installed version in `.env`;
- rebuild and restart the Docker containers;
- run database migrations;
- refresh Laravel caches.

The script does not delete Docker volumes. Application data, uploaded files, Redis data, Caddy data, and the MySQL database are preserved.

You can also verify the installed application version from the admin settings page.

## Database backup and restore

LineUp database backups and restores are handled from the server with `backup.sh`.

Backups are not created or restored from the web interface. This keeps database operations outside the Laravel application and avoids giving the web interface direct control over critical server actions.

### Create a database backup

To create a SQL backup of the current application database, connect to the server, go to the project directory, and run:

```bash
./backup.sh database
```

The script creates the `backups/` directory if it does not already exist and saves the SQL file there.

The generated backup includes metadata in the SQL header, including:

- application name;
- installed LineUp version;
- repository URL;
- generation date and time;
- application time zone;
- database name.

This version information is used to help validate compatibility before restoring the backup.

Runtime data such as sessions, cache entries, queued jobs, and failed jobs is excluded from the backup. The table structures remain available, but their temporary data is not restored.

### List available backups

To list existing SQL backups stored in the `backups/` directory, run:

```bash
./backup.sh list
```

### Restore a database backup

To restore a backup using an interactive menu, run:

```bash
./backup.sh restore
```

The script will display the available backup files and ask which one should be restored.

You can also restore a specific backup file:

```bash
./backup.sh restore backups/backup-file.sql
```

Replace `backup-file.sql` with the actual backup filename.

The restore process will:

- verify that the SQL file exists;
- verify that the MySQL container is running;
- test the database connection using the credentials from `.env`;
- read the backup version from the SQL header;
- compare the backup version with the installed application version;
- warn the administrator before replacing the current application data;
- ask for explicit confirmation before continuing;
- import the SQL file into the MySQL container;
- run database migrations;
- refresh Laravel caches.

If the backup version does not match the installed application version, the script will display an additional warning. It is recommended to install the matching LineUp version before restoring the backup.

Restoring a backup replaces the current application data. Use this only when you understand that the current database content will be overwritten by the backup.

The `backups/` directory should not be committed to Git.

## Development with Laravel Sail

The production Docker setup uses:

```text
compose.yaml
```

The local development setup uses:

```text
compose.sail.yaml
```

To start the development environment:

```bash
docker compose -f compose.sail.yaml up -d --build
```

Run migrations in development:

```bash
docker compose -f compose.sail.yaml exec app php artisan migrate
```

Run the seeders in development:

```bash
docker compose -f compose.sail.yaml exec app php artisan db:seed
```

Start Vite in development:

```bash
docker compose -f compose.sail.yaml exec app npm run dev
```

The Sail setup is intended for local development. The production setup should use `compose.yaml`.

## Notes

Do not run `php artisan key:generate` manually inside the production container to create the application key.

In this Docker setup, the `.env` file belongs to the server and is not copied into the Docker image. The `APP_KEY` value should be generated directly in the server `.env` file before starting the containers.

If the database is new, always run migrations before using the application. The application may fail with a server error if required database tables such as `cache`, `sessions`, or `jobs` do not exist yet.

