---
description: WCAG 2.x full-rule (non-contrast) accessibility failure inventory and burn-down backlog per axe rule. Companion to a11y-contrast-backlog.md.
last_verified: 2026-06-20
---

# A11y Full-Rule Backlog (non-contrast)

**Purpose:** Track WCAG 2.x accessibility failures **beyond** `color-contrast` (which has its own inventory in [`a11y-contrast-backlog.md`](a11y-contrast-backlog.md)). Each entry is a candidate for a `/plan`.

**Origin:** Full-rule axe-core audit (2026-06-13). The existing ratchet (`accessibility.spec.ts`) only ran `withTags(['wcag2a','wcag2aa'])` and suppressed `color-contrast`, so the `best-practice` and WCAG 2.1/2.2 rule families were **never enforced**. This audit enabled `wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa, best-practice` across all 47 spec pages + 9 previously-untested modules/root pages + the 9 authenticated form pages (depth chart, waivers, gm contact, compare players, draft, next sim, your account, voting ASG/EOY).

> **Good news on form a11y:** the 9 authenticated/form pages were audited explicitly and found **clean of `label`, `select-name`, and `link-name`** — the WCAG-A form criticals. Those criticals are confined to the admin `leagueControlPanel.php` (see below).

> **⚠️ Seed caveat:** the audit ran against the **dev DB** (`main.localhost`), not the CI seed. Per `feedback_a11y_contrast_scan_seed` (PR #1009), the dev DB misses conditional/data-driven content. Findings are split below into **seed-independent** (template/markup-driven — CI will reproduce; safe to plan now) and **seed-dependent** (must be re-verified on the `bin/wt-up --seed` / CI-seed stack before planning). Burn-down plans seed their allowlists **empirically at impl time**, not from this doc's page lists.

**Disposition legend:**
- 🟢 **automouse-safe** — fix is mechanical + verifiable by extending the spec ratchet (green-green); planned + queued.
- 🟡 **supervised** — needs human judgment (label wording, "which heading is THE title", architectural refactor) or carries VR-regression risk → `auto_merge: false`.
- 🔵 **seed-verify** — re-run axe on the CI-seed stack to confirm reproducibility before planning.
- ⚪ **out of scope** — covered elsewhere (contrast backlog) or the page is slated for deletion.

---

## How the expanded ratchet works

`a11y-1-ratchet-best-practice` adds the `best-practice` + `wcag22aa`/`wcag21*` tags to `tests/e2e/helpers/accessibility.ts` and generalizes the page allowlist from contrast-only to a **per-page-per-rule** map (`KNOWN_FAILING[rule] = Set<page>`). Every currently-failing (page, rule) pair is allowlisted so the spec stays green while now catching **new** regressions on every other page/rule. Burn-down plans fix pages and remove allowlist entries — each removal clicks the ratchet, exactly like the contrast backlog.

---

## Rule inventory

### page-has-heading-one — best-practice, moderate — 🟢/🟡
**Problem:** No page emits an `<h1>`. Module titles render as `<h2 class="ibl-title">`; the convention `<h1 class="ibl-title">` already exists (TeamView, LeagueSchedule/TeamScheduleView, YourAccountView) but most views never adopted it. Seed-independent (markup, not data). ~36 covered pages + 7 uncovered modules.
**Direction:** Promote the page's sole/main `<h2 class="ibl-title">` → `<h1>` (same CSS class = visually identical).

| Sub-group | Disposition | Covered by |
|-----------|-------------|------------|
| **Single-title views** (exactly one non-looped `h2.ibl-title`): draft history, cap space, activity tracker, all-star appearances, contract list, draft, draft pick locator, franchise history, free agency preview, gm contact list, injuries, league starters, one-on-one game, player movement, projected draft order, record holders, season highs, series records, team off/def stats, transaction history, search, topics, free agency, watchlist, training camp ratings diff | ✅ enforced (22 done; watchlist + training camp pending) | `a11y-2-heading-one-single-title` (merged) |
| **Multi-title / loop-rendered** (which `h2` is THE title needs judgment): standings + olympics standings (per-region loop), trading (2), season archive (2), franchise record book (2), compare players (4), waivers (2), depth chart entry (2), big board (2), trade block (3), voting results (`VotingResultsView::renderTable()` loop-renders one title per category) | 🟡 | backlog (this doc) |
| **No `ibl-title` h2 — needs an `<h1>` *added* with chosen title text:** season leaderboards, career leaderboards, award history, player database, player page (Player mega-module), your account (authenticated view — the `h1.ibl-card__title` only exists on the logged-out sign-in/register cards), voting ASG/EOY ballot, homepage + news index/categories/article (legacy `modules/News` index.php, no view class) | 🟡 | backlog (this doc) |
| **Conditional emit:** team page — `TeamView.php:52` only emits the `h1` in one ternary branch; the other branch (no team name) yields no h1 | 🟡 | backlog (this doc) |

### heading-order — best-practice, moderate — ✅ enforced
**Location:** `record holders` (`RecordHoldersView.php` — an `<h4>` follows the title with no intervening `<h3>`).
**Problem:** Heading levels skip. Coupled to the heading-one fix (promoting the title to `h1` shifts the hierarchy). Seed-independent.
**Direction:** Fix the level jump. Bundle into `a11y-2-heading-one-single-title` (same view, same render).
**Disposition:** ✅ enforced — `a11y-2-heading-one-single-title` (merged).

### empty-table-header — best-practice, minor — ✅ fixed
**Location:** cap space (`CapSpaceView` th[data-sort-col=7,13]), player page (`.highs-header`), free agency (`FreeAgencyView` sticky-col `th[data-sort-col=0]`), depth chart entry (`.dc-lineup-preview-table` first th + `.sep-team` separators, 9 nodes), next sim (`.next-sim-position-section` tables, 40 nodes).
**Problem:** `<th>` cells with no text (icon-only sort columns / sticky row-label column / separator + position-section headers). Template-driven, seed-independent.
**Fix:** Added `aria-label` to each empty header. No visual change.
**Disposition:** ✅ — fixed in `a11y-3-empty-table-header`; removed from `KNOWN_FAILING` in `accessibility.spec.ts`.

### link-name — wcag2a (level A), serious — 🔵
**Location:** news index/categories/article (12 nodes each — consistent → template icon-link in `modules/News`); homepage + debug menu (12 nodes — the `last-sim-recap` panel team links, **data-dependent**).
**Problem:** Links with no discernible text. **This is a WCAG-A failure on pages the existing spec already runs `wcag2a` against while CI is green → it is data-dependent (dev seed renders sim-recap/news rows the CI seed may not).**
**Direction:** Add `aria-label` (team name / article title — available in context) or visible text. The News template portion is likely mechanical once reproduced.
**Disposition:** 🔵 — re-run axe on the CI-seed stack. If reproduced: the News-template subset → automouse-safe plan; the sim-recap subset → confirm seed then plan. If NOT reproduced on CI seed, a ratchet assertion would go green with no fix.

### target-size — wcag22aa (WCAG 2.2), serious — 🟡🔵
**Location:** topics (100 nodes!), homepage + news article + debug menu (2 nodes — sim-recap `leaders-tabbed` team links).
**Problem:** Touch targets < 24×24px without sufficient spacing. WCAG 2.2 — a brand-new rule family for this codebase. The topics(100) hit is a dense small-link list.
**Direction:** CSS `min-height`/`min-width`/padding on the affected components.
**Disposition:** 🟡 (CSS sizing changes risk **visual-regression baseline** breakage → needs VR review, `auto_merge: false`) **+ 🔵** (the small-count hits are sim-recap data-dependent; verify topics(100) on CI seed). Plan after a VR-aware human pass.

### landmark-unique — best-practice, moderate — 🟡
**Location:** standings (per-region scroll regions all `aria-label="Standings"`), league starters (`aria-label="Scrollable data table"` generic, ×2), next sim (`.next-sim-position-section` scroll regions share a label), schedule + team schedule (`.nav-grain` duplicate landmark).
**Problem:** Multiple landmarks share the same role+name. Mixed root causes: (a) Standings — label *is* mechanically derivable from the existing `$region`/`$groupingType` vars; (b) league starters — generic default label needs a chosen per-table name; (c) `.nav-grain` — a nav-component element resolving to a duplicate landmark (needs investigation, likely affects the shared nav).
**Direction:** Unique `aria-label` per region. Standings subset could be mechanical; the rest need judgment + the `.nav-grain` nav-component fix needs care (shared across all pages).
**Disposition:** 🟡 — supervised (mixed label judgment + shared-nav blast radius; best-practice/moderate, not WCAG-AA).

### landmark-one-main — best-practice, moderate — 🟡
**Location:** league control panel (`leagueControlPanel.php`), faprep (`faprep.php`).
**Problem:** No `<main>` landmark. Both are root-level legacy pages that bypass the standard `PageLayout` (which provides the main landmark for module pages). See maintenance-backlog 2.27 (`leagueControlPanel.php` should become a module) / 3.9 (`faprep.php` slated for deletion).
**Disposition:** 🟡 — couple to the maintenance-backlog refactors, not a standalone a11y fix. faprep → ⚪ (delete instead, maintenance 3.9).

### region — best-practice, moderate — 🟡
**Location:** league control panel (13 nodes), faprep (1).
**Problem:** Page content sits outside any landmark region (PHP-Nuke legacy table-layout markup). Refactor-scale.
**Disposition:** 🟡 — same as landmark-one-main; couple to the maintenance-backlog 2.27/3.9 refactors. faprep → ⚪ (delete).

### label — wcag2a (level A), **critical** — 🟡
**Location:** league control panel (`.ibl-input` with no associated `<label>`).
**Problem:** Form input with no programmatic label. WCAG-A critical. But it's a root-level admin-only page (maintenance 2.27).
**Direction:** Add `<label for>` / `aria-label`. Mechanical, but on a legacy admin page being restructured.
**Disposition:** 🟡 — supervised; fold into the `leagueControlPanel.php` → module conversion (maintenance 2.27) or a standalone admin-page a11y pass.

### select-name — wcag2a (level A), **critical** — 🟡
**Location:** league control panel (4 `<select>`, incl. `select[name="SeasonPhase"]`, the league switcher).
**Problem:** `<select>` elements with no accessible name. WCAG-A critical. Same page as `label`.
**Direction:** Add `aria-label`/associated `<label>`. Bundle with the `label` fix above.
**Disposition:** 🟡 — supervised; bundle with `label` on the league control panel pass.

### html-has-lang — wcag2a (level A), serious — ⚪
**Location:** faprep (`faprep.php` — `<html>` with no `lang`).
**Problem:** Standalone root page that doesn't use `PageLayout` (which sets `lang`).
**Disposition:** ⚪ — `faprep.php` is slated for **deletion** (maintenance-backlog 3.9 / 2.28). Fix is wasted; delete the page instead.

### color-contrast — wcag2aa, serious — ⚪
**Disposition:** ⚪ — tracked separately in [`a11y-contrast-backlog.md`](a11y-contrast-backlog.md). The full-rule audit re-confirmed contrast on 26 pages (team-color cells + stat highlights); no new action here.

---

## Non-a11y findings surfaced by the audit

- **BigBoard returns HTTP 500** (`modules.php?name=BigBoard`, body len 73) — a real runtime bug, not accessibility. File separately. (Could not be a11y-audited.)

---

## Planned (automouse-safe) — queued 2026-06-13

| Plan | Scope |
|------|-------|
| `a11y-1-ratchet-best-practice` | Add best-practice + WCAG 2.2/2.1 tags to the helper; generalize the allowlist to per-page-per-rule; enable `page-has-heading-one`, `heading-order`, `empty-table-header` with empirically-seeded allowlists; **install this backlog doc to `ibl5/docs/a11y-backlog.md`**. No code fixes (regression-prevention only). |
| `a11y-2-heading-one-single-title` ✅ | Promote sole `h2.ibl-title` → `h1` in the single-title views + fix record-holders `heading-order`; remove those pages from the allowlist. |
| `a11y-3-empty-table-header` | Add visually-hidden labels to the empty `<th>` cells on cap space / player page / free agency; remove from allowlist. |

## Burn-down process

1. Fix the markup/CSS for the target page(s).
2. Run `bunx playwright test tests/e2e/smoke/accessibility.spec.ts --project=chromium` to confirm the page passes the now-enabled rule.
3. Remove the (page, rule) entry from `KNOWN_FAILING` in `tests/e2e/helpers/accessibility.ts` (or the spec).
4. Update this doc's disposition.
5. Bump `last_verified`. CI enforces the change permanently.
