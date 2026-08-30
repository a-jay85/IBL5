---
description: Historical archive: completed/declined E2E test-quality backlog entries, extracted from e2e-backlog.md.
last_verified: 2026-08-30
---

# E2E Test-Quality Backlog — Archive

Read-only historical record of ✅ Implemented / 🚫 Declined findings. For OPEN items see ../e2e-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### A1 `loginViaForm` reimplemented in 4 specs
**Location:** `flows/auth.spec.ts` (`loginWithRemember`), `flows/password-reset.spec.ts`, `flows/your-account.spec.ts`, `smoke/auth-regular-user-provisioned.spec.ts`
**Problem:** Each fills `#login-username`/`#login-password` and `Promise.all([waitForURL, click])` independently; same shape as the two `*.setup.ts` files.
**Suggested direction:** Extract `helpers/login.ts loginViaForm(page, user, pass, {remember?})`. Setup files keep their copy (they run pre-fixture).
**Est. effort:** S
**Risk if untouched:** Five copies drift; a login-flow change must be made in each.
**Status (2026-08-08):** ✅ Implemented — `helpers/login.ts loginViaForm()` extracted; imports updated in `auth.spec.ts`, `password-reset.spec.ts`, `smoke/auth-regular-user-provisioned.spec.ts`. (#1808)

### A2 `assertNoPhpErrors` hand-rolled inline
**Location:** `smoke/schedule-contrast.spec.ts:13,42`
**Problem:** Only spec that re-declares `const PHP_ERROR_STRINGS=[...]` + loop instead of importing `assertNoPhpErrors`. NB: the inline loop is the documented fallback in `playwright-tests.md`, so it's not wrong — just the lone hold-out.
**Suggested direction:** Import `assertNoPhpErrors`.
**Est. effort:** S
**Risk if untouched:** Pattern set drifts from the canonical helper.
**Status (2026-08-08):** ✅ Implemented — replaced inline loop with `assertNoPhpErrors` import in `schedule-contrast.spec.ts`. (#1808)

### A3 Voting category-expand block copy-pasted 4×
**Location:** `flows/voting-submission.spec.ts:32,160,210,301`
**Problem:** find-header-by-regex → check `aria-expanded` → click-if-collapsed, ~10 LOC each.
**Suggested direction:** Extract `expandVotingCategory(page, cat)`.
**Est. effort:** S
**Risk if untouched:** Voting-DOM change requires 4 edits.
**Status (2026-08-08):** ✅ Implemented — `helpers/voting.ts expandVotingCategory()` extracted; all 4 call sites in `voting-submission.spec.ts` updated. (#1808)

### A4 ajax-api 4-test pattern repeated 3×
**Location:** `flows/ajax-api-endpoints.spec.ts` (DCE / Team / LeagueStarters blocks)
**Problem:** ratings-returns-table / all-modes / invalid-fallback / HX-Push-Url repeated ~120 LOC, only the URL prefix varies.
**Suggested direction:** `describe` factory over `{module, url}`.
**Est. effort:** M
**Risk if untouched:** New API module → copy 4 tests; the weak `length>0` asserts (see C) propagate.
**Status (2026-08-08):** ✅ Implemented — `describeTabApi` factory introduced in `ajax-api-endpoints.spec.ts`; 3 describe blocks collapsed. (#1808)

### A5 Depth-chart MutationObserver setup duplicated
**Location:** `flows/depth-chart-entry.spec.ts:182,245`
**Problem:** Identical observer-install block across two preview tests.
**Suggested direction:** Local `installPreviewObserver(page)`.
**Est. effort:** S
**Risk if untouched:** Two copies drift.
**Status (2026-08-08):** ✅ Implemented — `installPreviewObserver()` hoisted to module scope in `depth-chart-entry.spec.ts`. (#1808)

### A6 `collectAllOfferIds` overlaps shared helper
**Location:** `flows/trading-submission.spec.ts:197` vs `helpers/trading.ts collectNewOfferIds`
**Problem:** Same navigate-to-reviewtrade + scrape `[data-preview-offer]`, differs only `Set` vs array.
**Suggested direction:** Make the helper return both shapes, or wrap it locally.
**Est. effort:** S
**Risk if untouched:** Two scrapers to maintain.
**Status (2026-08-08):** ✅ Implemented — `collectOfferIdSet()` hoisted to module scope in `trading-submission.spec.ts`, deduplicating scrape logic. (#1808)

### A7 `fetchToken()` CSRF-scraper trapped file-local
**Location:** `flows/csrf-rejection.spec.ts:43`
**Problem:** Good util; same need exists in `helpers/updater.ts` (regex-scrape token).
**Suggested direction:** Promote to `helpers/csrf.ts`.
**Est. effort:** S
**Risk if untouched:** Future positive-token tests reimplement it. (Low urgency — 1 current consumer.)
**Status (2026-08-08):** ✅ Implemented — `fetchToken()` promoted to `helpers/csrf.ts`; `csrf-rejection.spec.ts` updated to import. (#1808)

### A8 Repeated combined locator
**Location:** `flows/projected-draft-order.spec.ts`
**Problem:** `.projected-draft-order-table, .ibl-data-table` in 5 of 7 tests.
**Suggested direction:** Hoist to a const / page-object.
**Est. effort:** S
**Risk if untouched:** Minor.
**Status (2026-08-08):** ✅ Implemented — `TABLE_SEL` const hoisted in `projected-draft-order.spec.ts`; 5 locators updated. (#1808)

---

### Axis C — C1–C16, C18 weak/tautological E2E assertions
**Location:** 17 assertions across `contract-extension.spec.ts`, `draft.spec.ts`, `one-on-one-game.spec.ts`, `flows/search.spec.ts`, `free-agency.spec.ts`, `player.spec.ts`, `smoke/olympics-pages.spec.ts`, `smoke/htmx.spec.ts`, `team-schedule.spec.ts`, `season-highs.spec.ts`, `player-subpages.spec.ts`, `player-movement` / `free-agency-preview` / `cap-space` sortable, `topics.spec.ts`, `homepage.spec.ts`, `contract-list.spec.ts`, `flows/olympics-coverage.spec.ts:20`, and `api-e2e/api.test.ts` CSV paths.
**Problem:** Each assertion passes regardless of the feature under test — body-length thresholds, header-only regex, tautological locators, or bare `return`/true-only guards. Full per-item breakdown in the Axis C table in e2e-backlog.md.
**Suggested direction:** Replace each with an assertion tied to feature-specific content (seed value, specific column/class, real sort effect).
**Est. effort:** S per item.
**Risk if untouched:** Tests give false green; broken features pass CI undetected.
**Status (2026-08-08):** ✅ Implemented — each weak assertion replaced with a seed-grounded or element-scoped assertion, every one verified to fail when fed a wrong value. C17 remains ⬜ Open (out of scope). Bug finding: C7 player page (`smoke/olympics-pages.spec.ts:31`) — the tightened assertion red-flagged a real production bug: `PlayerPageController::renderPage()` resolved the viewer's team with an unguarded `Team::initialize()`, and `League\LeagueContext` rewrites `ibl_team_info` → `ibl_olympics_team_info`, which has no `Free Agents` row, so `Team::load()` threw and the page never rendered (the missing `ibl_olympics_plr` seed row, fixed here, was only what gives the assertion a name to match). Tracked as maintenance-backlog 6.24 and fixed in #2028; the assertion was kept failing, never relaxed, and goes green with #2028 in the base. Implementation notes: (1) C12 shipped the shared `assertColumnSorts` helper WITHOUT the exact-descending-sequence line `expect(after).toEqual([...before].sort().reverse())` — `sorttable.js` uses a diacritic-insensitive comparator that diverges from JS `.sort()`'s UTF-16 order on non-ASCII names (e.g. "Dariuš Lavrinovich"), producing false reds; the retained state assertions (`aria-sort`, `sorttable_sorted_reverse`, `#sorttable_sortrevind`) plus `after !== before` still discriminate; V36 is retired for that reason, not left unimplemented. (2) The plan's V40 completeness greps over-catch: the bare-return grep also matches the DON'T-12-compliant 401 branches at `api-e2e/api.test.ts` L45/L275, and the `if (ct.includes` grep matches three pre-existing, uncatalogued query-param-auth JSON guards at L423/438/450 — same Axis-C class, now tracked as C19 in e2e-backlog.md; both are documented knowns, not surviving escapes. (#1825)
