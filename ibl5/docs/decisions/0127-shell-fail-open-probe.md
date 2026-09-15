---
description: When a shell probe's exit status gates a destructive action, never use || echo <default> to swallow failure — use an explicit abort instead.
last_verified: 2026-09-14
---

# ADR-0127: Shell fail-open probe convention

**Status:** Accepted
**Date:** 2026-09-14

## Context

PR #2220 added a preserve-tables probe to `bin/db-sync-prod` to skip the prod dump's DROP
TABLE for tables that hold local-only state (`ibl_api_keys`, `ibl_bug_pipeline_state`, etc.).
The initial probe used `|| echo 0` to supply a default on failure:

```bash
_exists="$(docker exec "$DB_CONTAINER" mariadb ... 2>/dev/null || echo 0)"
```

That pattern converts every failure mode — container unreachable, auth blip, SIGPIPE,
timeout — into the "table absent" default, which causes the destructive action (DROP TABLE)
to fire on a resource that is actually present but temporarily unreachable. The surrounding
code comment even correctly avoided the `pipefail` + `grep` SIGPIPE hazard, while recreating
the identical data-loss hazard on a different error path.

## Decision

When a shell probe's exit status determines whether a destructive action fires — a `DROP
TABLE`, a `rm -rf`, a file overwrite, or an exclusion that lets a prod dump through — the
probe must be **fail-closed**: a non-zero exit must abort the script, not silently produce a
safe-looking default.

The required pattern:

```bash
if ! _exists="$(docker exec "$DB_CONTAINER" mariadb -uroot -proot -N -B \
    -e "SELECT COUNT(*) FROM information_schema.tables ..." \
    2> >(db_strip_warnings >&2))"; then
    printf 'ERROR: probe failed for %s — aborting.\n' "$resource" >&2
    exit 1
fi
```

`2>/dev/null` is permitted only on informational lookups where a default is genuinely safe.
The test is: "if the probe errors and returns the default, does the script proceed to destroy
or overwrite something it should have protected?" If yes, the fail-closed pattern is required.

Mechanically enforced by `.claude/rules/shell-fail-open-probe.md`.

## Alternatives Considered

- **Keep `|| echo 0`, add a pre-flight container check** — a separate reachability check
  still races; if the container goes down between the check and the probe, the window
  survives. Rejected: defence-in-depth, not defence.
- **Wrap the whole sync in `set -e`** — `set -e` has too many silent gotchas in bash
  (subshell, `&&`/`||` chains) to be reliable on its own. Rejected: the per-probe pattern
  is explicit and auditable.

## Consequences

- Positive: a transient container outage aborts the sync rather than silently destroying
  local-only state.
- Positive: the failure mode is visible (stderr message names the table) rather than silent.
- Negative: a sync attempted while the DB container is initialising will abort; the operator
  must retry once the container is ready.

## References

- `.claude/rules/shell-fail-open-probe.md` — operational rule operationalising this decision
- `bin/db-sync-prod` — first application of the fail-closed pattern (PR #2220)
- `ibl5/docs/decisions/0080-mac-local-discord-bug-pipeline-cron-topology.md` — context for
  the preserve-tables design that exposed this hazard
- `ibl5/docs/decisions/0123-shell-pipefail-grep-rule.md` — adjacent shell-probe safety rule
