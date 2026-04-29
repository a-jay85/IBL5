---
description: Docker setup, dependency management, testing commands, and database connection details.
last_verified: 2026-04-29
---

# IBL5 Development Environment Setup

**Purpose:** Environment setup, dependency management, and database connection.
**When to reference:** Initial setup, troubleshooting dependencies, database connection issues.

---

## Quick Start

```bash
docker compose up -d          # Start Docker stack (PHP + MariaDB)
cd ibl5 && composer install   # Install PHP dependencies
composer run test             # Run all tests (from ibl5/)
```

---

## Dependencies

Install with `composer install` from the `ibl5/` directory. In CI, `.github/workflows/cache-dependencies.yml` and `.github/workflows/tests.yml` handle caching via `actions/cache` keyed on `composer.lock`.

---

## Testing from Command Line

```bash
composer run test                                     # Run all tests (from ibl5/)
composer run test -- --filter testRenderPlayerHeader  # Run specific test
composer run test -- --testsuite "Player"             # Run specific suite
composer run analyse                                  # PHPStan
```

---

## Verifying Setup

```bash
cd ibl5
vendor/bin/phpunit --version               # Should show PHPUnit 13.0+
vendor/bin/phpcs --version                 # Should show PHP_CodeSniffer version
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| `vendor/bin/phpunit` not found | Run `cd ibl5 && composer install` |
| `Composer install fails` | Check network; check `.github/workflows/cache-dependencies.yml` logs in CI |
| `Can't connect to database` | Check Docker is running: `docker compose ps`. Start with `docker compose up -d` |

---

## Key Files

- `.github/workflows/cache-dependencies.yml` - Pre-cache workflow (runs daily)
- `.github/workflows/tests.yml` - CI/CD with dependency caching
- `ibl5/composer.json` - Project dependencies (dev tools)
- `ibl5/composer.lock` - Locked dependency versions

---

## Local Database (Docker MariaDB)

### Connection Details
- **Host:** `127.0.0.1`
- **Port:** `3306`
- **Database Name:** `iblhoops_ibl5`
- **Credentials Location:** See `ibl5/config.php` (in `.gitignore`)

### Starting the Database

```bash
docker compose up -d
docker compose ps
```

### Command Line Verification

```bash
mariadb -h 127.0.0.1 --skip-ssl \
  -u root -proot \
  -D iblhoops_ibl5 \
  -e "SELECT COUNT(*) as table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'iblhoops_ibl5';"
```

### Data Safety

- `docker compose down` preserves database data (named volume persists)
- `docker compose down -v` **destroys all data** (removes the volume)

### Security Notes

- **Credentials Location:** Database credentials live in `ibl5/config.php` (gitignored)
- **Never Share:** Do not copy credentials outside local development
- **Production Data:** Local database contains production IBL data - be careful with destructive queries
