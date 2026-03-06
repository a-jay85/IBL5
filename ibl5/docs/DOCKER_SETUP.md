# Docker Development Setup

The entire IBL5 dev stack runs via Docker Compose: PHP-Apache, MariaDB, Mailpit (email testing), and Adminer (DB browser).

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Bun](https://bun.sh/) (for CSS builds, PHPUnit, and Playwright)

## Quick Start

```bash
# From repo root
docker compose up -d --build

# Or from ibl5/
bun run docker:up
```

The site is available at **http://localhost/ibl5/**.

## Services

| Service | URL | Purpose |
|---------|-----|---------|
| PHP-Apache | http://localhost/ibl5/ | Application |
| MariaDB | localhost:3306 | Database |
| Mailpit | http://localhost:8025 | Email testing UI |
| Adminer | http://localhost:8080 | Database browser |

### Adminer Login

- System: MySQL
- Server: `mariadb`
- Username: `root`
- Password: `root`
- Database: `iblhoops_ibl5`

## Port 80 Conflict

If MAMP is running, stop it before starting Docker (both use port 80). To switch back to MAMP:

```bash
docker compose down   # or: bun run docker:down
# Then start MAMP
```

The `config.php` change (`getenv('DB_HOST') ?: '127.0.0.1'`) ensures both environments work without editing config files.

## Development Workflow

Tools that run on the **host** (not inside Docker):

- **CSS:** `bun run css:watch` — file changes are instantly visible via the bind mount
- **PHPUnit:** `cd ibl5 && vendor/bin/phpunit` — connects to Docker MariaDB on localhost:3306
- **Playwright:** `bun run test:e2e` — hits http://localhost/ibl5/ served by Docker PHP
- **PHPStan:** `cd ibl5 && composer run analyse`

Code edits on the host are immediately reflected in the container (bind mount: `./ibl5 -> /var/www/html/ibl5`).

## Email Testing with Mailpit

To capture outgoing emails in Mailpit, update `ibl5/config/mail.config.php`:

```php
'transport' => 'smtp',
'smtp' => [
    'host' => 'mailpit',
    'port' => 1025,
    'encryption' => '',
    'username' => '',
    'password' => '',
],
```

All sent emails appear in the Mailpit UI at http://localhost:8025.

## Convenience Scripts

```bash
bun run docker:up     # Start all containers
bun run docker:down   # Stop all containers
bun run docker:logs   # Follow PHP container logs
```

## Container Logs

```bash
docker compose logs -f php      # PHP-Apache logs
docker compose logs -f mariadb  # Database logs
```
