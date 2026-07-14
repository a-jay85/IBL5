---
description: Development-efficiency backlog — inner-loop speed (diff-scoped analysis, parallel tests), CI caching, dependency-bump batching, and worktree lifecycle automation, with per-entry status.
last_verified: 2026-07-14
---

# Development-Efficiency Backlog

**Purpose:** Catalogue tooling changes that cut wall-clock and setup waste in the develop-verify loop — locally, in CI, and in the overnight queue. Each open entry is a candidate for a `/plan`.

**Origin:** Advisory sessions (2026-07-07): a codebase + harness efficiency review and an automouse pipeline audit. Statuses verified against `bin/`, `ibl5/composer.json`, `.github/`, and the automouse queue on 2026-07-07.

**Companion to** [`ci-backlog.md`](ci-backlog.md) (CI-workflow simplification proper lives there) and the other backlogs in [README.md](README.md); same status taxonomy.

---

## Taxonomy

**Status** — canonical five-glyph set: see [README.md § Status taxonomy](README.md#status-taxonomy).

**Automouse-readiness** (for items not ✅/🚫): same glyphs as [`ci-backlog.md`](ci-backlog.md) — 🟩 auto-mergeable · 🟦 automouse-safe, human-merge · 🟨 conditional · 🟥 not automouse-safe.

**Effort scale:** **S** — single PR, < 1 day. **M** — multi-step plan, 1–3 days. **L** — platform shift, likely needs an ADR.

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 4 |
| 📋 Planned | 2 |
| ◑ Partial | 2 |
| ✅ Implemented | 3 |
| 🚫 Declined | 0 |

---

## Entries

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| E1 | Warm-standby worktree pool | ⬜ Open | 🟨 | M |
| E2 | Dependabot grouping | ⬜ Open | 🟩 | S |
| E3 | PHPStan result-cache in CI | ⬜ Open | 🟩 | S |
| E4 | Flake-quarantine ledger | ⬜ Open | 🟨 | M |
| E5 | Scheduled stale-worktree GC | ◑ Partial | 🟨 | S |
| E6 | Diff-scoped PHPStan wrapper | ✅ Implemented | — | S |
| E7 | Parallel PHPUnit | ✅ Implemented | — | M |
| E8 | Memory lines → mechanical gates (umbrella) | ◑ Partial | 🟨 | M |
| E9 | Meta-tooling growth bar | 📋 Planned | 🟦 | S |
| E10 | Schema baseline auto-regen | ✅ Implemented | — | M |
| E11 | In-PR pre-baked image build | 📋 Planned | 🟦 | M |

### E1 Warm-standby worktree pool
**Location:** `bin/wt-new` (no pool/claim logic today).
**Problem:** Per-plan worktree provisioning (composer install, Docker stack up) is pure serial dead time, paid once per plan in the overnight queue and once per task interactively.
**Suggested direction:** Pre-provision one spare worktree that `bin/wt-new` claims and rebrands (rename branch + Traefik route), then re-provision the spare in the background.
**Risk if untouched:** Minutes of dead time multiplied by every queue slot and every new task.
**Status (2026-07-07):** ⬜ Open — 🟨 (needs one design decision: how a claimed pool worktree gets its branch/route identity swapped safely).

### E2 Dependabot grouping
**Location:** `.github/dependabot.yml` — no `groups:` key (verified).
**Problem:** Minor/patch bumps arrive as separate PRs, each running full CI (observed: 5 dep PRs in one day).
**Suggested direction:** Dependabot `groups:` batching minor+patch per ecosystem into one weekly PR; majors stay individual.
**Risk if untouched:** ~5× redundant CI runs per bump wave.
**Status (2026-07-07):** ⬜ Open — 🟩.

### E3 PHPStan result-cache in CI
**Location:** `.github/workflows/` — no `resultCache` persistence (verified); every PR re-analyzes the world.
**Problem:** Most PRs touch a handful of files but pay a full-project PHPStan run.
**Suggested direction:** Persist `resultCachePath` via `actions/cache` keyed on `composer.lock` + the phpstan config; PHPStan's own file-hash invalidation keeps it correct.
**Risk if untouched:** The longest single step in the most-run workflow stays O(project) instead of O(diff).
**Status (2026-07-07):** ⬜ Open — 🟩.

### E4 Flake-quarantine ledger
**Location:** E2E CI (`.github/workflows/`) — no quarantine mechanism (verified; "flake" mentions are VR-specific).
**Problem:** Specs that pass only on retry are invisible until a red run poison-pills the nightly queue (the post-plan skip-on-red behavior exists because of this).
**Suggested direction:** Auto-detect passed-on-retry specs from Playwright reports, log them to a ledger, and report a quarantine list for triage.
**Risk if untouched:** Recurring lost nights; flake debt accumulates unmeasured.
**Status (2026-07-07):** ⬜ Open — 🟨 (one policy decision: what quarantine *does* — report-only vs auto-skip).

### E5 Scheduled stale-worktree GC
**Location:** `bin/cleanup` (`--all` / `--dry-run` sweep of worktrees, branches, and Docker stacks) + `bin/wt-status` (MERGED / OPEN-PR / UNPUSHED / STALLED / EMPTY classifier — the safety layer).
**Problem:** The sweep exists but only runs when invoked by hand; dead worktrees accumulate holding containers/volumes for weeks.
**Suggested direction:** Schedule `bin/cleanup --all` (launchd/cron), sweeping only worktrees `bin/wt-status` classifies as safe (MERGED/EMPTY), surfacing STALLED ones instead of deleting them.
**Risk if untouched:** Disk/RAM held by dead Docker stacks; stale worktrees confuse session coordination.
**Status (2026-07-07):** ◑ Partial — sweep + classifier merged; scheduling absent. 🟨 (the schedule itself is host-local, not PR-shippable).

➜ E6 Diff-scoped PHPStan wrapper — ✅ Implemented (2026-07-14): see [archive](archive/dev-efficiency-backlog-archive.md).

### E7 Parallel PHPUnit
**Location:** `ibl5/composer.json:17,21` — `brianium/paratest ^7.23`; `composer run test` runs paratest.
**Status (2026-07-07):** ✅ Implemented — the most-run local command is parallel; `#[Group('database')]` tests stay serial against the shared fixture.

### E8 Memory lines → mechanical gates (umbrella)
**Location:** The memory index (`MEMORY.md`) and its context→mechanical audit; delivered gates land in `bin/` + `.github/workflows/`.
**Problem:** Norms that live only as memory lines are always-loaded context tax and fail silently when stale; a gate enforces the norm at zero per-turn cost.
**Suggested direction:** Keep converting each mechanizable norm to a gate that retires its memory line (pairs with T7 in [token-spend-backlog.md](token-spend-backlog.md)).
**Delivered:** memory-expiry marker + SessionStart hook; the `bin/test` unit/db/e2e dispatcher; the seed-id collision gate (`ibl5/bin/check-seed-id-uniqueness`, CI-wired) — both previously "queued" here, now shipped; plus the bucket-B round-2/3 sweep — bash-guard checks (plans-rm, force-push, worktree-stash, prod-migration SQL), `ibl5/bin/check-config-example`, `ibl5/bin/check-xml-class-refs` + extension-less `bin/` scripts enrolled in `ibl5/phpstan.neon`, `ibl5/bin/check-th-aria-label`, and the ASG conference-split regression lock (`ibl5/tests/LeagueTest.php`).
**Open (2026-07-14):** exactly one — the **free-agents teamid write guard** (`FREE_AGENTS_TEAM_NAME`/null team must not write `teamid=0`). Not cleanly mechanizable as a lint (the guards live legitimately in ~8 sibling controllers, so a call-graph rule is false-positive-prone); the tractable route is characterization tests per write path (the ASG precedent). Needs its own `/plan` — not an ad-hoc, not a today-ship.
**Risk if untouched:** Recall dilution plus repeat failures the gates would have caught.
**Status (2026-07-14):** ◑ Partial — cheap-gate well exhausted; the only remaining item (free-agents guard) is a standalone `/plan`. 🟨.

### E9 Meta-tooling growth bar
**Location:** Plan: `$HOME/.claude/plans/meta-tooling-bar.md` (queued) — extend-before-add rule + quarterly cull.
**Problem:** ~27 of 101 `bin/` scripts exist to test the other scripts; the gate layer itself has had bugs. Nothing pushes back on meta-tooling growth.
**Status (2026-07-07):** 📋 Planned — queued. 🟦 (rule authoring wants human eyes on merge).

### E10 Schema baseline auto-regen
**Location:** `.github/workflows/migration-safety.yml` (`regen-schema-dump` job) + `bin/regen-schema-dump`.
**Problem (was):** The schema reference was a stale March snapshot; every schema question paid a verify-against-migrations tax.
**Status (2026-07-07):** ✅ Implemented — on every master push, CI rebuilds the schema from migrations and auto-commits `ibl5/docs/schema/current-schema.sql` if changed. (The `000_baseline` migration snapshot itself is intentionally untouched — the regenerated dump is the source of truth for schema questions.)

### E11 In-PR pre-baked image build
**Location:** Plan: `$HOME/.claude/plans/in-pr-prebaked-image-build.md` (queued). Today only `.github/workflows/cache-dependencies.yml` builds the image, on schedule/push — never in-PR.
**Problem:** A PR changing the Dockerfile or composer deps is E2E-tested against the *previous* master image; the mismatch surfaces only after merge.
**Status (2026-07-07):** 📋 Planned — queued; paths-filtered so normal PRs are unaffected. 🟦.

---

## Burn-down process

1. Pick an entry; `/plan` it (or ad-hoc per the work-triage rule for S items).
2. Implement in a worktree; green-green via existing CI.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
