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

**First-time setup** — create the shared Docker network (once):

```bash
docker network create ibl5-proxy
```

The site is available at **http://main.localhost/ibl5/**.

## Services

| Service | URL | Purpose |
|---------|-----|---------|
| PHP-Apache | http://main.localhost/ibl5/ | Application |
| MariaDB | localhost:3306 | Database |
| Mailpit | http://localhost:8025 | Email testing UI |
| Adminer | http://localhost:8082 | Database browser |
| Traefik Dashboard | http://localhost:8081 | Reverse proxy routes |

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
- **Playwright:** `bun run test:e2e` — hits http://main.localhost/ibl5/ served by Docker PHP
- **PHPStan:** `cd ibl5 && composer run analyse`

Code edits on the host are immediately reflected in the container (bind mount: `./ibl5 -> /var/www/html/ibl5`).

## OPcache

The Docker PHP container has OPcache enabled with `validate_timestamps=1` and `revalidate_freq=0`. This means PHP checks file modification times on every request, so code edits are visible immediately — no cache clearing needed. OPcache eliminates redundant parsing/compilation, making PHP requests 2-4x faster.

To verify OPcache is loaded:

```bash
docker compose exec php php -m | grep OPcache
```

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

## Multi-Worktree Environments

Run multiple worktrees simultaneously, each with its own PHP-Apache container and isolated MariaDB database. Traefik routes requests by hostname (`*.localhost`).

### Quick Start

```bash
# Start a worktree environment
bin/wt-up my-feature              # → http://my-feature.localhost/ibl5/

# With test seed data
bin/wt-up my-feature --seed       # Also imports ci-seed.sql

# Use PR number as URL
bin/wt-up my-feature --pr         # → http://pr-42.localhost/ibl5/
```

### Managing Environments

```bash
# List all running worktree environments
bin/wt-list

# Stop a worktree environment
bin/wt-down my-feature

# Stop and remove database volume
bin/wt-down my-feature --volumes

# Tear down all worktree environments
bin/wt-down --all --volumes
```

### How It Works

- **Traefik** (port 80) routes by `Host` header — `main.localhost` goes to the main repo, `<slug>.localhost` goes to each worktree
- Each worktree gets its own MariaDB container with an isolated database
- `*.localhost` resolves to `127.0.0.1` natively in Chrome and Firefox (RFC 6761)
- **Safari** requires manual `/etc/hosts` entries (the script prints a reminder)

### Adminer Access

Connect to any worktree's database via Adminer at http://localhost:8082:
- Server: `db-<slug>` (e.g., `db-my-feature`)
- Username: `root`
- Password: `root`

### CLI Database Access

```bash
docker exec -it ibl5-db-<slug> mariadb -u root -proot iblhoops_ibl5
```

### Convenience Scripts

```bash
bun run wt:up -- my-feature       # Start worktree environment
bun run wt:down -- my-feature     # Stop worktree environment
bun run wt:list                   # List all environments
```

### Resource Usage

Each worktree MariaDB uses ~100-200MB of memory. Tear down environments when not in use:

```bash
bin/wt-down --all --volumes
```
