# LineUp

LineUp is a Laravel application used to manage student help requests in a classroom or training center.

Students create support requests, choose a subject, add a comment, and wait in a queue. Teachers can view pending requests, take charge of requests, pause them, complete them, or return them to the queue.

Administrators can manage application settings, users, classrooms, subjects, and other configuration options.

## Recommended production installation

The recommended production installation method is the automated installer.

Use a fresh Ubuntu server.

Before running the installer, update the server packages and make sure `curl` is available:

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y curl ca-certificates
```

If the upgrade installs indicates that a reboot is required, reboot the server before continuing.

Then run the installer:

```bash
cd /tmp
curl -fsSL https://raw.githubusercontent.com/kevin-belanger/lineup/main/scripts/install.sh -o install.sh
chmod +x install.sh
./install.sh
```

The installer will:

- validate that the server is suitable for a fresh installation;
- install required packages;
- install Docker from Docker’s official repository;
- clone the latest official LineUp release tag;
- create and configure `.env`;
- configure Caddy;
- build and start the Docker containers;
- run database migrations and initial seeders;
- display the initial administrator account information.

The installer is for fresh installations only. Do not use it to update an existing installation.

For the manual installation procedure, see:

```text
docs/manual-installation.md
```

## Updating LineUp

Production updates are deployed through Git version tags, not through every commit on `main`.

To update an existing installation:

```bash
cd /opt/lineup
./scripts/update.sh
```

The update script installs the latest valid release tag, updates `APP_VERSION`, rebuilds the containers, runs migrations, and refreshes Laravel caches.

For details, see:

```text
docs/update.md
```

## Database backup and restore

Database backups and restores are handled from the server with `backup.sh`.

Create a backup:

```bash
cd /opt/lineup
./scripts/backup.sh database
```

List available backups:

```bash
./scripts/backup.sh list
```

Restore a backup:

```bash
./scripts/backup.sh restore
```

For details, see:

```text
docs/backup-restore.md
```

## Maintenance

Production uses Docker log rotation and Laravel daily logs to avoid uncontrolled disk usage.

Useful commands:

```bash
cd /opt/lineup
docker compose ps
docker compose logs --tail=100 app
docker compose logs --tail=100 scheduler
```

Do not use these commands during normal maintenance:

```bash
docker compose down -v
docker volume prune
docker system prune --volumes
```

They can delete Docker volumes, including the MySQL database volume.

For details, see:

```text
docs/maintenance.md
```

## Development with Laravel Sail

This section is only for developers who want to run LineUp locally to modify or test the application. If you only want to install and use LineUp in production, you can ignore this section and use the recommended production installation procedure above.

The production Docker setup uses:

```text
compose.yaml
```

The local development setup uses:

```text
compose.sail.yaml
```

Start the development environment:

```bash
docker compose -f compose.sail.yaml up -d --build
```

Run migrations:

```bash
docker compose -f compose.sail.yaml exec app php artisan migrate
```

Run seeders:

```bash
docker compose -f compose.sail.yaml exec app php artisan db:seed
```

Start Vite:

```bash
docker compose -f compose.sail.yaml exec app npm run dev
```

The Sail setup is intended for local development only.
