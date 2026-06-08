---
description: WCAG 2.1 AA color-contrast failure inventory and burn-down backlog per page.
last_verified: 2026-06-08
---

# A11y Color-Contrast Backlog

**Purpose:** Track which pages have known `color-contrast` WCAG 2.1 AA failures (PHP-Nuke legacy palette).  
**When to reference:** Removing an entry from `CONTRAST_KNOWN_FAILING` in `ibl5/tests/e2e/smoke/accessibility.spec.ts` after palette CSS fixes.

---

## How the ratchet works

`accessibility.spec.ts` enables axe-core's `color-contrast` rule everywhere.
Pages listed in `CONTRAST_KNOWN_FAILING` have the rule suppressed **for that page only**.
All other pages — including any new page added to the spec — are **enforced** from day one.

Removing a page from `CONTRAST_KNOWN_FAILING` = promoting it to enforced (the ratchet clicks).
CI will immediately catch any contrast regression on promoted pages.

---

## Inventory (as of 2026-06-08)

Captured by running axe-core `color-contrast` against `http://main.localhost/ibl5/` with
the current dev seed. Rerun whenever palette CSS changes land.

### Pages currently enforced (contrast passes — 16 pages)

| Page | URL |
|------|-----|
| standings | `modules.php?name=Standings` |
| season leaderboards | `modules.php?name=SeasonLeaderboards` |
| career leaderboards | `modules.php?name=CareerLeaderboards` |
| draft history | `modules.php?name=DraftHistory` |
| team page | `modules.php?name=Team&op=team&teamid=1` |
| award history | `modules.php?name=AwardHistory` |
| league starters | `modules.php?name=LeagueStarters` |
| player database | `modules.php?name=PlayerDatabase` |
| transaction history | `modules.php?name=TransactionHistory` |
| voting results | `modules.php?name=VotingResults` |
| free agency | `modules.php?name=FreeAgency` |
| waivers | `modules.php?name=Waivers` |
| compare players | `modules.php?name=ComparePlayers` |
| your account | `modules.php?name=YourAccount` |
| voting ASG ballot | `modules.php?name=Voting` (ASG phase) |
| voting EOY ballot | `modules.php?name=Voting` (EOY phase) |

### Pages in allowlist (contrast fails — 31 pages)

Remove each entry from `CONTRAST_KNOWN_FAILING` in `ibl5/tests/e2e/smoke/accessibility.spec.ts`
once the palette fix for that page is verified passing.

**Public pages (26):**

| Page | URL |
|------|-----|
| homepage | `index.php` |
| cap space | `modules.php?name=CapSpace` |
| player page | `modules.php?name=Player&pa=showpage&pid=1` |
| activity tracker | `modules.php?name=ActivityTracker` |
| all-star appearances | `modules.php?name=AllStarAppearances` |
| contract list | `modules.php?name=ContractList` |
| draft pick locator | `modules.php?name=DraftPickLocator` |
| franchise history | `modules.php?name=FranchiseHistory` |
| franchise record book | `modules.php?name=FranchiseRecordBook` |
| free agency preview | `modules.php?name=FreeAgencyPreview` |
| injuries | `modules.php?name=Injuries` |
| one on one game | `modules.php?name=OneOnOneGame` |
| player movement | `modules.php?name=PlayerMovement` |
| projected draft order | `modules.php?name=ProjectedDraftOrder` |
| record holders | `modules.php?name=RecordHolders` |
| schedule | `modules.php?name=Schedule` |
| search | `modules.php?name=Search` |
| season archive | `modules.php?name=SeasonArchive` |
| season highs | `modules.php?name=SeasonHighs` |
| series records | `modules.php?name=SeriesRecords` |
| team off/def stats | `modules.php?name=TeamOffDefStats` |
| team schedule | `modules.php?name=Schedule&teamid=1` |
| topics | `modules.php?name=Topics` |
| news index | `modules.php?name=News` |
| news categories | `modules.php?name=News&file=categories` |
| news article | `modules.php?name=News&file=article&sid=1` |

**Authenticated pages (5):**

| Page | URL |
|------|-----|
| trading | `modules.php?name=Trading` |
| depth chart entry | `modules.php?name=DepthChartEntry` |
| gm contact list | `modules.php?name=GMContactList` |
| draft | `modules.php?name=Draft` |
| next sim | `modules.php?name=NextSim` |

---

## Burn-down process

1. Fix palette CSS for the target page(s).
2. Run `npx playwright test tests/e2e/smoke/accessibility.spec.ts --project=chromium` locally to confirm the page now passes.
3. Remove the page name from `CONTRAST_KNOWN_FAILING` in `ibl5/tests/e2e/smoke/accessibility.spec.ts`.
4. Update the tables above (move rows from the allowlist table to the enforced table).
5. Bump `last_verified` in this file's frontmatter.
6. CI enforces the change permanently.
