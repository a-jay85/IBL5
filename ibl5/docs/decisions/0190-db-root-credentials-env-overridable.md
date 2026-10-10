---
description: ADR-0190. Local Docker DB user, password and default database name live in bin/lib/db-helpers.sh as env-overridable constants, pinned by bin/test-db-sync-prod-argv.
last_verified: 2026-10-08
---

# ADR-0190: Env-overridable DB root credentials and default database name

**Status:** Accepted
**Date:** 2026-10-08

## Context

`bin/db-sync-prod`, `bin/db-migrate`, `bin/db-test-up`, `bin/wt-up` and `bin/e2e-wt` each spelled `root`/`root`/`iblhoops_ibl5` inline. Changing the local Docker credentials or database name meant editing every copy. No test pinned the exact `docker exec` argv these scripts send, so a refactor could silently change a command.

## Decision

`bin/lib/db-helpers.sh` defines `DB_ROOT_USER`, `DB_ROOT_PASS` and `DB_DEFAULT_NAME`. Each defaults to the old literal and can be overridden from the environment under its own name. The database constant is `DB_DEFAULT_NAME` because `DB_NAME` is already an exported variable in bug-pipeline scripts and CI jobs. `db_cmd <container> <client> [args...]` wraps `docker exec` with these credentials and keeps stdout and stderr separate. `bin/test-db-sync-prod-argv` stubs `docker` on `PATH` and pins the argv at the default values, the override path, and the ambient `DB_NAME` case. It runs in `.github/workflows/tests.yml`.

## Alternatives Considered

- Name the constant `DB_NAME`. Rejected because the ambient exported `DB_NAME` would retarget local scripts without anyone asking.
- Route every caller through `db_exec`. Rejected because `db_exec` merges stderr into stdout, which corrupts a redirected dump.

## Consequences

- Positive: changing the local credentials or database name is a one-line edit or an env var.
- Positive: the argv test fails CI if a caller's command drifts at the default values.
- Negative: an exported `DB_DEFAULT_NAME` in the calling shell retargets `bin/db-sync-prod` and `bin/db-migrate`.

## References

- `bin/lib/db-helpers.sh`
- `bin/test-db-sync-prod-argv`
- `bin/db-sync-prod`
- `bin/db-migrate`
- `bin/db-test-up`
- `bin/wt-up`
- `bin/e2e-wt`
