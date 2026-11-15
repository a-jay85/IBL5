---
description: The Plan checks job now runs a plan's DB, web, bun, actionlint and container matrix rows, starting MariaDB, the CI web stack, bun and actionlint only when a row in the PR-body block needs them; prefire-verify runs only Mac-only rows.
last_verified: 2026-11-16
---

# ADR-0255: Run DB, web, bun and container matrix rows in the Plan checks job

**Status:** Accepted. Supersedes in part ADR-0244 (`ibl5/docs/decisions/0244-plan-checks-ci-from-pr-body.md`): its routing rule, and its consequence that DB rows still need the local worktree stack.
**Date:** 2026-11-15
**Deciders:** ajaynicolas
Related: ADR-0244 (Plan checks from the PR body), ADR-0253 (prefire shell stage)

## Context

Under ADR-0244, `row_runs_in_ci` in `bin/lib/plan-matrix-rows.sh` kept a row local when it touched the DB, a URL, docker or bun. Those rows ran in `bin/lib/prefire-verify` on the Mac, against the worktree's Docker stack. A plan with DB rows could not fire until that stack was up, and the rows never ran in a required check. In the frozen routing table in `bin/test-plan-checks-block`, the old rule sent 63 of 66 rows to the Mac.

## Decision

- **Routing.** `row_runs_in_ci` stays the only predicate. Its local regex, `PLAN_ROW_LOCAL_RE`, now lists only Mac-only markers: `gh`, Playwright, npx and npm, wget, the `bin/wt-*`, automouse, post-plan, plan-now, check-plan, db-query and db-test-up scripts, launchctl, osascript, ssh, scp, rsync, `~/`, `$HOME`, `/Users/`, `/tmp/`, `claude-plans`, `PLAN` and `../`. Docker is allowed only as `docker compose exec -T php `. The first-word allowlist adds `php`, `actionlint`, `test`, `[`, `printf`, `seq`, `diff`, `curl`, `bun`, `bunx`, `docker compose`, `bin/run-shell-harnesses` and `bin/generate-codebase-map`. A row that matches neither list stays local.
- **Needs.** `row_ci_needs` prints the stacks a CI row needs: `db` for a `PLAN_ROW_DB_RE` match; `web` and `db` for a URL, `curl`, or the compose-php form; `bun` for a `bun` or `bunx` first word; `actionlint` for an `actionlint` first word. `bin/lib/plan-checks-block needs` prints the union as `db=`, `web=`, `bun=` and `actionlint=` lines. An absent or corrupt block prints four `=0` lines.
- **Stacks.** The `needs` step in `.github/workflows/plan-checks.yml` writes those lines to `$GITHUB_OUTPUT`. Each stack step carries a step-level `if:` on its output:
  - MariaDB from `docker-compose.ci.yml`, migrated with `ibl5/bin/run-migrations-ci` and seeded with `tests/DatabaseIntegration/Fixtures/db-seed.sql`.
  - The web stack from `docker-compose.ci.yml` on `localhost:8080`, with the e2e `config.php`.
  - `oven-sh/setup-bun` and `bun install`.
  - Go and actionlint, at the same pins as `tests.yml`.
  A teardown step runs `docker compose down -v`, and its errors never turn the job red.
- **Host rewrite.** `ci_host_rewrite` maps `http://<slug>.localhost/` and bare `localhost` or `127.0.0.1` URLs to `localhost:8080`. `do_ci` applies it at run time, to the row commands and to the check field of each mutation record only.
- **Container rows.** The ci step sets `COMPOSE_FILE=docker-compose.ci.yml`, so `docker compose exec -T php ...` reaches the CI php container. After such a row, `do_ci` gives root-owned files under `ibl5/` back to the runner user.
- **Readiness markers.** Each stack step appends `IBL_CI_DB_READY=1`, `IBL_CI_WEB_READY=1` or `IBL_CI_BUN_READY=1` to `$GITHUB_ENV`. `do_ci` fails a row whose stack's marker is not exactly `1`, and names the missing marker.
- **P6.** `bin/check-composite-contracts` P6 fails the workflow when the `needs` step is missing or runs late, when a stack step lacks a `steps.needs.outputs.` guard or runs after the ci step, or when a `docker login` has no `docker logout` before the ci step.
- **P4.** Both images (`ghcr.io/a-jay85/mirror/mariadb:10.11` and `ghcr.io/a-jay85/ibl5/php-apache:latest`) pulled anonymously on 2026-11-15 (`docker manifest inspect` after `docker logout ghcr.io`). The job adds no `packages: read` and no login step, and P4 is unchanged.
- **Prefire.** The DB probe in `bin/lib/prefire-verify` skips rows that `row_runs_in_ci` sends to CI, so a fire with only CI DB rows no longer needs the local DB.
- **Concurrency rows.** A `seq N | xargs -P 16` row runs as written on the 4-vCPU runner. It tests contention, which does not need 16 cores.

