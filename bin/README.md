# `bin/` — repo-level tooling (host only)

Scripts here operate on the **repository, git, worktrees, CI orchestration,
nightly automation, and remote prod ops**. They run from the **host** at the
repo root and never need the PHP application's autoloader or the Docker
container.

## What belongs here

| Group | Scripts |
|-------|---------|
| Worktrees | `wt-new`, `wt-up`, `wt-down`, `wt-list`, `wt-rebase`, `wt-remove`, `wt-db-test`, `e2e-wt.sh` |
| Nightly automation | `nightly-run`, `nightly-queue`, `nightly-prompt-impl`, `nightly-prompt-postplan` |
| CI / quality gates | `adr-check`, `check-docs`, `check-hot-files`, `check-master-ci-green`, `check-plan-staleness`, `check-vr-coverage`, `check-e2e-hygiene`, `check-e2e-fa-offers-owner`, `check-destructive-migrations`, `refactor-flag` |
| Prod ops (SSH) | `db-sync-prod`, `log-fetch-prod`, `merge-master-to-prod`, `smoke-prod` |
| Dev / Docker env | `dev-up`, `db-test-up`, `db-migrate` |
| Scaffolding | `next-adr`, `next-migration`, `generate-codebase-map`, `sync-branches` |
| Lighthouse | `lighthouse-audit-report`, `lighthouse-audit-urls`, `lighthouse-comment` |
| E2E dispatch | `e2e-for-file`, `e2e-for-pr` |
| Shared helpers | `lib/` (`db-helpers.sh`, `git-helpers.sh`, `wt-guards.sh`, `nightly-stream-filter`) |

## What does NOT belong here

- Anything that needs the PHP app (Composer autoload, `config.php`, a DB
  connection) **or** is invoked inside the Docker container → **`ibl5/bin/`**.
- PHP admin / data-operation entry points (imports, data fixes, key
  generation) → **`ibl5/scripts/`**.

`bin/db-query` is a symlink to `ibl5/bin/db-query` so the DB CLI is reachable
from both paths. See **`ibl5/bin/README.md`** for the app-scoped folder.
