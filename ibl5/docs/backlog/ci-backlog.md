---
description: CI/GitHub-Actions workflow simplification backlog — duplicated setup/notify boilerplate, job consolidation, and verified-not-redundant workflows, with per-entry status + automouse-readiness.
last_verified: 2026-07-14
---

# CI Workflow Simplification Backlog

**Purpose:** Catalogue `.github/workflows/` simplifications that reduce maintenance cost **without losing quality or fidelity**. Each open entry is a candidate for a `/plan`. Companion to [`maintenance-backlog.md`](maintenance-backlog.md) (uses the same status/automouse glyph language).

**Origin:** Full 30-workflow audit (2026-06-28) via parallel multi-agent read of every file in `.github/workflows/`. The headline finding: the *file count* (30) is mostly justified — the bloat is duplicated boilerplate copy-pasted across files, plus one composite action (`.github/actions/setup-php-env`) that already exists for the job but **is wired into zero workflows**.

---

## Disposition taxonomy

**Status** — canonical five-glyph set: see [README.md § Status taxonomy](README.md#status-taxonomy).

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
| ⬜ Open | 2 |
| 📋 Planned | 0 |
| ✅ Implemented | 8 |
| 🚫 Declined | 0 |

> The 4 "verified-not-redundant" entries in Axis 4 are **decisions to keep**, not open work — they exist so a future audit does not re-flag them. Not counted above.

---

## Axis 1: Duplicated setup boilerplate (highest leverage)

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| 1.1 | Revive + adopt `setup-php-env` composite | ✅ Implemented | 🟩 | M |
| 1.2 | Extract `notify-discord` composite (SSH + Discord DM) | ✅ Implemented | 🟦 | M |

### 1.1 Dead `setup-php-env` composite; PHP setup hand-rolled 16×
**Location:** `.github/actions/setup-php-env/action.yml` (defined, **used by zero workflows**); duplicated blocks in `.github/workflows/tests.yml` (×5), `migration-safety.yml` (×3), `mutation.yml` (×2), `adr-required.yml`, `refactor-flag.yml`, `doc-freshness.yml`, `doc-freshness-audit.yml`, `deploy-rehearsal.yml`, `cache-dependencies.yml`.
**Problem:** The "Checkout / Setup PHP / cache vendor / composer install" sequence is hand-rolled **16 times across 9 files**. A composite action to collapse it already exists but was abandoned and has rotted: it pins `actions/cache@v4` (workflows use `@v6`), defaults PHP `8.3` (workflows use `8.5`), and omits the `shivammathur/setup-php` step itself. Meanwhile `cache-dependencies.yml` warms vendor with extensions `pdo, pdo_mysql` while every consumer uses `mysqli` — a latent cache-key/extension divergence a shared action would eliminate (see 3.5).
**Suggested direction:** Rewrite `setup-php-env` to own the full sequence (add the `setup-php` step + optional `config.php` creation as inputs, refresh action versions to match current usage), then adopt it across all 9 files. Mirrors the existing house pattern (`setup-docker-e2e`, `lighthouse-setup` composites).
**Risk if untouched:** A version/extension bump must be applied in 16 places; drift already present (3.5).
**Status (2026-06-28):** ✅ Implemented — rewrote `.github/actions/setup-php-env/action.yml` to own `shivammathur/setup-php` + optional vendor cache / `config.php` / `composer install` (refreshed to `actions/cache@v6`, default PHP 8.5); adopted across all 10 workflows (17 call-sites). 3.5 folded in.

### 1.2 No `notify-discord` composite; SSH + Discord DM hand-rolled
**Location:** "Setup SSH" block (`mkdir ~/.ssh` / write key / `chmod 600` / `ssh-keyscan`) appears **12× across 7 files** — `smoke-prod.yml` (×5), `db-backup.yml` (×2), `main.yml` (×2), `mutation.yml`, `log-review.yml`, `doc-freshness-audit.yml`, `deploy-rehearsal.yml`. The "Send Discord DM" block (`jq` payload + `ssh`+`curl` to the IBLbot `discordDM` endpoint) appears **8× across 6 files** — `smoke-prod.yml` (×4), `main.yml`, `mutation.yml`, `db-backup.yml`, `log-review.yml`, `doc-freshness-audit.yml`.
**Problem:** Two structurally identical blocks copy-pasted across the deploy/notify surface; the Discord blocks differ only in the `MSG` string. Each copy is a place a key-handling or endpoint change must be repeated.
**Suggested direction:** A `notify-discord` composite action taking `message` (and host/secret inputs) that owns both the SSH setup and the DM POST. Callers reduce to one `uses:` + a message string. Note `db-backup.yml`'s notify intentionally targets `secrets.HOST` (not the override) and `deploy-rehearsal.yml`'s SSH setup has a secret-empty guard — the composite must preserve both as inputs/options.
**Risk if untouched:** A secret rotation or endpoint change touches 12 SSH blocks + 8 DM blocks by hand; the `db-backup` heredoc remote logic is already un-shellchecked.
**Status (2026-06-29):** ✅ Implemented (PR for ci-notify-discord-composite) — two composites (`setup-ssh`, `notify-discord`) extracted and adopted at all 13 SSH + 9 Discord sites; per-caller variants (guard, override-host, loud/soft) preserved. `auto_merge: false` — Discord/rollback-alert path is prod-only, unreachable from CI.

---

## Axis 2: Job-level consolidation

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| 2.1 | Collapse smoke-prod's 4 notify jobs into one | ✅ Implemented | 🟦 | S |
| 2.2 | Merge migration-safety `idempotency-check` + `schema-completeness` | ✅ Implemented | 🟩 | M |

➜ 2.1 smoke-prod.yml — ✅ Implemented (2026-07-11): see [archive](archive/ci-backlog-archive.md).

➜ 2.2 migration-safety.yml — ✅ Implemented (2026-07-11): see [archive](archive/ci-backlog-archive.md).

---

## Axis 3: Smaller cleanups & one correctness fix

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| 3.1 | audit-js runs `npm audit` with no install (vacuous pass) | ✅ Implemented | 🟩 | S |
| 3.2 | db-backup redundant MariaDB wait loop | ✅ Implemented | 🟩 | S |
| 3.3 | lighthouse-audit re-collects instead of reusing baseline artifact | ✅ Implemented | 🟩 | S |
| 3.4 | `changes`-detection mechanism is inconsistent | ✅ Implemented | 🟩 | M |
| 3.5 | PHP extension set divergence in cache-dependencies | ✅ Implemented | 🟩 | S |

➜ 3.1 audit-js — ✅ Implemented (2026-07-11): see [archive](archive/ci-backlog-archive.md).

➜ 3.2 db-backup.yml — ✅ Implemented (2026-07-11): see [archive](archive/ci-backlog-archive.md).

➜ 3.3 lighthouse-audit.yml — ✅ Implemented (2026-07-11): see [archive](archive/ci-backlog-archive.md).

➜ 3.4 Inconsistent change-detection across workflows — ✅ Implemented (2026-07-11): see [archive](archive/ci-backlog-archive.md).

### 3.5 cache-dependencies.yml PHP extensions diverge from consumers
**Location:** `.github/workflows/cache-dependencies.yml` (`mbstring, intl, pdo, pdo_mysql`) vs every consumer (`mbstring, intl, mysqli`).
**Problem:** The cache warmer builds under a different PHP extension set than the workflows restoring the cache. Vendor is largely extension-agnostic so it works today, but it's a latent footgun. A shared `setup-php-env` (1.1) would force a single source of truth.
**Suggested direction:** Align the extension list; fold into 1.1.
**Risk if untouched:** A future extension-sensitive dep could cache-poison consumers.
**Status (2026-06-28):** ✅ Implemented — 🟩 (subsumed by 1.1; cache-dependencies now inherits the composite default `mbstring, intl, mysqli`).

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

## Axis 5: Deploy-server build offload

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| 5.1 | Build IBLbot's TypeScript on the CI runner; ship only `dist/` | ⬜ Open | 🟦 | M |

### 5.1 IBLbot's TS is compiled on the memory-tight deploy droplet
**Location:** `.github/workflows/main.yml` "Deploy and restart IBLbot" step (`cd www/ibl5/IBLbot && npm install --include=dev && npm run build` over SSH); `ibl5/IBLbot/package.json` (`build: tsc`).
**Problem:** The deploy runs `npm install` + `tsc` **on the production droplet**, which is memory-constrained. This has already broken deploys twice as the toolchain got heavier: tsx/esbuild was SIGKILLed (worked around by running `node dist/` instead of `tsx src/`), and TypeScript 7's native **Go** compiler fatally failed with `newosproc` (can't spawn OS threads) — forcing a pin back to the JS-based `typescript@5.x` (PR #1453, 2026-07-13) plus a Dependabot major-version block on `typescript`. Each fix is a workaround for the root cause: **a compiler runs on a server that can't afford one.**
**Suggested direction:** Compile IBLbot on the CI runner (where it already builds during tests), upload `dist/` as an artifact, and have the deploy step `rsync`/scp the prebuilt `dist/` to the droplet + `npm ci --omit=dev` (runtime deps only) + pm2 restart — no compiler on the server. This also unblocks the `typescript@7` Dependabot pin (the Go compiler is fine on a CI runner). Mirrors how compiled CSS is already built in CI and deployed as an artifact (`main.yml` "Build CSS" → "Deploy compiled CSS to server").
**Risk if untouched:** Every future bump to a heavier build tool (TS, esbuild, a bundler) risks re-breaking the deploy on server memory limits; each is caught only at deploy time, blocking the whole production pipeline until hand-patched.
**Status (2026-07-14):** ⬜ Open — surfaced by the PR #1453 deploy-failure investigation; needs a `/plan` (touches the deploy/notify path → expect `auto_merge: false`, 🟦).

---

## Burn-down process

1. Pick an entry; `/plan` it (CI infra touching deploy/notify → expect `auto_merge: false`).
2. Implement in a worktree; the change is green-green when the existing CI suites pass unchanged.
3. Update this doc's status/readiness.
4. Bump `last_verified` (CI enforces via `bin/check-docs`).
