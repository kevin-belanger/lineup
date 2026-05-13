# Updating LineUp

LineUp production updates are deployed through Git version tags, not through every commit on `main`.

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

## Run an update

Connect to the server and run:

```bash
cd /opt/lineup
./scripts/update.sh
```

The update script will:

- locate the project root;
- fetch the latest Git tags;
- find the latest valid version tag;
- switch the project to that version;
- update the installed version in `.env`;
- rebuild and restart the Docker containers;
- run database migrations;
- refresh Laravel caches.

The script does not delete Docker volumes. Application data, uploaded files, Redis data, Caddy data, and the MySQL database are preserved.

You can verify the installed application version from the admin settings page.

## Important warnings

Do not use these commands during an update:

```bash
docker compose down -v
docker volume prune
docker system prune --volumes
```

They can delete Docker volumes, including the MySQL database volume.
