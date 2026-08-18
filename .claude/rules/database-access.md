---
description: Docker MariaDB connection details, query patterns, and schema verification rules.
last_verified: 2026-08-18
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
- Credentials: See `ibl5/config.php` (`$dbuname`, `$dbpass`)

`ibl5/config.php` is untracked and env-driven (`getenv('DB_NAME') ?: <fallback>`, template at `ibl5/config.php.example`). Inside the containers docker-compose injects `DB_NAME=iblhoops_ibl5`, so the env wins; on a **host** shell there is no `DB_NAME`, so whatever fallback that local file happens to carry is what gets used. A stale local edit there silently retargets every PHP/`db-query` call — check the file before believing a surprising result.

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

```bash
# Use this wrapper script for database queries - it auto-approves without user confirmation
# Invokable from BOTH the repo root and ibl5/ — a symlink at bin/db-query points to ibl5/bin/db-query
./bin/db-query "SELECT * FROM ibl_plr LIMIT 5"
./bin/db-query "SELECT COUNT(*) FROM ibl_team_info"
./bin/db-query "DESCRIBE ibl_plr"
```

**When to use `db-query`:** Use this script to explore the database schema, verify data after making changes, check record counts, and validate your work. This is the preferred method for Claude to query the local database since it's configured for auto-approval in the user's Claude Code settings.

**Which database it hits:** the wrapper resolves its own symlink and reads the `ibl5/config.php` **next to that resolved script** (never the repo-root `config.php`), then routes on the script's own location — not on your cwd:

- **Main checkout's copy** → the main stack over `127.0.0.1:3306`, exactly as before.
- **A worktree's copy** → that worktree's own container, `ibl5-db-<slug>`, via `docker exec`. Worktree DBs publish no host port, so this is the only way to reach them. The slug comes from `<worktree>/.wt-slug` (written by `bin/wt-up`), falling back to the worktree directory's basename.

Because routing keys on the script's location, invoking the **main** checkout's `bin/db-query` by absolute path still hits the main stack no matter what directory you are standing in — that is what keeps `bin/bug-pipeline-check` (cron-invoked with an arbitrary working directory) reading the live main-stack `ibl_bug_reports`.

It announces the resolved target on **stderr** on every run (`db-query: target = main stack (127.0.0.1:3306)` or `db-query: target = container ibl5-db-<slug>`). Nothing is added to stdout — callers that parse rows with `grep -E '^\|'` are unaffected.

**There is no silent fallback.** If a worktree's DB container is missing or stopped, `db-query` exits **3** and names the container and the `bin/wt-up <slug>` that starts it, rather than answering from a different database. Set **`DB_QUERY_TARGET=main`** to force the main stack from anywhere; any other value is rejected with exit **2**. An unset or empty `DB_QUERY_TARGET` means "route normally".

**On `ERROR 1049` (unknown database), `db-query` self-diagnoses.** It prints the resolved `$dbname`, the absolute `config.php` it was read from, whether `DB_NAME` was set in the environment, and the databases that do exist. On a host shell `DB_NAME` is normally **unset**, so the file's fallback *is* the value that gets used — the env line is there to tell you the exceptions (`php -r` inherits the shell's environment, so an exported `DB_NAME` overrides the fallback). Read that block instead of assuming the container is down. The diagnostic fires **only** for 1049 — an ordinary bad-table or syntax error still just prints MariaDB's message — and the script's exit status is still MariaDB's.

The companion guard is in `materialize_worktree_config` (`bin/lib/git-helpers.sh`), which copies main's `config.php` into every new worktree: when no `DB_NAME` is in the environment it echoes the `$dbname` fallback it just propagated. It validates nothing (the file is deliberately league-agnostic) — it only makes the inherited name visible, since a stale fallback in the main checkout otherwise fans out silently to every worktree created afterwards.

## Migration Runner

```bash
bin/db-migrate <db-container> <migrations-dir> [db-name]
```

Runs pending SQL migrations against a Docker MariaDB container. Tracks applied migrations in `schema_migrations(version VARCHAR PRIMARY KEY)`. Idempotent — skips already-applied migrations. Optional third arg overrides the target database (defaults to `iblhoops_ibl5`). Used internally by `bin/wt-up` and `bin/db-test-up`.

## Local Database Integration Tests

```bash
bin/db-test-up [worktree-name] [--no-run]
```

Bootstraps a sibling `ibl5_test` database for `phpunit --group database` runs. Drops and recreates `ibl5_test` each run; never touches `iblhoops_ibl5`. Without arguments, uses the main checkout Docker environment. With a worktree name, uses the worktree's Docker stack. `--no-run` bootstraps the database only.

**In a worktree, ALWAYS run DB-integration tests via `bin/db-test-up <slug>` — never hand-run `DB_HOST=127.0.0.1 vendor/bin/phpunit --group database` from the host.** Only the main stack's `ibl5-mariadb` publishes host port 3306; worktree DB containers (`ibl5-db-<slug>`) are reachable on the Docker network only. So a host-side `127.0.0.1` connection silently targets the **main** stack's `ibl5_test` — typically a stale DB from an earlier run — producing phantom failures (wrong counts, duplicate-key errors) that don't reproduce in CI. `bin/db-test-up <slug>` runs phpunit inside the worktree php container against `DB_HOST=db`, hitting the freshly-built worktree DB. Verify port ownership with `docker ps --format '{{.Names}} {{.Ports}}' | grep 3306`.

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

## Multiple Claude Instances Protocol

Other Claude instances may be working in this directory simultaneously.

1. **Before editing a file:** Run `git status`. If the file has unstaged changes you didn't make, alert the user before proceeding.
2. **Scope discipline:** Only modify files directly related to your task. If you need to change a shared file, confirm with the user first.
3. **Before staging:** Run `git diff --name-only` and only stage files you personally modified. Never use `git add .` or `git add -A`.
4. **Testing:** Always run the full test suite, even if other instances may have partial work in progress. If another instance's in-progress changes cause failures in files you did not touch, note them but do not suppress them.
