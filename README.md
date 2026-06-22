# LineUp

LineUp is a Laravel application used to manage student help requests in a classroom or training center.

Students create support requests, choose a subject, add a comment, and wait in a queue. Teachers can view pending requests, take charge of requests, pause them, complete them, or return them to the queue.

Administrators can manage application settings, users, classrooms, subjects, and other configuration options.

## Recommended production installation

The recommended production installation method is the automated installer.

Use a fresh Ubuntu server.

Download the installer and run it with root privileges:

```bash
cd /tmp
curl -fsSL https://raw.githubusercontent.com/kevin-belanger/lineup/main/scripts/install.sh -o install.sh
chmod +x install.sh
sudo ./install.sh
```

The installer can also be run directly from a root session.

The installer will:

- validate that the server is suitable for a fresh installation;
- install required packages;
- install Docker from Docker’s official repository;
- clone the latest published LineUp GitHub Release;
- create and configure `.env`;
- configure Caddy;
- build and start the Docker containers;
- run database migrations and initial seeders;
- display the initial administrator account information.

The installer is for fresh installations only. Do not use it to update an existing installation.

Detailed production installation notes are available for the automated installer and for advanced troubleshooting:

```text
docs/installation.md
```

## Updating LineUp

Production updates are deployed through published GitHub Releases, not through every commit on `main` or every Git tag.

To update an existing installation:

```bash
cd /opt/lineup
sudo ./scripts/update.sh
```

Without arguments, the update script installs the latest published GitHub Release, updates `APP_VERSION` from the release `tag_name`, rebuilds the containers, runs migrations, and refreshes Laravel caches. It can also install code directly from a named remote branch when that is explicitly requested.

For details, see:

```text
docs/update.md
```

## Database backup and restore

Database backups and restores are handled from the server with `backup.sh`.

Create a backup:

```bash
cd /opt/lineup
sudo ./scripts/backup.sh database
```

List available backups:

```bash
sudo ./scripts/backup.sh list
```

Restore a backup:

```bash
sudo ./scripts/backup.sh restore
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
sudo docker compose ps
sudo docker compose logs --tail=100 app
sudo docker compose logs --tail=100 scheduler
```

Do not use these commands during normal maintenance:

```bash
sudo docker compose down -v
sudo docker volume prune
sudo docker system prune --volumes
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

### Testing database isolation

Development and test data must stay separated:

- development database: `lineup`
- testing database: `lineup_testing`

The test environment is defined in `.env.testing`, and `phpunit.xml` also forces `DB_CONNECTION=mysql` and `DB_DATABASE=lineup_testing`. Tests that run migrations, `RefreshDatabase`, or `migrate:fresh` must only affect `lineup_testing`.

If Laravel configuration has been cached before running tests, clear it first. At minimum, clear the config cache so PHPUnit can apply the testing environment:

```bash
docker compose -f compose.sail.yaml exec app php artisan config:clear
```

When the development containers are running normally, `php artisan optimize:clear` is also fine.
