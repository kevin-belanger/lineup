# Production maintenance

This document covers routine maintenance for a production LineUp installation.

## Logs

Production Docker services in `compose.yaml` use the Docker `json-file` logging driver with rotation enabled. The `app`, `scheduler`, `caddy`, `mysql`, and `redis` services each keep up to five 10 MB Docker log files.

Laravel should use daily application logs in production so files in `storage/logs` do not grow indefinitely.

Recommended `.env` values:

```env
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

## Scheduler cleanup

With the default production database-backed session and queue settings, the Laravel scheduler may prune expired database sessions and failed queue jobs. These tasks clean transient runtime records only. They do not delete application history such as support requests.

Verify the scheduler is running:

```bash
cd /opt/lineup
sudo docker compose ps scheduler
sudo docker compose logs --tail=100 scheduler
```

## Useful commands

View running containers:

```bash
cd /opt/lineup
sudo docker compose ps
```

View recent application container logs:

```bash
sudo docker compose logs --tail=100 app
```

View recent scheduler logs:

```bash
sudo docker compose logs --tail=100 scheduler
```

View Laravel logs:

```bash
sudo docker compose exec app ls -lh storage/logs
```

Restart containers:

```bash
sudo docker compose restart
```

Rebuild containers:

```bash
sudo docker compose up -d --build
```

## Disk usage

Show Docker disk usage:

```bash
sudo docker system df
```

List Docker volumes:

```bash
sudo docker volume ls
```

## Commands to avoid

Do not use these commands during normal maintenance:

```bash
sudo docker compose down -v
sudo docker volume prune
sudo docker system prune --volumes
```

They can delete Docker volumes, including the MySQL database volume.
