# M46 — `argument.type` test-baseline burndown triage table

Backlog 5.15 / chunk C6b. Baseline `phpstan-tests-baseline.neon` = **75 entries, 100% `argument.type`,
31 files** (verified `grep -c`). Pre-edit snapshot: `/tmp/m46-baseline-before.neon`.

Success = fix the genuinely-lazy fixtures/types faithfully (move **toward** prod reality), RETAIN the
intentional edges, log DEFER-PROD where the *prod* type is the bug. **NOT 75 → 0.** Fixable bucket is
materially smaller than 75 by design.

## Verdict summary

| File | entries | verdict | basis |
|------|--------:|---------|-------|
| tests/Api/Transformer/GameTransformerTest.php | 1 | **RETAIN** (attempted FIX, reverted) | Narrowing `makeGameRow` → `GameViewRow` did NOT burn the entry down: the only two erroring sites are `testTransformHandlesNullScores`/`testTransformScheduledGameStatus`, which set `visitor_score`/`home_score` to `null` (GameViewRow types them `int`). The regen-diff proved it churned 1 entry (count 2) → 2 precise entries (an *added* entry, zero net reduction). Reverted; intent comment added. |
| tests/ComparePlayers/ComparePlayersViewTest.php | 1 | **FIX** | `getValidComparisonData()` returns exactly `player1`/`player2`; all 7 erroring call sites use it. Narrow outer `@return array<string, …>` → `array{player1: …, player2: …}`. Prod param values are `array<string,mixed>` (wide) — our precise inner is assignable. |
| tests/UI/Tables/PlayerRowTransformerTest.php | 1 | **RETAIN** (plan cat-4 recipe was wrong here) | `testResolveWithStatsSkipsNonArrayNonPlayerForCurrentSeason` passes `new \stdClass()` *deliberately* and asserts `[]` — it proves the non-Player skip branch. Swapping in a real `Player` (the plan's cat-4 recipe) would make the method return the player and **delete the edge under test**. Intentional defer; comment added. |
| tests/LeagueSchedule/LeagueScheduleViewTest.php | 1 | **FIX** | `renderGameRow()` genuinely reads `visitorTier`/`homeTier`; prod service emits camel `gameOfThatDay`. Test literals are lazy (snake `game_of_that_day`, omit tiers). Complete literals to `LeagueGame` shape + narrow `createPageData` `@param`/`@return` to `array<string, MonthData>` (import). Faithful — render reads the added keys. |
| tests/TeamOffDefStats/TeamOffDefStatsServiceTest.php | 5 | **DEFER-PROD** | `calculateLeagueTotals`/`calculateDifferentials` bodies read `offense_games`/`raw_offense`/`raw_defense` — exactly the fixture keys. Prod `@param`/`@phpstan-type ProcessedTeamStats` over-declares `offense_totals`/`offense_averages`/`defense_totals` as required, which these methods never read. Fixtures are faithful to runtime → prod type is the bug. Leave test + entries. Suspected prod fix: split a "consumed-subset" alias or relax the param. |
| tests/TeamOffDefStats/TeamOffDefStatsViewTest.php | 5 | **RETAIN** | Empty/partial `array{}` / `list<mixed>` / `array<string,string>` fixtures exercise the empty-and-partial render branches of `TeamOffDefStatsView::render()`. Populating guts the missing-data path. |
| tests/Team/TeamViewTest.php | 1 (23 occ) | **DEFER (fixable follow-up)** | `team` fixture is a `stdClass`; `TeamPageData` wants `Team\Team`. A faithful fix = inline-anon `Team` across `createPageData` + the per-method `$team` builders (lines 107/134), but the `$overrides['team']` path types `team` as `mixed`. Higher-risk multi-site conversion — deferred to a focused follow-up, NOT mislabeled intentional. |
| tests/Team/TeamViewXssTest.php | 1 | **DEFER (fixable follow-up)** | Same `stdClass`-team root cause; XSS coverage must be preserved during the conversion. Pairs with TeamViewTest. |
| tests/Search/SearchViewTest.php | 1 (6 occ) | **DEFER (fixable follow-up)** | Helper already has a precise `@return`; only gap is `results: list<mixed>|null` vs prod union `list<StoryResult>|list<CommentResult>|list<UserResult>|null`. Faithful narrow needs importing the three result aliases + confirming each result-bearing test builds a conforming row — deferred to avoid a "trust-me" `@var` that doesn't reflect the data. |
| tests/Extension/ExtensionOfferEvaluatorTest.php | 8 | **RETAIN** | Empty `array{}` deliberately exercises `?? default` coalescing in each modifier calc. Author intent comments already present (`:146-148`, `:594`, `:604`, `:615`, `:625`, `:634`). |
| tests/DepthChartEntry/DepthChartEntryValidatorTest.php | 8 | **RETAIN** | Fixtures omit `playerData` to validate the structural/count rules independent of the player list — the missing key is the point. |
| tests/DepthChartEntry/DepthChartEntryProcessorTest.php | 7 | **RETAIN** | Partial player rows + string-coerced `pg: '1'` (mirrors `$_POST`) + comma-name CSV edge. Each exercises a degenerate-input branch. |
| tests/FreeAgency/CommonContractValidatorTest.php | 5 | **RETAIN** | Empty `array{}` offers exercise missing-year validation; `:731-733` documented. |
| tests/Waivers/WaiversProcessorTest.php | 4 | **RETAIN** | Partial player rows (`salary_yr1`/`exp` only) exercise `determineContractData` defaulting. |
| tests/Trading/TradeValidatorTest.php | 3 | **RETAIN** | Empty/partial trade-cap arrays exercise missing-key cap validation. |
| tests/Statistics/TeamStatsCalculatorTest.php | 3 | **RETAIN** | Partial/null game rows (`:421` null-row) exercise missing-field handling in `calculate()`. |
| tests/WideUnit/Schedule/ScheduleWideUnitTest.php | 2 | **RETAIN** | DB-touching WideUnit; `array<string,mixed>` games need a real `Game` object to narrow — same class as TeamSchedule, deferred. |
| tests/Voting/VotingSubmissionServiceTest.php | 2 | **RETAIN** | Comma-injection ballot (`'John Doe, My Team'`) — sanitization edge; deleting guts the coverage. |
| tests/TeamSchedule/TeamScheduleViewTest.php | 2 | **RETAIN** | `array{array<string,mixed>}` rows need a real `LeagueSchedule\Game` object to narrow — lazy double, deferred (same class as ScheduleWideUnit). |
| tests/Extension/ExtensionValidatorTest.php | 2 | **RETAIN** | `array<string,int>` offers exercise validator missing-key paths. |
| tests/AllStarAppearances/AllStarAppearancesViewTest.php | 2 | **RETAIN** | Fixture carries extra `pid` key vs sealed `array{name,appearances}`; dropping it is marginal and risks removing intent — left for a follow-up if ever widened in prod. |
| tests/Negotiation/NegotiationDemandCalculatorTest.php | 1 | **RETAIN** | Empty `array{}` teamFactors → `?? default` path. |
| tests/Player/PlayerImageHelperTest.php | 1 | **RETAIN** | `float` input proves coercion handling; documented `:189-191`. |
| tests/AwardHistory/AwardHistoryViewTest.php | 1 | **RETAIN** | `array{year:null,...}` null-row; documented `:177-179`. |
| tests/DepthChartEntry/DepthChartEntryRepositoryTest.php | 1 | **RETAIN** | String depth values mirror `$_POST`; documented `:141-143`. |
| tests/DraftPickLocator/DraftPickLocatorViewTest.php | 1 | **RETAIN** | `'Team&Name'`/`Test<script>` XSS-sanitization edge. |
| tests/CapSpace/CapSpaceViewTest.php | 1 | **RETAIN** | `'Test<script>'`/`'Team&Name'` XSS-sanitization edge. |
| tests/FreeAgency/FreeAgencyAdminProcessorTest.php | 1 | **RETAIN** | Fixture carries extra `offerTotal` key vs sealed param; dropping it is marginal — deferred. |
| tests/DatabaseIntegration/FreeAgencyRepositoryTest.php | 1 | **RETAIN** | `saveOffer` `array<string,float|int|string>` — DB-integration shape; narrowing needs the full sealed offer literal, deferred. |
| tests/Unit/Api/ApiContractTest.php | 1 | **RETAIN** | `transformDetail` fed a generic `array<string,mixed>` contract row; narrowing has low value vs the contract test's intent. |
| tests/Api/Transformer/TeamTransformerTest.php | 1 | **RETAIN** | `makeTeamRow()` base row is intentionally incomplete (used by both `transform`/`TeamListRow` open + `transformDetail`/`TeamDetailRow` sealed); a single `@return` narrow can't serve both. Deferred. |

**Count check:** FIX = 2 entries (ComparePlayers, LeagueSchedule).
DEFER-PROD = 5 (TeamOffDefStatsService). DEFER-fixable-follow-up = 3 (TeamView, TeamViewXss, SearchView).
RETAIN = remaining 65 (incl. PlayerRowTransformer, GameTransformer). Sum = 2 + 5 + 3 + 65 = **75.** ✓

Net baseline shrink: **2 entries removed** (ComparePlayers count 7, LeagueSchedule count 10 = 17
occurrences), zero added, zero weakened.

Reclassifications mid-impl (fidelity over hitting a number):
- PlayerRowTransformer FIX→RETAIN — the plan's cat-4 stdClass recipe would have deleted the
  non-Player skip path under test.
- GameTransformer FIX→RETAIN — narrowing only churned the baseline (regen-diff showed an added
  entry, zero net reduction); the real errors are intentional null-score edges.
