---
description: Docker MariaDB connection details, query patterns, and schema verification rules.
paths:
  - "**/*Repository.php"
  - "**/migrations/000_baseline_schema.sql"
  - "**/migrations/**"
  - "**/db/**"
  - "**/seed*.php"
  - "**/seed*.sql"
last_verified: 2026-04-23
---

# Database Access Reference

## Local Docker MariaDB Connection

**Connection Details:**
- Host: `127.0.0.1`
- Port: `3306`
- Database: `iblhoops_ibl5`
- Credentials: See `ibl5/config.php` (`$dbuname`, `$dbpass`)

**Start the database:**
```bash
docker compose up -d   # from repo root
```

**PHP Connection (app standard):**
```php
// Via app bootstrap (standard way)
require_once 'autoloader.php';
include 'config.php';
include 'db/db.php';
// $mysqli_db and $db are now available globally
```

**Command Line Access:**
```bash
mariadb -h 127.0.0.1 --skip-ssl -u root -proot iblhoops_ibl5
```

## Claude Code Database Queries (Auto-Approved)

```bash
# Use this wrapper script for database queries - it auto-approves without user confirmation
# Works from BOTH the repo root and ibl5/ — a symlink at bin/db-query points to ibl5/bin/db-query
./bin/db-query "SELECT * FROM ibl_plr LIMIT 5"
./bin/db-query "SELECT COUNT(*) FROM ibl_team_info"
./bin/db-query "DESCRIBE ibl_plr"
```

**When to use `db-query`:** Use this script to explore the database schema, verify data after making changes, check record counts, and validate your work. This is the preferred method for Claude to query the local database since it's configured for auto-approval in the user's Claude Code settings.

## Migration Runner

```bash
bin/db-migrate <db-container> <migrations-dir>
```

Runs pending SQL migrations against a Docker MariaDB container. Tracks applied migrations in `schema_migrations(version VARCHAR PRIMARY KEY)`. Idempotent — skips already-applied migrations. Used internally by `bin/wt-up` to apply migrations after seeding.

## MariaDB Strict Mode & Triggers

- **NOT NULL columns without DEFAULT reject INSERTs before BEFORE INSERT triggers fire.** If a column is `NOT NULL` with no `DEFAULT`, MariaDB strict mode (enabled by default since 10.2) throws `Field 'x' doesn't have a default value` *before* any BEFORE INSERT trigger can auto-populate it. All uuid columns now have `DEFAULT (UUID())` (migration 065), so uuid is no longer an example of this problem. The rule still applies to other NOT NULL columns without defaults.

## BaseMysqliRepository API

All repositories extend `BaseMysqliRepository`. Core methods:

| Method | Returns | Use |
|--------|---------|-----|
| `executeQuery($query, $types, ...$params)` | `mysqli_stmt` | Raw prepared statement (caller closes) |
| `fetchOne($query, $types, ...$params)` | `?array` | Single row or null |
| `fetchAll($query, $types, ...$params)` | `array` | All rows |
| `execute($query, $types, ...$params)` | `int` | INSERT/UPDATE/DELETE — affected rows |
| `getLastInsertId()` | `int` | Auto-increment ID after INSERT |

**Type-spec characters:** `i` (INT), `s` (VARCHAR/TEXT), `d` (FLOAT/DOUBLE), `b` (BLOB).

**Error codes:** 1001 = type/param count mismatch, 1002 = prepare failed (bad SQL), 1003 = execute failed (constraint violation).

## Multiple Claude Instances Protocol

Other Claude instances may be working in this directory simultaneously.

1. **Before editing a file:** Run `git status`. If the file has unstaged changes you didn't make, alert the user before proceeding.
2. **Scope discipline:** Only modify files directly related to your task. If you need to change a shared file, confirm with the user first.
3. **Before staging:** Run `git diff --name-only` and only stage files you personally modified. Never use `git add .` or `git add -A`.
4. **Testing:** Always run the full test suite, even if other instances may have partial work in progress. If another instance's in-progress changes cause failures in files you did not touch, note them but do not suppress them.
