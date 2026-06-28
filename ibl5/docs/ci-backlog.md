---
description: CI/GitHub-Actions workflow simplification backlog — duplicated setup/notify boilerplate, job consolidation, and verified-not-redundant workflows, with per-entry status + automouse-readiness.
last_verified: 2026-06-28
---

# CI Workflow Simplification Backlog

**Purpose:** Catalogue `.github/workflows/` simplifications that reduce maintenance cost **without losing quality or fidelity**. Each open entry is a candidate for a `/plan`. Companion to [`maintenance-backlog.md`](maintenance-backlog.md) (uses the same status/automouse glyph language).

**Origin:** Full 30-workflow audit (2026-06-28) via parallel multi-agent read of every file in `.github/workflows/`. The headline finding: the *file count* (30) is mostly justified — the bloat is duplicated boilerplate copy-pasted across files, plus one composite action (`.github/actions/setup-php-env`) that already exists for the job but **is wired into zero workflows**.

---

## Disposition taxonomy

**Status** — where the fix stands:
- ✅ **Implemented** — merged; the named concern is resolved on `master` (verified on disk).
- ◑ **Partial** — partially addressed; residual named in the note.
- 📋 **Planned** — a plan file exists (queued or PR-open); not yet merged.
- ⬜ **Open** — no plan yet.
- 🚫 **Declined** — deliberately won't-do (rationale in the Status line).

**Automouse-readiness** (assigned only for items not ✅/🚫):
- 🟩 **Auto-mergeable** — behavior-preserving (green-green via existing CI) + no gate-14 trigger → arms auto-merge unattended.
- 🟦 **Automouse-safe, human-merge** — implementable unattended, but a gate-14 trigger (deploy/notify path, secrets handling) forces `auto_merge: false`.
- 🟨 **Conditional** — would become 🟩/🟦 with one added scope item or one upfront decision (named in the note).
- 🟥 **Not automouse-safe** — irreducible judgment or unverifiable-in-CI.

**Effort scale:** **S** — single PR, < 1 day. **M** — multi-step plan, 1-3 days. **L** — platform shift, likely needs an ADR.

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 7 |
| 📋 Planned | 2 |
| ✅ Implemented | 0 |
| 🚫 Declined | 0 |

> The 4 "verified-not-redundant" entries in Axis 4 are **decisions to keep**, not open work — they exist so a future audit does not re-flag them. Not counted above.

---

## Axis 1: Duplicated setup boilerplate (highest leverage)

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| 1.1 | Revive + adopt `setup-php-env` composite | 📋 Planned | 🟩 | M |
| 1.2 | Extract `notify-discord` composite (SSH + Discord DM) | 📋 Planned | 🟦 | M |

### 1.1 Dead `setup-php-env` composite; PHP setup hand-rolled 16×
**Location:** `.github/actions/setup-php-env/action.yml` (defined, **used by zero workflows**); duplicated blocks in `.github/workflows/tests.yml` (×5), `migration-safety.yml` (×3), `mutation.yml` (×2), `adr-required.yml`, `refactor-flag.yml`, `doc-freshness.yml`, `doc-freshness-audit.yml`, `deploy-rehearsal.yml`, `cache-dependencies.yml`.
**Problem:** The "Checkout / Setup PHP / cache vendor / composer install" sequence is hand-rolled **16 times across 9 files**. A composite action to collapse it already exists but was abandoned and has rotted: it pins `actions/cache@v4` (workflows use `@v6`), defaults PHP `8.3` (workflows use `8.5`), and omits the `shivammathur/setup-php` step itself. Meanwhile `cache-dependencies.yml` warms vendor with extensions `pdo, pdo_mysql` while every consumer uses `mysqli` — a latent cache-key/extension divergence a shared action would eliminate (see 3.5).
**Suggested direction:** Rewrite `setup-php-env` to own the full sequence (add the `setup-php` step + optional `config.php` creation as inputs, refresh action versions to match current usage), then adopt it across all 9 files. Mirrors the existing house pattern (`setup-docker-e2e`, `lighthouse-setup` composites).
**Risk if untouched:** A version/extension bump must be applied in 16 places; drift already present (3.5).
**Status (2026-06-28):** 📋 Planned — plan `ci-setup-php-env-composite` written + queued for automouse (own PR, split from 1.2). 🟩 auto-mergeable: behavior-preserving; verified at impl time by `actionlint -shellcheck=` (host-installed, not yet in CI — plain `actionlint` exits 1 on master from pre-existing shellcheck noise, so the gate is `-shellcheck=` + a no-new-findings delta) plus the self-triggering consumer workflows' own PR CI (`tests`/`refactor-flag`/`deploy-rehearsal`/`adr-required` glob/`doc-freshness` no-filter). The 4 non-self-triggering adopters (`migration-safety`/`mutation`/`doc-freshness-audit`/`cache-dependencies`) rely on impl-time `actionlint` + grep-asserted identical step logic.

