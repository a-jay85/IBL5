---
description: WCAG 2.x full-rule (non-contrast) accessibility failure inventory and burn-down backlog per axe rule, with audited per-entry implementation + automouse-readiness status. Companion to a11y-contrast-backlog.md.
last_verified: 2026-06-21
---

# A11y Full-Rule Backlog (non-contrast)

**Purpose:** Track WCAG 2.x accessibility failures **beyond** `color-contrast` (which has its own inventory in [`a11y-contrast-backlog.md`](a11y-contrast-backlog.md)). Each open entry is a candidate for a `/plan`.

**Origin:** Full-rule axe-core audit (2026-06-13). The existing ratchet (`accessibility.spec.ts`) only ran `withTags(['wcag2a','wcag2aa'])` and suppressed `color-contrast`, so the `best-practice` and WCAG 2.1/2.2 rule families were **never enforced**. That audit enabled `wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa, best-practice` across all spec pages + previously-untested modules/root pages + the authenticated form pages.

> **⚠️ Seed caveat:** the original audit ran against the **dev DB** (`main.localhost`), not the CI seed. Per `feedback_a11y_contrast_scan_seed` (PR #1009), the dev DB misses conditional/data-driven content. Findings are split into **seed-independent** (template/markup-driven — CI reproduces; safe to plan now) and **seed-dependent** (must be re-verified on the `bin/wt-up --seed` / CI-seed stack before planning). Burn-down plans seed their allowlists **empirically at impl time**, not from this doc's page lists.

---

## Disposition taxonomy

This audit (2026-06-20, verified against the live `accessibility.spec.ts` `KNOWN_FAILING` map + fresh code reads, **not** the original dev-DB descriptions) classifies every item on two axes.

**Status** — where the fix stands:
- ✅ **implemented** — merged; the rule is enforced for that page (removed from `KNOWN_FAILING`).
- 📋 **planned** — a plan file exists (queued or PR-open); not yet merged.
- ⬜ **unplanned** — no plan yet.

**Automouse-readiness** — what it would take for automouse to ship it unattended:
- 🟢 **auto-mergeable** — mechanical + invisible (no VR change) + no security/judgment surface; green-green verifiable by the ratchet → a plan can arm auto-merge.
- 🟦 **automouse-safe, not auto-mergeable** — automouse can implement + verify, but a human must merge (VR baseline change needing visual review, or an `auto_merge: false` hold). Held at the merge, not the implementation.
- 🟠 **scope/decision first** — *could* become 🟢/🟦 after a one-time addition: a small mechanical scope-add (e.g. routing a param through a shared renderer; a seed-verify phase) **or** an upfront human decision (which `h2` is THE title; chosen heading text). The judgment is a single discrete choice, front-loadable per `/plan` Step 3.5.
- 🔴 **not automouse-safe** — needs a refactor-scale change, distributed per-site judgment, or is slated for deletion.

(Legacy 🟢/🟡/🔵/⚪ markers from the original audit are superseded by the table below.)

---

## Master status table

| Rule / subgroup | Status | Readiness | Verdict basis (fresh-checked 2026-06-20) |
|---|---|---|---|
| **heading-order** | ✅ implemented | — | `a11y-2` merged; empty `KNOWN_FAILING['heading-order']` set. |
| **empty-table-header** | ✅ implemented | — | `a11y-3` merged; rule key absent from `KNOWN_FAILING` entirely. |
| **page-has-heading-one** — single-title views | ✅ implemented | — | `a11y-2` (#1103) merged. |
| **page-has-heading-one** — training camp ratings diff | ✅ implemented | — | `a11y-4` (#1158) merged. |
| **page-has-heading-one** — 4 leaderboard/db promotes + team-page add | 📋 planned | 🟦 not auto-mergeable | `a11y-5` (#1163) PR **open**, `auto_merge: false` — the Team-page `<h1>` **add** changes `team` VR baselines → human review. The 4 promotes alone are VR-identical, but bundled with Team. |
| **page-has-heading-one** — next sim (single-title promote) | ⬜ unplanned | 🟢 auto-mergeable | `NextSimView.php:54` emits a single `<h2 class="ibl-title">Next Sim</h2>` → plain promote to `<h1>` (VR-identical). An unplanned single-title view a11y-2/4 didn't sweep. |
| **page-has-heading-one** — schedule + team schedule (STALE allowlist) | ⬜ unplanned | 🟢 auto-mergeable | **Re-checked:** both Views already emit `<h1 class="ibl-title">Schedule</h1>` **unconditionally** (`LeagueScheduleView.php:51`, `TeamScheduleView.php:101`). The pages already pass `page-has-heading-one`; the allowlist entries are **stale** → verify-and-remove (no code change), clicks the ratchet. |
| **page-has-heading-one** — multi-title / loop-rendered (standings, trading, season archive, franchise record book, compare players, waivers, depth chart entry, voting results, olympics standings; **big board, trade block — blocked on Phase-4 re-land**) | 📋 partial | 🟠 decision | **DONE (this plan):** trading, season archive, franchise record book, compare players, waivers, depth chart entry promoted to `<h1>` (record book also got a `heading-order` h3→h2 co-fix). **STILL OPEN:** standings + voting results need a page-level `<h1>` ADDED (per-region/per-award loop, no single title — a VR-changing decision, separate plan); olympics standings is not spec-tracked; big board / trade block blocked on Phase-4 re-land. |
| **page-has-heading-one** — title-less add (homepage, player page, your account, voting ASG/EOY ballot, news index/categories/article) | ⬜ unplanned | 🟠 decision → 🟦 | Needs an `<h1>` **added** with invented title text (decision) **and** the new visible heading changes VR baselines (human review). After the text decision, lands as 🟦 (not auto-mergeable). |
| **link-name** (homepage, news index/categories/article) | ⬜ unplanned | 🟠 scope | `aria-label` add is invisible → auto-mergeable in principle, **but** seed-dependent: add a CI-seed reproduce phase first. News-template subset → 🟢 once reproduced; homepage sim-recap subset is data-dependent (may go green with no fix). |
| **target-size** (topics ×100, homepage, news article) | ⬜ unplanned | 🟦 not auto-mergeable | Fix is **CSS** `min-height`/`min-width`/padding → changes rendered pixels → VR baseline regen + human review. Automouse can implement; merge is held. Small-count hits also seed-dependent. |
| **landmark-unique** — standings | ✅ implemented | — | #1164 merged; `StandingsView::renderHeader()` derives per-region `aria-label`; removed from `KNOWN_FAILING`. |
| **landmark-unique** — schedule + team schedule | ⬜ unplanned | 🟢 auto-mergeable | **Re-checked:** the duplicate is each schedule View's **own** `<nav class="ibl-jump-menu">` (`LeagueScheduleView.php:84`, `TeamScheduleView.php:144`) colliding with the site nav — NOT a shared-nav change. One invisible `aria-label` per View (e.g. "Jump to month") → like standings. |
| **landmark-unique** — league starters + next sim | ⬜ unplanned | 🟠 scope | The scroll-region label is auto-derived by `responsive-tables.js:163` from each `<table>`'s `aria-label`, **but** those tables are built by **shared** renderers (`UI\Tables\Ratings`, `BasketballStats\Tables\*`) with no aria-label param. Route an optional per-table label through them (the position/section title already exists) → invisible → 🟢. |
| **label** (leagueControlPanel `.ibl-input`) | 📋 planned | 🟢 auto-mergeable | Plan `leaguecontrolpanel-aria-label-a11y` **queued** (automouse); aria-label-only (invisible), admin-gate/CSRF/handler untouched, auto-merge eligible. |
| **select-name** (leagueControlPanel `<select>` ×6) | 📋 planned | 🟢 auto-mergeable | Same plan, bundled. |
| **landmark-one-main** (leagueControlPanel) | ⬜ unplanned | 🔴 not safe | Needs a `<main>` landmark, which means routing the legacy root page through `PageLayout` — the maintenance-2.27 module conversion. Refactor-scale. (The page IS now ratchet-tracked — allowlisted — via the `label`/`select-name` plan.) |
| **landmark-one-main** / **region** / **html-has-lang** (faprep) | ⬜ unplanned | 🔴 delete instead | `faprep.php` is slated for **deletion** (maintenance 3.9). Fixing is wasted. |
| **region** (leagueControlPanel, 13 nodes) | ⬜ unplanned | 🔴 not safe | Same as landmark-one-main: PHP-Nuke table-layout content sits outside any landmark; refactor-scale (2.27). |
| **color-contrast** | ⬜ out of scope | — | Tracked in [`a11y-contrast-backlog.md`](a11y-contrast-backlog.md). |

**One-line takeaways for picking work:**
- **Ready to plan as auto-mergeable now:** `landmark-unique` schedule/team-schedule (jump-menu aria-label); `page-has-heading-one` next sim (single-title promote) **and** schedule/team-schedule (stale allowlist removal — no code change). `label`/`select-name` already planned + queued.
- **Auto-mergeable after a small scope/decision:** `landmark-unique` league-starters/next-sim (renderer param); `link-name` News subset (seed-verify); `page-has-heading-one` multi-title (which-`h2` decision).
- **Automouse-safe but a human must merge:** `target-size` (VR), `page-has-heading-one` title-less + a11y-5 Team page (VR).
- **Not automouse-safe:** `landmark-one-main` + `region` on leagueControlPanel (2.27 refactor); everything on `faprep.php` (delete).

---

## How the expanded ratchet works

`a11y-1-ratchet-best-practice` (merged) added the `best-practice` + `wcag22aa`/`wcag21*` tags to `tests/e2e/helpers/accessibility.ts` and generalized the page allowlist to a **per-page-per-rule** map (`KNOWN_FAILING[rule] = Set<page>`) **in `tests/e2e/smoke/accessibility.spec.ts`** (not the helper — the original burn-down note misstated this). Every currently-failing (page, rule) pair is allowlisted so the spec stays green while catching **new** regressions everywhere else. Burn-down plans fix pages and remove allowlist entries — each removal clicks the ratchet.

---

## Rule inventory (detail)

### page-has-heading-one — best-practice, moderate
**Problem:** Many module pages render their title as `<h2 class="ibl-title">` and emit no `<h1>`. The `<h1 class="ibl-title">` convention already exists (TeamView, schedule views, training camp) and is being adopted page-by-page. Seed-independent (markup, not data).
**Direction:** Promote the page's sole/main `<h2 class="ibl-title">` → `<h1>` (same CSS class = visually identical), or **add** an `<h1>` where the page has no title heading.

| Sub-group | Status / readiness |
|-----------|--------------------|
| **Single-title views** (draft history, cap space, activity tracker, all-star appearances, contract list, draft, draft pick locator, franchise history, free agency preview, gm contact list, injuries, league starters, one-on-one game, player movement, projected draft order, record holders, season highs, series records, team off/def stats, transaction history, search, topics, free agency, training camp ratings diff) | ✅ implemented — `a11y-2` + `a11y-4` merged. **watchlist** half BLOCKED on the Phase-4 GM re-land (Watchlist reverted in `503d1fa85`) — fix when that module re-lands. |
| **4 promotes + Team-page add** (season leaderboards, career leaderboards, award history, player database; team page) | 📋 planned — `a11y-5` (#1163) **open**, 🟦 not auto-mergeable (Team `<h1>` add → VR review). |
| **next sim** (single `<h2 class="ibl-title">Next Sim</h2>`, `NextSimView.php:54`) | ⬜ unplanned — 🟢 plain promote (VR-identical); an unplanned single-title view. |
| **schedule + team schedule** (already emit `<h1>` unconditionally — `LeagueScheduleView.php:51`, `TeamScheduleView.php:101`) | ⬜ unplanned — 🟢 **stale allowlist entry**: page already passes; remove from `KNOWN_FAILING` and confirm green (no code change). |
| **Multi-title — promoted** (trading, season archive, franchise record book, compare players, waivers, depth chart entry) | ✅ implemented — topmost `h2.ibl-title`→`h1` (record book + `heading-order` h3→h2 co-fix). |
| **Multi-title — needs page-level `<h1>`** (standings, voting results; uncovered: olympics standings) | ⬜ unplanned — 🟠→🟦: topmost heading is a per-region/per-award loop item, not a page title; adding a page-level `<h1>` is a VR change (separate plan). |
| **Multi-title — blocked** (big board, trade block) | ⬜ unplanned — 🔴 blocked on Phase-4 GM re-land (reverted; PR #1084 / #1082) — don't plan until the module re-lands. |
| **Title-less add** (homepage, player page, your account, voting ASG/EOY ballot, news index/categories/article) | ⬜ unplanned — 🟠→🟦: needs chosen title text **and** the new visible `<h1>` changes VR. |

### heading-order — best-practice, moderate — ✅ implemented
Was a single `<h4>`-after-`<h2>` skip on `record holders`; fixed in `a11y-2-heading-one-single-title` (merged). Empty `KNOWN_FAILING['heading-order']` set confirms enforcement.

### empty-table-header — best-practice, minor — ✅ implemented
`<th>` cells with no text (icon-only sort columns / sticky row-label / separator + position-section headers) on cap space, player page, free agency, depth chart entry, next sim. Fixed via `aria-label` in `a11y-3-empty-table-header` (merged). Rule key absent from `KNOWN_FAILING` → fully enforced.

### link-name — wcag2a (level A), serious — ⬜ unplanned, 🟠 scope
**Location (allowlisted):** homepage, news index/categories/article. **Problem:** links with no discernible text — News-template icon-links (consistent → mechanical) + homepage `last-sim-recap` team links (data-dependent). **Direction:** `aria-label` (team name / article title) — invisible, so auto-mergeable per-page. **Scope to add:** a CI-seed reproduce phase first (`feedback_a11y_contrast_scan_seed`): if reproduced, News subset → 🟢; if a sim-recap hit doesn't reproduce on the CI seed, a ratchet removal would go green with no fix.

### target-size — wcag22aa (WCAG 2.2), serious — ⬜ unplanned, 🟦 not auto-mergeable
**Location (allowlisted):** topics (~100 nodes, dense small-link list), homepage, news article. **Problem:** touch targets < 24×24px. **Direction:** CSS `min-height`/`min-width`/padding. **Why held:** CSS sizing changes rendered pixels → VR baseline regen + human visual review. Automouse can implement; the merge is held (`auto_merge: false`). Small-count hits are also sim-recap seed-dependent — verify topics(100) on the CI seed.

### landmark-unique — best-practice, moderate
**standings — ✅ implemented** (#1164): `StandingsView::renderHeader()` derives a unique `aria-label` per region from the in-scope `$region`/`$groupingType` vars; removed from `KNOWN_FAILING`.

**schedule + team schedule — ⬜ unplanned, 🟢 auto-mergeable.** Fresh check: each schedule View emits its own `<nav class="ibl-jump-menu schedule-months">` (`LeagueScheduleView.php:84`, `TeamScheduleView.php:144`); on a schedule page that is a *second* navigation landmark alongside the shared site `<nav class="nav-grain">` (`NavigationView.php:53`), both unnamed → `landmark-unique`. Fix is one invisible `aria-label` per View on the jump-menu nav — View-local, **not** a shared-nav change. (The original "shared-nav blast radius" note was wrong.)

**league starters + next sim — ⬜ unplanned, 🟠 scope.** `responsive-tables.js:163` sets each scroll-region's `aria-label` from `table.getAttribute("aria-label") || "Scrollable data table"` — so multiple tables get the same generic name. The distinguishing labels already exist (position/section titles), but the `<table>` markup is produced by **shared** renderers (`UI\Tables\Ratings::render`, `BasketballStats\Tables\*::render`) that take no aria-label argument. Add an optional per-table `aria-label` param (or wrap) so the View can pass the section name; the JS then propagates it. Invisible → 🟢 once that small cross-cutting scope is added.

### landmark-one-main — best-practice, moderate — ⬜ unplanned, 🔴 not safe
**leagueControlPanel:** no `<main>` because the root page bypasses `PageLayout`. Fixing means routing it through `PageLayout` — the maintenance-2.27 module conversion (refactor-scale). The page is now ratchet-tracked (allowlisted for this rule) via the `label`/`select-name` plan, so regressions are caught; the fix itself waits on 2.27. **faprep:** → delete (maintenance 3.9).

### region — best-practice, moderate — ⬜ unplanned, 🔴 not safe
**leagueControlPanel** (13 nodes): PHP-Nuke table-layout content sits outside any landmark region. Same root cause and same resolution as landmark-one-main (couple to 2.27). **faprep** (1): → delete.

### label — wcag2a (level A), **critical** — 📋 planned, 🟢 auto-mergeable
**leagueControlPanel** `.ibl-input` with no programmatic label. Plan `leaguecontrolpanel-aria-label-a11y` (queued for automouse) adds a static `aria-label` to every input; aria-label-only (invisible, no VR), admin-gate/CSRF/POST handler untouched → auto-merge eligible. Adds the page to the ratchet enforcing `label`.

### select-name — wcag2a (level A), **critical** — 📋 planned, 🟢 auto-mergeable
**leagueControlPanel** `<select>` ×6 (incl. `SeasonPhase`, the league switcher) with no accessible name. Bundled into the same queued plan; `aria-label` per select.

### html-has-lang — wcag2a (level A), serious — ⬜ unplanned, 🔴 delete instead
**faprep** `<html>` with no `lang` (doesn't use `PageLayout`, which sets `lang`). `faprep.php` is slated for **deletion** (maintenance 3.9 / 2.28) — fix is wasted.

### color-contrast — wcag2aa, serious — ⬜ out of scope
Tracked separately in [`a11y-contrast-backlog.md`](a11y-contrast-backlog.md). The full-rule audit re-confirmed contrast on the team-color/stat-highlight pages; no new action here.

---

## Non-a11y findings surfaced by the audit

- **BigBoard returns HTTP 500** (`modules.php?name=BigBoard`) — a runtime bug, not accessibility; could not be a11y-audited. (Note: BigBoard rides the Phase-4 GM re-land — see PR #1084.) File/track separately.

---

## Plans — status (audited 2026-06-20)

| Plan | Status |
|------|--------|
| `a11y-1-ratchet-best-practice` | ✅ merged — tags + per-page-per-rule allowlist + this doc. |
| `a11y-2-heading-one-single-title` | ✅ merged (#1103) — single-title promotes + record-holders heading-order. |
| `a11y-3-empty-table-header` | ✅ merged — empty `<th>` labels. |
| `a11y-4-training-camp-heading-one` | ✅ merged (#1158) — training camp `<h1>`. |
| `a11y-5-heading-one-burndown` | 📋 PR open (#1163), `auto_merge: false` — 4 promotes + Team-page `<h1>` add (VR review). |
| `standings-landmark-unique-aria-label` | ✅ superseded — the standings fix already merged independently as **#1164**; the plan file is redundant (not queued). |
| `leaguecontrolpanel-aria-label-a11y` | 📋 queued for automouse — `label` + `select-name` via aria-label; auto-merge eligible. |
| `a11y-heading-one-multi-title` | ✅ implemented — 6 multi-title pages promoted; standings + voting results deferred (page-level `<h1>` decision). |

## Burn-down process

1. Fix the markup/CSS for the target page(s).
2. Run `bunx playwright test tests/e2e/smoke/accessibility.spec.ts --project=chromium` to confirm the page passes the now-enabled rule.
3. Remove the (page, rule) entry from `KNOWN_FAILING` in `tests/e2e/smoke/accessibility.spec.ts`.
4. Update this doc's status/readiness in the master table.
5. Bump `last_verified` (CI enforces via `bin/check-docs`).