## Measurement

On 2026-11-15 I took the last 10 successful Plan checks runs and every job on the same head SHAs:

| Number | Seconds |
|--------|---------|
| Plan checks median (20 runs) | 63 |
| Database Integration Tests "Apply migrations" median | 7 |
| Database Integration Tests "Import seed data" median | 0 |
| Slowest other job median: E2E Tests (1/1) | 359 |

The e2e `docker compose up` and `bun install` steps run inside the `setup-docker-e2e` composite action and a cache-gated step, so the run API reports no separate time for them. Even if they take 180 s together, the projected Plan checks time is about 250 s, below 359 s. Verdict: no sharding.

## Alternatives Considered

- Start MariaDB, the web stack and bun on every PR. Rejected because it adds minutes to each run, including the many PRs with no DB rows.
- Rewrite the host in the encoder. Rejected because the PR body would then hold CI-only URLs, and the local and CI views of a row would differ.
- Shard the rows over a job matrix with an aggregator named `Plan checks`. Rejected for now by the measurement above. It would also need a P3 change, since the aggregator needs `if: always()`.

## Consequences

- Positive: DB, web, bun and container rows run in a required check that fails closed.
- Positive: a fire with only CI DB rows no longer needs the worktree stack.
- Negative: a moved row no longer runs in prefire, so its first verdict arrives with CI.
- Negative: a row asserting Mac dev-DB data or the `_auto_login` cookie fails red on the PR. The CI DB holds only migrations and `db-seed.sql`.
- Neutral: a row the job cannot run fails the job, and the fix is to rewrite the row or mark it Mac-only.

## References

- `bin/lib/plan-matrix-rows.sh` (`row_runs_in_ci`, `row_ci_needs`, `ci_host_rewrite`)
- `bin/lib/plan-checks-block` (`needs`, `ci`)
- `bin/lib/prefire-verify` (DB probe)
- `.github/workflows/plan-checks.yml`
- `bin/check-composite-contracts` (P6)
- `bin/test-plan-checks-block`, `bin/test-prefire-verify`, `bin/test-check-composite-contracts`

## Addendum: what shipped (2026-11-16)

Review rounds on PR #3989 changed four parts of the Decision above before merge.

- **COMPOSE_FILE.** The Decision says the ci step sets `COMPOSE_FILE=docker-compose.ci.yml`. The shipped step sets `COMPOSE_FILE: ${{ github.workspace }}/docker-compose.ci.yml`, an absolute path. Docker compose resolves `COMPOSE_FILE` against the cwd, so a `cd ibl5 && docker compose exec -T php ...` row missed the relative file. `bin/lib/plan-checks-block ci` also pins any relative value to the repo root.
- **P7.** `bin/check-composite-contracts` gained P7. It fails when any `COMPOSE_FILE` value in `plan-checks.yml` is relative. The plan-checks group now has seven invariants, P1-P7.
- **ci_row_rewrite.** `do_ci` calls `ci_row_rewrite`, not `ci_host_rewrite`, on each row command and mutation check field. It rewrites only the arguments of curl, and of php, bun or bunx as a command word. Other text stays byte-exact, so a grep pattern or an expected URL keeps its host. `ci_host_rewrite` now ends a host at any character that cannot be part of a host name, such as `)`, `?`, `#`, a quote or a space. A host with its own `:port` stays as written.
- **Remote-host guard.** `row_runs_in_ci` keeps a curl row local when any URL in it names a host other than `localhost`, `127.0.0.1` or a `*.localhost` slug host. A curl call with no `scheme://` URL also stays local, because its host may be remote and `ci_host_rewrite` needs a scheme. Without the guard, CI would fetch production and its result would say nothing about the PR.
