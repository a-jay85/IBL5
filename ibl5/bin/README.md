# `ibl5/bin/` — app-scoped tooling

Scripts here need the **PHP application** — Composer autoload, `config.php`, a
DB connection, or PHPStan/coverage baselines — **or** are invoked **inside the
Docker container**.

> **Why these can't move to the repo-root `bin/`:** `docker-compose.yml`
> bind-mounts only `./ibl5` → `/var/www/html/ibl5`. Scripts run via
> `docker exec … /var/www/html/ibl5/bin/<x>` (e.g. `validate-schema` from
> `bin/wt-up`, `warm-cache` from `docker/entrypoint.sh`) are **physically
> pinned** to this folder — a symlink in `bin/` can't relocate the real file
> out of the mount.

## What belongs here

| Group | Scripts |
|-------|---------|
| Migrations (app context) | `migrate`, `migrate-seed`, `run-migrations-ci.sh`, `validate-schema` |
| PHPStan / coverage gates | `check-baseline-drift`, `check-coverage`, `check-coverage-regression`, `check-new-class-coverage`, `check-infection-excludes` |
| Cache ops (run in container) | `warm-cache`, `purge-page-cache`, `rebuild-record-holders-cache` |
| E2E / visual regression | `e2e-local.sh`, `visual-regression.sh` |
| DB CLI | `db-query` (symlinked from `bin/db-query`) |

## What does NOT belong here

- Repository / git / worktree / CI-orchestration / prod-ops tooling that runs
  on the host → **`bin/`** (repo root).
- PHP admin / data-operation entry points (imports, data fixes, key
  generation) → **`ibl5/scripts/`**.

See **`../../bin/README.md`** for the repo-level folder.
