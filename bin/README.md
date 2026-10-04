# `bin/` — repo-level tooling (host only)

Scripts here operate on the **repository, git, worktrees, CI orchestration,
automouse automation, and remote prod ops**. They run from the **host** at the
repo root and never need the PHP application's autoloader or the Docker
container.

## Who runs a script

Every script here serves one of four audiences. The name prefix tells you the
first one. For the other three, read the script's header comment and `--help`.

| Audience | How to spot it | Safe to run by hand? |
|----------|----------------|----------------------|
| Gate or harness | `check-*` (read-only gate) or `test-*` (self-contained harness) | Yes. A gate reads and reports with exit code 0, 1, or 2 (see Check-script conventions below). A harness builds its own temp fixtures. |
| CI-invoked | Unprefixed, called from `.github/workflows/` or `.github/actions/`. This includes runner-side scripts CI ships to the prod host, such as `predeploy-db-snapshot` and `rehearsal-prod-dump`. | Read the header first. Some update remote branches, post to Discord, or act on the prod host. |
| Operator | Unprefixed, run by a person: `wt-new`, `e2e-for-file`, `merge-master-to-prod`, `rollback-phantom-repair`. | Yes. That is what they are for. Some act on prod, so read `--help` first. |
| Automation | launchd or cron jobs: `*-tick`, `*-cron-setup`, `automouse/*`, `burndown-loop`, `docfix-poll`. | Run a `*-tick` by hand only to debug it. A `*-cron-setup` installs or replaces a job on this machine. |

A script can serve two audiences. `wt-new` is an operator tool that CI also
runs, and `merge-master-to-prod` is the manual twin of the promotion CI runs.

### The prefix is a hint

Many unprefixed scripts also run in CI, and some `check-*` scripts run in no
workflow. A missing prefix does not mean a script is meant for humans only.
Use the commands below to get the live answer.

Moving the CI scripts into a `bin/ci/` (example) subdirectory was considered and
rejected. Workflows, actions, hooks, docs, and tests name these scripts by path
in hundreds of places, and every one of those references would have to change.

### Naming a new script

- A read-only gate that exits 0, 1, or 2 is named `check-<what>`.
- A test harness for another script is named `test-<target>`.
- Any other script is unprefixed. The first lines of its header comment say
  who runs it: CI, an operator, or an automation job.
- A script that behaves differently when `GITHUB_ACTIONS` or `CI` is set says
  so in its header and in its `--help` text.
- A script that changes shared remote state (a remote branch, the prod host,
  gh-pages) offers `--dry-run` or requires an explicit flag before it acts.

### Finding who runs an existing script

Run these from the repo root in bash. They read the live tree, so the answer
never goes stale.

Unprefixed scripts that a workflow or composite action calls:

```bash
for f in bin/*; do
  n=${f#bin/}
  [ -f "$f" ] || continue
  case "$n" in check-*|test-*|*.md) continue ;; esac
  grep -rhoE "(ibl5/)?bin/${n}([^A-Za-z0-9_.-]|\$)" \
    .github/workflows .github/actions \
    | grep -qv '^ibl5/' && echo "$n"
done
```

The boundary `([^A-Za-z0-9_.-]|$)` keeps `bin/test` from matching
`bin/test-bin-help`. The `ibl5/` filter drops `ibl5/bin/db-query` style
references and keeps forms like `./bin/wt-new`.

Scripts that read a CI environment variable (`GITHUB_*` or `CI`), which are the
ones most likely to act differently when you run them locally:

```bash
for f in bin/*; do
  [ -f "$f" ] || continue
  grep -qE 'GITHUB_[A-Z_]+|\$\{?CI([^A-Za-z0-9_]|$)' "$f" \
    && echo "${f#bin/}"
done
```

`check-*` gates that no workflow or composite action calls directly (a script
may still call them, as `promote-master-to-production` calls
`check-master-ci-green`):

```bash
for f in bin/check-*; do
  n=${f#bin/}
  grep -rqE "${n}([^A-Za-z0-9_.-]|\$)" \
    .github/workflows .github/actions || echo "$n"
done
```

## What belongs here

