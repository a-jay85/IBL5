---
description: Historical archive: completed/declined CI workflow simplification entries, extracted from ci-backlog.md.
last_verified: 2026-07-11
---

# CI Workflow Simplification Backlog — Archive

Read-only historical record of ✅ Implemented / 🚫 Declined findings. For OPEN items see ../ci-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### 3.1 audit-js — `npm audit` without a prior install
**Location:** `.github/workflows/tests.yml`, job `audit-js`.
**Problem:** Runs `npm audit --audit-level=high` with no `npm ci`/`npm install` first, relying on the bare runner Node — it may pass vacuously (no lockfile-resolved tree to scan). This is a **correctness** gap, not just tidiness.
**Suggested direction:** Install deps (or point at the lockfile) before `npm audit`; or move JS audit onto the Bun toolchain the rest of the repo uses.
**Risk if untouched:** A high-severity JS advisory could slip through unflagged.
**Status (2026-07-11):** ✅ Implemented — added `npm ci` step before `npm audit` in `.github/workflows/tests.yml`; audit now scans the resolved lockfile tree. (#1419)

### 3.2 db-backup.yml — manual MariaDB wait loop on top of a health-check
**Location:** `.github/workflows/db-backup.yml`, job `backup`, step "Wait for verify MariaDB to be ready".
**Problem:** A 30×5s manual retry loop runs even though the service container already declares `--health-retries=10`; by the time steps run the service is healthy. Dead wait.
**Suggested direction:** Drop the loop; rely on the service health-check (as other DB-using workflows do).
**Risk if untouched:** Up to 150s of wasted wall-time per nightly run; misleading "wait" step.
**Status (2026-07-11):** ✅ Implemented — dropped the 30×5s `mysqladmin ping` loop from `.github/workflows/db-backup.yml`; MariaDB readiness is guaranteed by the service container health-check. (#1419)

### 3.3 lighthouse-audit.yml re-runs a full-site collect
**Location:** `.github/workflows/lighthouse-audit.yml` vs `.github/workflows/lighthouse-baseline.yml`.
**Problem:** Both do a full-site `lhci collect` with `numberOfRuns=1` over the same URL set (`bin/lighthouse-audit-urls`). The weekly audit could consume the `lighthouse-baseline-manifest` artifact the baseline workflow already uploads, instead of re-collecting.
**Suggested direction:** Have the weekly audit download + report on the latest baseline manifest where freshness allows; re-collect only if the artifact is stale/absent.
**Risk if untouched:** Duplicate 120-min-budget LHCI collect weekly. (Low priority — distinct outputs, see Axis 4.)
**Status (2026-07-11):** ✅ Implemented — `lighthouse-audit.yml` now downloads the `lighthouse-baseline-manifest` artifact and, when present and ≤7 days old, skips Docker + `lhci collect` and generates the report from the downloaded manifest; absent/stale falls back to the unchanged full-collect path. (#1421)
