# Backup and restore

LineUp backups are created from the server with `backup.sh`.

Backups are not created or restored from the web interface. This keeps critical server operations outside the Laravel application.

## Create a backup

```bash
cd /opt/lineup
./scripts/backup.sh
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
- `files/`, restored files and persistent Docker `storage` files.

The `files/storage/app/` directory and any `files/storage/*.key` files are copied from the running `app` container. This captures persistent Laravel storage from the real `laravel-storage` Docker volume instead of the placeholder `storage` directory in the Git checkout. Runtime files such as cache, compiled views, sessions, and logs are not backed up.

The backup also includes important local files such as `.env`, `Caddyfile`, and common override files, plus local Git changes and untracked files. Large or generated paths such as `vendor/`, `node_modules/`, `backups/`, `public/build/`, `public/hot`, `public/storage`, and the local `storage/` directory are skipped.

The `app` and `mysql` containers must be running when the backup is created.

## Restore a backup

Run the restore script from inside a backup directory:

```bash
./restore.sh
```

By default, the script restores to the application path saved in `metadata.env`. If that value is missing, it falls back to `/opt/lineup`.

You can also provide a target path:

```bash
./restore.sh /opt/lineup
```

The restore script requires only Bash, Git, Docker, and Docker Compose on the host.

If the target path already exists and contains a `compose.yaml` file, the restore script stops the existing Docker Compose application and removes its volumes. The target directory is then moved aside to:

```text
TARGET.before-restore-YYYYMMDD-HHMMSS
```

If the backup directory is inside the target path, the restore script first copies it to a temporary location so the backup remains available after the target directory is moved.

Then the script clones the repository, checks out the saved commit, copies the backed up files, applies deleted tracked files, starts MySQL, imports `database.sql`, restores persistent storage files into the `app` container, starts the application, and clears Laravel caches.

The restore does not run database migrations. It restores the application to the saved backup state.

The `backups/` directory should not be committed to Git.
