---
description: Docker MariaDB connection details, query patterns, schema verification, and migration authoring rules. Long-form sections live in database-access-detail.md (Read on demand).
last_verified: 2026-11-11
paths:
  - "**/*Repository.php"
  - "**/migrations/000_baseline_schema.sql"
  - "**/migrations/**"
  - "**/db/**"
  - "**/Seed*.php"
  - "**/*seed*.sql"
---

# Database Access Reference

## Local Docker MariaDB Connection

**Connection Details (from the host):**
- Host: `127.0.0.1`
- Port: `3306` — published by the **main** stack's `ibl5-mariadb` only
- Database: `iblhoops_ibl5`
- Credentials: See `ibl5/config.local.php` (`$dbuname`, `$dbpass`)

The template `ibl5/config.php.example` (used by CI) requires the gitignored `ibl5/config.local.php` for `$dbhost`, `$dbuname`, `$dbpass`, `$dbname`; template at `ibl5/config.local.php.example`. A config built from that template has no env-var fallback and exits 1 when `config.local.php` is missing. The main checkout's untracked `ibl5/config.php` may still carry the older `getenv('DB_NAME') ?: <fallback>` lines, and `bin/wt-new` copies it into every worktree. In that shape the docker-compose env wins inside the containers, and on a host shell the file's fallback is used. A stale local edit in either file silently retargets every PHP/`db-query` call. Check the file before believing a surprising result.

**Start the database:**
```bash
docker compose up -d   # from repo root
```

**PHP Connection (app standard):** Composer PSR-4 autoloading, not a hand-rolled autoloader.

```php
// Web request: mainfile.php wires config + DB via the Bootstrap factories.
require_once 'mainfile.php';

// CLI script: bootstrap by hand — see ibl5/scripts/bug-pipeline/_bootstrap.php
// for the canonical version (it also re-registers the worktree's classes/ dir,
// which matters because vendor/ symlinks to the main repo).
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';
// $mysqli_db and $db are now available globally
```

**Command Line Access:**
```bash
mariadb -h 127.0.0.1 --skip-ssl -u root -proot iblhoops_ibl5
```

## Claude Code Database Queries (Auto-Approved)

Moved to `.claude/rules/database-access-detail.md` § Claude Code Database Queries (Auto-Approved). Use `./bin/db-query "<SQL>"` for local database queries; it auto-approves, and routes to the database next to the script's own location.

## Migration Runner

```bash
bin/db-migrate <db-container> <migrations-dir> [db-name]
```

Runs pending SQL migrations against a Docker MariaDB container. Tracks applied migrations in `schema_migrations(version VARCHAR PRIMARY KEY)`. Idempotent — skips already-applied migrations. Optional third arg overrides the target database (defaults to `iblhoops_ibl5`). Used internally by `bin/wt-up` and `bin/db-test-up`.

## Local Database Integration Tests

Moved to `.claude/rules/database-access-detail.md` § Local Database Integration Tests. In a worktree, ALWAYS run DB-integration tests via `bin/db-test-up <slug>`. A hand-run host-side `phpunit --group database` is the wrong path.

## MariaDB Strict Mode & Triggers

- **NOT NULL columns without DEFAULT reject INSERTs before BEFORE INSERT triggers fire.** If a column is `NOT NULL` with no `DEFAULT`, MariaDB strict mode (enabled by default since 10.2) throws `Field 'x' doesn't have a default value` *before* any BEFORE INSERT trigger can auto-populate it. All uuid columns now have `DEFAULT (UUID())` (migration 065), so uuid is no longer an example of this problem. The rule still applies to other NOT NULL columns without defaults.

## BaseMysqliRepository API

All repositories extend `BaseMysqliRepository`. Its data-access helpers are **`protected`** — they are the API you call *from inside* a repository subclass, not from a controller or a test. Callers get a repository's own public methods.

| Method (protected) | Returns | Use |
|--------|---------|-----|
| `executeQuery($query, $types, ...$params)` | `mysqli_stmt` | Raw prepared statement (caller closes) |
| `fetchOne($query, $types, ...$params)` | `?array` | Single row or null |
| `fetchAll($query, $types, ...$params)` | `array` | All rows |
| `execute($query, $types, ...$params)` | `int` | INSERT/UPDATE/DELETE — affected rows |
| `getLastInsertId()` | `int` | Auto-increment ID after INSERT |
| `transactional(callable $fn)` | `mixed` | Runs `$fn` in a transaction; nests via SAVEPOINT when already inside one |

