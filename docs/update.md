# Updating LineUp

LineUp production updates are deployed through published GitHub Releases, not through every commit on `main` or every Git tag.

Stable versions are the published GitHub Releases. The release `tag_name` must use this format:

```text
vX.X.X
```

Examples:

```text
v0.0.1
v0.1.0
v1.0.0
```

Release tags may also include an optional suffix after the version number, for example:

```text
v0.1.0-beta
v1.0.0-rc.1
```

## Stable update

The recommended stable update mode installs the latest published GitHub Release:

```bash
cd /opt/lineup
sudo ./scripts/update.sh
```

The update script will:

- locate the project root;
- retrieve the latest published GitHub Release from the GitHub API;
- validate the release `tag_name`;
- fetch Git tags;
- switch the project to that version;
- update the installed version in `.env`;
- rebuild and recreate the Docker containers so `.env` changes are loaded;
- run database migrations;
- refresh Laravel caches.

In stable mode, `APP_VERSION` is set to the release `tag_name`, for example:

```env
APP_VERSION=v1.2.3
```

## Branch update

Branch update modes install code directly from a Git branch. They are not stable releases.

Use the exact remote branch name from `origin`. Branch names are case-sensitive.

Install the latest commit from a branch:

```bash
cd /opt/lineup
sudo ./scripts/update.sh MAIN
```

Install a specific commit that belongs to a branch:

```bash
cd /opt/lineup
sudo ./scripts/update.sh MAIN abc1234
```

The script verifies that:

- the branch exists exactly on `origin`;
- branch name casing matches exactly;
- the commit exists when one is provided;
- the commit belongs to the requested branch.

In branch modes, `APP_VERSION` is set to the branch name and the installed short commit hash, for example:

```env
APP_VERSION=MAIN abc1234
```

The script does not delete Docker volumes. Application data, uploaded files, Redis data, Caddy data, and the MySQL database are preserved.

You can verify the installed application version from the admin settings page.

## Important warnings

Do not use these commands during an update:

```bash
sudo docker compose down -v
sudo docker volume prune
sudo docker system prune --volumes
```

They can delete Docker volumes, including the MySQL database volume.