### 1.2 No `notify-discord` composite; SSH + Discord DM hand-rolled
**Location:** "Setup SSH" block (`mkdir ~/.ssh` / write key / `chmod 600` / `ssh-keyscan`) appears **12× across 7 files** — `smoke-prod.yml` (×5), `db-backup.yml` (×2), `main.yml` (×2), `mutation.yml`, `log-review.yml`, `doc-freshness-audit.yml`, `deploy-rehearsal.yml`. The "Send Discord DM" block (`jq` payload + `ssh`+`curl` to the IBLbot `discordDM` endpoint) appears **8× across 6 files** — `smoke-prod.yml` (×4), `main.yml`, `mutation.yml`, `db-backup.yml`, `log-review.yml`, `doc-freshness-audit.yml`.
**Problem:** Two structurally identical blocks copy-pasted across the deploy/notify surface; the Discord blocks differ only in the `MSG` string. Each copy is a place a key-handling or endpoint change must be repeated.
**Suggested direction:** A `notify-discord` composite action taking `message` (and host/secret inputs) that owns both the SSH setup and the DM POST. Callers reduce to one `uses:` + a message string. Note `db-backup.yml`'s notify intentionally targets `secrets.HOST` (not the override) and `deploy-rehearsal.yml`'s SSH setup has a secret-empty guard — the composite must preserve both as inputs/options.
**Risk if untouched:** A secret rotation or endpoint change touches 12 SSH blocks + 8 DM blocks by hand; the `db-backup` heredoc remote logic is already un-shellchecked.
**Status (2026-06-28):** 📋 Planned — plan `ci-notify-discord-composite` written + queued for automouse (own PR, split from 1.1). Two composites (`setup-ssh` + `notify-discord`) across the **13 SSH + 9 Discord sites** the plan re-counted on disk. 🟦 `auto_merge: false`: the Discord-send + rollback-alert path runs only against prod (SSH + IBLbot `discordDM`), **unreachable from CI** — `actionlint` proves wiring/syntax but not that a prod DM actually sends; combined with the deploy/secrets surface, a human merges (gate-14 deploy/secrets + gate-15 data-blocked verification gap).

---

## Axis 2: Job-level consolidation

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| 2.1 | Collapse smoke-prod's 4 notify jobs into one | ⬜ Open | 🟦 | S |
| 2.2 | Merge migration-safety `idempotency-check` + `schema-completeness` | ⬜ Open | 🟩 | M |

### 2.1 smoke-prod.yml — four near-identical notify jobs
**Location:** `.github/workflows/smoke-prod.yml` — jobs `rollback-and-notify`, `notify-scheduled-failure`, `notify-ibl6-degradation`, `notify-inconclusive`.
**Problem:** Four separate jobs each boot a fresh runner solely to SSH-tunnel one `curl` DM; they differ only in the trigger condition and message string. (Same notify shape recurs in `main.yml`, `mutation.yml`, `db-backup.yml`.)
**Suggested direction:** Collapse the three notify-only jobs into one job that branches on the `smoke` outcome via `if:` (keep `rollback-and-notify` separate — it mutates git). Best done **after** 1.2 lands so the merged job calls the `notify-discord` composite.
**Risk if untouched:** Notify logic forks across 4 jobs; a message-format change is repeated.
**Status (2026-06-28):** ⬜ Open — sequence after 1.2. Deploy/notify surface → 🟦.

### 2.2 migration-safety.yml — three jobs each rebuild a full DB stack
**Location:** `.github/workflows/migration-safety.yml` — jobs `idempotency-check`, `schema-parity-check`, `schema-completeness`.
**Problem:** All three spin up an independent MariaDB 10.11 service, run an independent composer install, and apply the full migration stack from zero. `idempotency-check` and `schema-completeness` both apply the same full stack; the latter just adds FK/table/column assertions afterward.
**Suggested direction:** Merge `idempotency-check` into `schema-completeness` (one DB build, then both sets of assertions). Keep `schema-parity-check` separate — it needs two DBs by design. Costs some intra-workflow parallelism; nets fewer runner-minutes and one fewer composer install.
**Risk if untouched:** Three full migration runs per push to a migrations file; setup duplication (mitigated once 1.1 lands).
**Status (2026-06-28):** ⬜ Open — green-green (gate job unchanged, assertions preserved) → 🟩.

---

## Axis 3: Smaller cleanups & one correctness fix

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| 3.1 | audit-js runs `npm audit` with no install (vacuous pass) | ⬜ Open | 🟩 | S |
| 3.2 | db-backup redundant MariaDB wait loop | ⬜ Open | 🟩 | S |
| 3.3 | lighthouse-audit re-collects instead of reusing baseline artifact | ⬜ Open | 🟨 | S |
| 3.4 | `changes`-detection mechanism is inconsistent | ⬜ Open | 🟨 | M |
| 3.5 | PHP extension set divergence in cache-dependencies | ⬜ Open | 🟩 | S |

