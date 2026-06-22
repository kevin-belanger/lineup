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
- verify that the currently installed commit is an ancestor of the target commit;
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
- the commit belongs to the requested branch;
- the currently installed commit is an ancestor of the target commit.

In branch modes, `APP_VERSION` is set to the branch name and the installed short commit hash, for example:

```env
APP_VERSION=MAIN abc1234
```

The script does not delete Docker volumes. Application data, uploaded files, Redis data, Caddy data, and the MySQL database are preserved.

Before switching versions, the script compares the currently installed commit with the target commit. If the target is not a descendant of the current commit, the script refuses to continue. This prevents accidental downgrades, unrelated branch switches, and updates across rewritten Git history. Database migrations are not rolled back automatically.

You can verify the installed application version from the admin settings page.

## Fast code deploy

For small application code changes that do not require database migrations, `.env` changes, Dockerfile changes, Compose changes, or new system-level dependencies, the running containers can be updated without rebuilding the image:

```bash
cd /opt/lineup
sudo ./scripts/deploy-code.sh
```

The fast deploy script will:

- pull the current Git branch from `origin`;
- rebuild `public/build` with a temporary `node:22-alpine` container, without requiring `npm` on the host;
- copy only the runtime application paths into the running `app` and `scheduler` containers: `app`, `artisan`, `bootstrap`, `composer.json`, `composer.lock`, `config`, `lang`, `public`, `resources`, and `routes`;
- keep `.env`, `storage`, `vendor`, and `node_modules` out of the copied payload;
- refresh Composer dependencies and Laravel caches inside the containers;
- restart the scheduler container.

When `SKIP_ASSETS=1` is used, the script does not rebuild or replace the `public` directory, so the already deployed `public/build` assets remain in place.

Use the full `update.sh` flow instead when an update includes migrations, PHP or Node dependency changes that need a fresh image, `.env` changes, Dockerfile changes, Compose changes, or any change where rebuilding the container is safer.

## Important warnings

Do not use these commands during an update:

```bash
sudo docker compose down -v
sudo docker volume prune
sudo docker system prune --volumes
```

They can delete Docker volumes, including the MySQL database volume.
