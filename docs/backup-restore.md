# Backup and restore

LineUp backups are created from the server with `backup.sh`.

Backups are not created or restored from the web interface. This keeps critical server operations outside the Laravel application.

## Create a backup

```bash
cd /opt/lineup
sudo ./scripts/backup.sh
```

The script creates a timestamped backup directory:

```text
backups/lineup-backup-YYYYMMDD-HHMMSS/
```

Each backup contains:

- `metadata.env`, a Bash-readable metadata file;
- `database.sql`, a complete MySQL dump;
- `deleted-tracked-files.txt`, the tracked Git files that were deleted locally;
- `manifest.txt`, the list of files in the backup;
- `restore.sh`, the restore script copied from the version that created the backup;
- `files/`, restored files and persistent Laravel `storage` files.

The `files/storage/app/` directory and any `files/storage/*.key` files are copied from the running `app` container. In production, `/var/www/html` is a bind mount to the application directory on the host, so this captures the persistent Laravel storage from that host directory. Runtime files such as cache, compiled views, sessions, and logs are not backed up.

The backup also includes important local files such as `.env`, `Caddyfile`, and common override files, plus local Git changes and untracked files. Large or generated paths such as `vendor/`, `node_modules/`, `backups/`, `public/build/`, `public/hot`, `public/storage`, and the local `storage/` directory are skipped.

The `app` and `mysql` containers must be running when the backup is created.

## Restore a backup

Run the restore script from inside a backup directory:

```bash
sudo ./restore.sh
```

The restore script must be run with root privileges. It can also be run directly from a root session.

By default, the script restores to the application path saved in `metadata.env`. If that value is missing, it falls back to `/opt/lineup`.

You can also provide a target path:

```bash
sudo ./restore.sh /opt/lineup
```

The restore script can be used on a fresh Ubuntu server or on a server that already has Docker and other applications. It installs or updates the required host packages, including Git, Docker, and Docker Compose, using Docker's official repository.

If the server already runs other Docker applications, the restore only removes Docker volumes for the target LineUp Compose project. Installing or updating Docker may still briefly affect Docker while packages are updated.

If the target path already exists and contains a `compose.yaml` file, the restore script stops the existing Docker Compose application and removes its volumes. The target directory is then moved aside to:

```text
TARGET.before-restore-YYYYMMDD-HHMMSS
```

If the backup directory is inside the target path, the restore script first copies it to a temporary location so the backup remains available after the target directory is moved.

Then the script clones the repository, checks out the exact saved commit from `metadata.env`, copies the backed up files, applies deleted tracked files, starts MySQL, imports `database.sql`, restores persistent storage files, rebuilds dependencies and frontend assets, starts the application, and clears Laravel caches.

The restore does not select the latest Git tag or latest GitHub Release. It restores the application to the saved backup state and does not run database migrations.

The `backups/` directory should not be committed to Git.