### 3.1 audit-js — `npm audit` without a prior install
**Location:** `.github/workflows/tests.yml`, job `audit-js`.
**Problem:** Runs `npm audit --audit-level=high` with no `npm ci`/`npm install` first, relying on the bare runner Node — it may pass vacuously (no lockfile-resolved tree to scan). This is a **correctness** gap, not just tidiness.
**Suggested direction:** Install deps (or point at the lockfile) before `npm audit`; or move JS audit onto the Bun toolchain the rest of the repo uses.
**Risk if untouched:** A high-severity JS advisory could slip through unflagged.
**Status (2026-06-28):** ⬜ Open — 🟩.

### 3.2 db-backup.yml — manual MariaDB wait loop on top of a health-check
**Location:** `.github/workflows/db-backup.yml`, job `backup`, step "Wait for verify MariaDB to be ready".
**Problem:** A 30×5s manual retry loop runs even though the service container already declares `--health-retries=10`; by the time steps run the service is healthy. Dead wait.
**Suggested direction:** Drop the loop; rely on the service health-check (as other DB-using workflows do).
**Risk if untouched:** Up to 150s of wasted wall-time per nightly run; misleading "wait" step.
**Status (2026-06-28):** ⬜ Open — 🟩.

### 3.3 lighthouse-audit.yml re-runs a full-site collect
**Location:** `.github/workflows/lighthouse-audit.yml` vs `.github/workflows/lighthouse-baseline.yml`.
**Problem:** Both do a full-site `lhci collect` with `numberOfRuns=1` over the same URL set (`bin/lighthouse-audit-urls`). The weekly audit could consume the `lighthouse-baseline-manifest` artifact the baseline workflow already uploads, instead of re-collecting.
**Suggested direction:** Have the weekly audit download + report on the latest baseline manifest where freshness allows; re-collect only if the artifact is stale/absent.
**Risk if untouched:** Duplicate 120-min-budget LHCI collect weekly. (Low priority — distinct outputs, see Axis 4.)
**Status (2026-06-28):** ⬜ Open — 🟨 (needs a freshness-window decision).

### 3.4 Inconsistent change-detection across workflows
**Location:** `dorny/paths-filter@v4` in `codeql.yml`/`engine.yml`/`eslint.yml`; `bin/website-affecting` git-diff in `e2e-tests.yml`/`lighthouse.yml`; static `paths:` filters elsewhere.
**Problem:** Three different mechanisms answer "did relevant files change?". Harder to reason about why a given workflow did/didn't run.
**Suggested direction:** Standardize where semantics allow (note `bin/website-affecting` encodes domain logic a static filter can't; not all are interchangeable). Modest payoff — defer unless it causes a miss.
**Risk if untouched:** Cognitive overhead; subtle trigger-gap bugs.
**Status (2026-06-28):** ⬜ Open — 🟨 (needs a per-workflow audit of which are truly interchangeable).

### 3.5 cache-dependencies.yml PHP extensions diverge from consumers
**Location:** `.github/workflows/cache-dependencies.yml` (`mbstring, intl, pdo, pdo_mysql`) vs every consumer (`mbstring, intl, mysqli`).
**Problem:** The cache warmer builds under a different PHP extension set than the workflows restoring the cache. Vendor is largely extension-agnostic so it works today, but it's a latent footgun. A shared `setup-php-env` (1.1) would force a single source of truth.
**Suggested direction:** Align the extension list; fold into 1.1.
**Risk if untouched:** A future extension-sensitive dep could cache-poison consumers.
**Status (2026-06-28):** ⬜ Open — 🟩 (subsumed by 1.1).

---

## Axis 4: Verified NOT redundant — decisions to keep (do not re-flag)

These look like duplicate workflows but each occupies a distinct, justified role. Recorded so a future audit does not propose merging them.

| Pair / group | Why kept separate |
|--------------|-------------------|
| `doc-freshness.yml` vs `doc-freshness-audit.yml` | PR/push runs on-touch staleness only (`--since`, `--no-staleness`); the nightly audit does the repo-wide sweep the PR workflow deliberately omits, emitting a GitHub issue + Discord DM. Complementary scopes. |
| `pr-collisions.yml` vs `pr-collisions-cron.yml` | Event-driven single-PR check (posts a sticky comment) vs daily `--sweep` of all open PRs (catches collisions that arise when another PR merges). Complementary triggers. |
| `lighthouse.yml` / `lighthouse-baseline.yml` / `lighthouse-audit.yml` | PR-delta + sticky comment / push-to-master baseline-artifact producer / weekly full-site GitHub issue. Distinct consumers and outputs. (Minor collect-dedup tracked as 3.3.) |
| The small gate workflows (`adr-required`, `refactor-flag`, `hot-files`, `orphan-css`, `e2e-hygiene`, `pr-collisions`, `human-signoff`, etc.) | Each is a separate **required-check name** with its own path filter and independent failure isolation. Folding into `tests.yml` would trade away granular required-checks + path-scoped triggering for nothing. Keep split. |

---

## Burn-down process

1. Pick an entry; `/plan` it (CI infra touching deploy/notify → expect `auto_merge: false`).
2. Implement in a worktree; the change is green-green when the existing CI suites pass unchanged.
3. Update this doc's status/readiness.
4. Bump `last_verified` (CI enforces via `bin/check-docs`).
