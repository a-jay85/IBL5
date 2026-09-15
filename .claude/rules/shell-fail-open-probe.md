---
description: Never use || echo <default> to swallow shell probe failure when the result gates a destructive action — convert probe errors into aborts, not into "resource absent".
last_verified: 2026-09-14
paths: "bin/**"
---

# Shell Fail-Open Probe

## Rule

When a shell probe's exit status determines whether a **destructive action** fires — a `DROP
TABLE`, a `rm -rf`, a file overwrite, a table exclusion that lets a prod dump through — **never
use `|| echo <default>` to swallow command failure**.

`|| echo <default>` converts every failure mode (container unreachable, auth blip, SIGPIPE,
timeout) into the default value, which is usually the "resource absent" path. That path then
proceeds to the destructive action on a resource that is actually present but temporarily
unreachable.

**Before (fail-open):**

```bash
_exists="$(docker exec "$DB_CONTAINER" mariadb -uroot -proot mydb -N -B \
    -e "SELECT COUNT(*) FROM ..." 2>/dev/null || echo 0)"
if [ "${_exists:-0}" != "0" ]; then
    # protect it
fi
# if the probe failed, _exists=0 → resource treated as absent → DROP TABLE fires
```

**After (fail-closed):**

```bash
if ! _exists="$(docker exec "$DB_CONTAINER" mariadb -uroot -proot mydb -N -B \
    -e "SELECT COUNT(*) FROM ..." 2> >(db_strip_warnings >&2))"; then
    printf 'ERROR: probe failed for %s — aborting.\n' "$resource" >&2
    exit 1
fi
if [ "${_exists:-0}" != "0" ]; then
    # protect it
fi
```

## Why

PR #2220 (`bin/db-sync-prod`): the preserve-tables probe used `|| echo 0`, meaning a docker
socket hiccup or mariadb auth blip returned `0` — "table absent" — so the prod dump's `DROP
TABLE` destroyed the protected local-only table. This is the exact data-loss path the PR existed
to close. The adjacent code comment even reasoned about `pipefail` + `grep` SIGPIPE (correctly
avoiding that pattern) while `|| echo 0` recreated the identical hazard on a different error path.

## Application

| What to look for | What to do |
|---|---|
| `cmd \|\| echo <value>` where `cmd` probes existence | Use `if ! _result="$(cmd ...)"; then exit 1; fi` |
| `2>/dev/null` silencing the probe | Route through `db_strip_warnings` or similar so the error cause is visible |
| Probe result used to gate DROP / rm -rf / exclusion-from-protection list | Test the probe's failure path explicitly (exit nonzero must abort, not proceed) |

## Calibration

Only applies when probe failure triggers a **destructive action**. `|| echo default` for a
best-effort informational lookup (e.g., `git rev-parse HEAD || echo unknown`) is fine.
The test is: "if the probe errors and returns the default, does the script proceed to destroy
or overwrite something it should have protected?" If yes, the fail-closed pattern is required.
