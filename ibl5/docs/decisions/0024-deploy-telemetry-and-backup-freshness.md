---
description: Adds deploy-step sentinel telemetry, enriched failure notifications, and a deploy-rehearsal HTTP probe to CI/CD workflows.
last_verified: 2026-05-14
---

# ADR-0024: Deploy Telemetry and Rehearsal HTTP Probe

**Status:** Accepted
**Date:** 2026-05-14

## Context

Two gaps in the master-to-production deploy pipeline:

1. **Failure notifications lack context.** When `notify-deploy-failure` fires a Discord DM, operators must open the run log to identify which step failed, what migration was last applied, and what SHA is live.
2. **Deploy rehearsal only validates migrations.** `deploy-rehearsal.yml` catches migration and schema assertion failures but not HTTP-level breakages (syntax errors, missing routes, broken controllers).

Additionally, `smoke-prod.yml`'s `rollback-and-notify` job performed a re-smoke after revert that was unreachable correctly — it ran against the runner checkout rather than waiting for the revert deploy to land. The `workflow_run`-triggered smoke already covers post-revert verification.

## Decision

### Deploy-step sentinel + enriched notifications

Each SSH step in `build-and-deploy` writes its step name to `/tmp/ibl5-last-deploy-step` on the prod box before executing. On failure, `notify-deploy-failure` SSHes in and reads the sentinel, last migration, and deployed SHA. Discord DMs now contain the failing step name, commit, last migration, and live SHA.

The misleading re-smoke in `smoke-prod.yml` is removed. Post-revert health is verified by the fresh `workflow_run`-triggered smoke that fires from the revert's own `Build and Deploy` run.

### Deploy-rehearsal HTTP probe

After the existing migration + schema validation, `deploy-rehearsal.yml` now seeds the rehearsal database, boots a PHP built-in server, and runs the IBL5 smoke battery against it. A new `SMOKE_REHEARSAL_MODE` env var in `bin/smoke-prod` broadens the API accept-list (404 is valid without `.htaccess` rewrites) and skips the CSS asset check (no Tailwind build in rehearsal).

## Alternatives Considered

- **Inline sentinel in step names (use YAML step name metadata)** — GitHub Actions doesn't expose the failing step name as an output or env var in `if: failure()` jobs. The sentinel file is the simplest reliable approach.
- **Use `php -r` to read the last migration via config.php** — considered but rejected in favor of `mysql -N -e` to avoid nested escaping issues (triple-quoted PHP inside single-quoted SSH inside YAML).

## Consequences

- Positive: operators diagnose deploy failures from the Discord DM without opening the run log.
- Positive: HTTP-level regressions (broken routes, syntax errors) are caught before production.
- Negative: deploy-rehearsal adds ~30s (PHP server boot + smoke checks) to every rehearsal run.

## References

- `.github/workflows/main.yml` — sentinel writes, enriched notifications
- `.github/workflows/smoke-prod.yml` — removed re-smoke steps
- `.github/workflows/deploy-rehearsal.yml` — seed, boot, smoke, teardown steps
- `bin/smoke-prod` — `SMOKE_REHEARSAL_MODE` env var
- Pre-migrate backup freshness gate reverted 2026-05-14: SSH-based freshness check could not see JetBackup 5's snapshot directory; weekly/monthly JetBackup snapshots cover the recovery need for now.
