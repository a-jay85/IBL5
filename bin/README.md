# `bin/` — repo-level tooling (host only)

Scripts here operate on the **repository, git, worktrees, CI orchestration,
automouse automation, and remote prod ops**. They run from the **host** at the
repo root and never need the PHP application's autoloader or the Docker
container.

## What belongs here

| Group | Scripts |
|-------|---------|
| Worktrees | `wt-new`, `wt-up`, `wt-down`, `wt-list`, `wt-rebase`, `wt-remove`, `wt-db-test`, `e2e-wt.sh` |
| Automouse automation | `automouse-run`, `automouse-queue`, `automouse-prompt-impl`, `automouse-prompt-postplan` |
| CI / quality gates | `adr-check`, `check-docs`, `check-hot-files`, `check-master-ci-green`, `check-plan`, `check-plan-staleness`, `check-vr-coverage`, `check-e2e-hygiene`, `check-e2e-fa-offers-owner`, `check-destructive-migrations`, `refactor-flag` |
| Prod ops (SSH) | `db-sync-prod`, `log-fetch-prod`, `merge-master-to-prod`, `smoke-prod` |
| Dev / Docker env | `dev-up`, `db-test-up`, `db-migrate` |
| Scaffolding | `next-adr`, `next-migration`, `generate-codebase-map`, `sync-branches` |
| Lighthouse | `lighthouse-audit-report`, `lighthouse-audit-urls`, `lighthouse-comment` |
| E2E dispatch | `e2e-for-file`, `e2e-for-pr` |
| Shared helpers | `lib/` (`db-helpers.sh`, `git-helpers.sh`, `wt-guards.sh`, `automouse-stream-filter`) |

## What does NOT belong here

- Anything that needs the PHP app (Composer autoload, `config.php`, a DB
  connection) **or** is invoked inside the Docker container → **`ibl5/bin/`**.
- PHP admin / data-operation entry points (imports, data fixes, key
  generation) → **`ibl5/scripts/`**.

`bin/db-query` is a symlink to `ibl5/bin/db-query` so the DB CLI is reachable
from both paths. See **`ibl5/bin/README.md`** for the app-scoped folder.

## Symlink strategy

The repo keeps its symlink surface deliberately tiny. **There is exactly one
tracked symlink:** `bin/db-query → ../ibl5/bin/db-query`.

**Why the real file lives in `ibl5/bin/`, not here:** `docker-compose.yml`
bind-mounts only `./ibl5` into the container, so any script that must run
**inside Docker** (or needs the PHP app's autoload / `config.php`) is physically
pinned to `ibl5/bin/` — a symlink in this folder cannot relocate the real file
out of the mount. `db-query` is such a script, so its source of truth is
`ibl5/bin/db-query`, and `bin/db-query` is a thin convenience symlink so the DB
CLI is reachable from the repo-root `bin/` path too.

**Convention for new symlinks:** prefer **not** to add them. If a tool needs to
be reachable from two paths, add a short wrapper that `exec`s the canonical
script, or relocate the script to its correct home (`bin/` vs `ibl5/bin/` vs
`ibl5/scripts/`). A `.symlinks` manifest is intentionally **not** maintained —
one tracked symlink does not warrant one.