| Group | Scripts |
|-------|---------|
| Worktrees | `wt-new`, `wt-up`, `wt-down`, `wt-list`, `wt-rebase`, `wt-remove`, `wt-db-test`, `e2e-wt` |
| Automouse automation | `automouse/run`, `automouse/queue`, `automouse/queue-reorder-ui`, `automouse/self-heal`, `automouse/prompt-impl`, `automouse/prompt-postplan`, `watch-automouse-plan` (wait for a queued plan's phase to finish, then DM) |
| Notifications | `discord-dm` (owner notices post to the #dev webhook (opt-in `--ping` for failures); other recipients and `--raw --route` stay on the IBLbot DM; retries + spool; the sibling of `.github/actions/notify-discord`) |
| Quality gates (sample; see Who runs a script for what CI calls) | `adr-check`, `check-docs`, `check-prose`, `check-hot-files`, `check-master-ci-green`, `check-plan`, `check-plan-staleness`, `check-e2e-hygiene`, `check-e2e-fa-offers-owner`, `check-e2e-mutator-isolation`, `check-e2e-fixture-drift`, `check-destructive-migrations`, `check-old-code-compat`, `refactor-flag` |
| Prod ops | `db-sync-prod`, `log-fetch-prod`, `promote-master-to-production` (the promotion primitive CI runs; `--dry-run` to rehearse), `merge-master-to-prod` (manual/emergency promotion), `smoke-prod` (SSH from host); `iblbot-healthcheck` (pm2 cron watchdog, runs on the prod box) |
| Dev / Docker env | `dev-up`, `db-test-up`, `db-migrate` |
| Scaffolding | `next-adr`, `next-migration`, `generate-codebase-map`, `sync-branches` |
| Lighthouse | `lighthouse-audit-report`, `lighthouse-audit-urls`, `lighthouse-comment` |
| E2E dispatch | `e2e-for-file`, `e2e-for-pr` |
| Shared helpers | `lib/` (`db-helpers.sh`, `git-helpers.sh`, `wt-guards.sh`, `automouse-stream-filter.sh`) |

### Promotion to production is automatic

`master` fast-forwards to `production` on its own whenever a master SHA goes
all-green on CI — `.github/workflows/promote-to-production.yml` does it, and
the push to `production` is what triggers `Build and Deploy`. You get a Discord
DM on every promotion, on every hard failure, and when a push arrives while the
automation is paused. See [ADR-0112](../ibl5/docs/decisions/0112-auto-promote-master-to-production.md).

**Pause it** (takes effect on the next push; no PR, no deploy):

    gh variable set AUTO_PROMOTE_PAUSED --body 1

**Resume it:**

    gh variable set AUTO_PROMOTE_PAUSED --body 0

**Check it:**

    gh variable get AUTO_PROMOTE_PAUSED

While paused, promote by hand with `bin/merge-master-to-prod`. Only the exact
value `1` pauses the automation — unset, `0`, `true`, and `yes` all leave it
running, which is deliberate: the pause must be a decision, never a typo.

A docs-only merge does **not** self-promote (too few check-runs exist on such a
SHA to satisfy the green gate), so it ships with the next code merge, or
immediately via `bin/merge-master-to-prod`.

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

## Every script answers `--help`

Run `bin/<script> --help` to see its arguments. Help goes to stdout and exits
0. The check sits at the top of the script, before any `source`, `git`, `cd`,
or network call, so asking for help never does anything else. Add it to every
new script. `bin/test-bin-help` enforces this in CI. It skips `test-*` harnesses,
`bin/check-composite-contracts` (built-in skip list), and any script whose interpreter is
absent on the runner.

## Check-script conventions

Applies to `bin/check-*` (Bash) and `ibl5/bin/check-*` (PHP) — both sets follow
this de-facto standard.

### Exit codes

| Code | Meaning | Examples |
|------|---------|---------|
| `0` | Pass — no violations | `bin/check-plan` prints `check-plan: OK (...)` then exits 0; `ibl5/bin/check-baseline-drift` prints `PASSED: ...`; advisory listing mode (`bin/check-hot-files`, `ibl5/bin/check-new-class-coverage`) exits 0 |
| `1` | Violations / drift detected | `bin/check-plan` cats violations then exits 1; `bin/check-plan-staleness` prints `STALE: <token>` then exits 1; `ibl5/bin/check-baseline-drift` prints `FAILED: ...`; `bin/check-docs` prints `FAIL <path>` |
| `2` | Usage / environment error | `bin/check-master-ci-green` exits 2 when `gh` is not authenticated — distinct from a content failure |

### Output channels

- **stdout** — violation lines and pass/summary lines.
- **stderr** (`>&2`) — diagnostic, usage, and environment errors.

Violation lines are prefixed with an **UPPERCASE tag** for grep-ability:
`STALE:`, `FLAG:`, `INCREASE:`, `FAILED:`, `ERROR:`, `FAIL`.

### Bash preamble

Bash check scripts open with `set -euo pipefail`. PHP check scripts compute an
`$exitCode` integer and call `exit($exitCode)`.

### CI consumption

CI gates key off the exit code: `0` = passes the gate, non-zero = fails. `exit 2`
lets CI distinguish a genuine violation (`1`) from a broken environment (`2`).