**Type-spec characters:** `i` (INT), `s` (VARCHAR/TEXT), `d` (FLOAT/DOUBLE), `b` (BLOB).

**Error codes:** 1001 = type/param count mismatch, 1002 = prepare failed (bad SQL — also thrown by the constructor on an invalid or closed connection), 1003 = execute failed (constraint violation).

## Migration Authoring Gotchas

**Never run migrations directly on production.** No supported prod-write path exists: `bin/db-sync-prod` reads from prod, `bin/merge-master-to-prod` deploys code. Commit → push → CI deploy via `bin/db-migrate`. Applies to data fixes too — route through a migration.

**Idempotent DDL patterns:** `ADD COLUMN IF NOT EXISTS`, `DROP COLUMN IF EXISTS`, `ADD INDEX IF NOT EXISTS`, `CREATE TABLE IF NOT EXISTS`, `CREATE OR REPLACE VIEW/TRIGGER`. `MODIFY COLUMN` is already idempotent.

**Column/FK gotchas:**
- CHECK constraints on FK columns are forbidden (MariaDB 10.6 `ERROR 1901`).
- Can't MODIFY column type with active FK (`ERROR 1832`) — drop the FK first.
- Reserved words (`to`, `from`, `do`) must be backtick-quoted in DDL.
- `team_id` column references in `ibl5/classes/` must be **bare** (no backticks) — `BanInconsistentColumnNamesRule` bans `` `team_id` `` there. Only the table name keeps backticks.

**View recreation:** always pull the definition from the **latest migration** that owns the view (`grep -n "vw_<name>" ibl5/migrations/*.sql`), never from `SHOW CREATE VIEW` on local Docker (the worktree DB may predate later renames).

**Multi-PR migration number collisions** happen when multiple branches claim the same number. Recovery: rebase onto master, `git mv` to new numbers, force-push.

**`bin/check-column-rename-sweep` false positive:** migration PRs trigger this check; the `do` bash keyword in `ibl5/bin/` scripts always matches (migration 113 renamed column `do` → `r_drive_off`). Add `<!-- check-column-rename: <reason ≥20 chars> -->` to the PR body **before** the first CI run. `gh run rerun` replays the original event payload — a later body edit is invisible; push an empty commit instead.

**Schema ground truth:** `ibl5/migrations/000_baseline_schema.sql` is a **stale 2026-03-11 snapshot** — post-migration column names (e.g., `ibl_plr.teamid`, `ibl_schedule.game_date`) are NOT there. Use `ibl5/docs/schema/current-schema.sql` (CI-regenerated on every master push) for current names.

**`wt-up --seed` OOM at migrations 034/124** under memory pressure from 20+ running worktrees (~95MB each). Workaround: stop unused worktrees first (`bin/wt-down`), then retry. If container crashes again, run the failing migration SQL directly into the container and `INSERT IGNORE INTO schema_migrations ...`, then resume `bin/db-migrate`.

## SQL Pattern Gotchas

- **Never include nullable columns in UNIQUE KEYs for `ON DUPLICATE KEY UPDATE`.** `NULL != NULL` → upsert never matches → silent duplicates. Use a sentinel (`DEFAULT 0`).
- **Use `BaseMysqliRepository::transactional()`** for multi-statement atomicity. Direct `begin_transaction()` in repository subclasses is banned by PHPStan (`BanBeginTransactionInRepositoryRule`). Processors that call `begin_transaction()` internally break `DatabaseTestCase` (the outer transaction is implicitly committed) — fix with `$this->db->commit()` in `setUp()` and manual `DELETE` cleanup in `tearDown()`.
- **FK column types must match exactly.** `auth_users.id` is `INT UNSIGNED`; FK must also be `INT UNSIGNED`. MariaDB rejects signedness mismatches with errno 150.
- **Use PIDs in 200000000+ range** for integration test data. Production goes up to ~100M; 90000–100000 is occupied by CI seed.
- **CI seed `Current Season Ending Year`** is 2026. Use 8888 or similar for "no data" assertions.
