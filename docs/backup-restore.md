# Database backup and restore

LineUp database backups and restores are handled from the server with `backup.sh`.

Backups are not created or restored from the web interface. This keeps database operations outside the Laravel application and avoids giving the web interface direct control over critical server actions.

## Create a database backup

To create a SQL backup of the current application database:

```bash
cd /home/lineup
./scripts/backup.sh database
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

## List available backups

To list existing SQL backups stored in the `backups/` directory:

```bash
cd /home/lineup
./scripts/backup.sh list
```

## Restore a database backup

To restore a backup using an interactive menu:

```bash
cd /home/lineup
./scripts/backup.sh restore
```

The script will display the available backup files and ask which one should be restored.

You can also restore a specific backup file:

```bash
./scripts/backup.sh restore backups/backup-file.sql
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

If the backup version does not match the installed application version, the script displays an additional warning. If the backup is older than the installed application, migrations may update the database after restoration. If the backup is newer than the installed application, restoration may break the application.

Restoring a backup replaces the current application data. Use this only when you understand that the current database content will be overwritten by the backup.

The `backups/` directory should not be committed to Git.
