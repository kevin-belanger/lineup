# Development with Laravel Sail

The production Docker setup uses:

```text
compose.yaml
```

The local development setup uses:

```text
compose.sail.yaml
```

## Start the development environment

```bash
docker compose -f compose.sail.yaml up -d --build
```

## Run migrations

```bash
docker compose -f compose.sail.yaml exec app php artisan migrate
```

## Run seeders

```bash
docker compose -f compose.sail.yaml exec app php artisan db:seed
```

## Start Vite

```bash
docker compose -f compose.sail.yaml exec app npm run dev
```

## Stop the development environment

```bash
docker compose -f compose.sail.yaml down
```

Do not use the production `compose.yaml` for local development unless you intentionally want to test the production container setup.
