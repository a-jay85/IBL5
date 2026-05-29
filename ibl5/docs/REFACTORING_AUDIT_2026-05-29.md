---
description: Ranked refactoring audit of ibl5/classes — dead code, duplication, ADR-boundary findings (2026-05-29).
last_verified: 2026-05-29
---

# Refactoring Audit — `ibl5/classes/` (2026-05-29)

Audited all 84 modules / ~99K LOC. Findings respect ADR-0001 (Repo/Service/View),
ADR-0026 (hot files advisory — **size alone is not a finding**), ADR-0028 (no generic
Services/Shared buckets), ADR-0014 (centralized contract formulas).

**Split discriminator used throughout:** a large file is only flagged for splitting when its
method-groups have *independent callers*. Splitting a cohesive file hurts the token-proximity
goal and is itself over-engineering — those candidates were dropped, not reported.

Ranked by impact ÷ risk. Tier 1 = do these.

---

## Tier 1 — High impact, low risk

### 1.1 Delete the dead Player stats-view cluster — 641 LOC, zero callers
`Player/Stats/Views/Player{Season,Playoff,Heat,Olympics}StatsView.php` + their 4 `Contracts/` interfaces (8 files).
**Verified:** no references anywhere outside the files' own dir/contracts; `PlayerViewFactory` and `PlayerPageController` never instantiate them. Pure deletion. *Priority: agent ergonomics, hygiene.*

### 1.2 Centralize CP1252⇄UTF-8 conversion — 6 call sites, 2 implementations, divergent error behavior
Read-direction CP1252→UTF-8 is done **6 ways**: `iconv('CP1252','UTF-8//IGNORE')` in `PlrFileWriter:333` & `PlrLineParser:32` (drops bad bytes), `mb_convert_encoding(...,'Windows-1252')` in `PlrOrdinalMap:83`, `DraFileParser:101`, `AwaImporter:45`, and `RcbFileParser::toUtf8:96` (the only one with a null-safe fallback). The iconv calls silently differ from the mb calls on malformed input.
**Fix:** promote `RcbFileParser::toUtf8()` (or add to `PlrFieldSerializer`, which already owns the reverse `toCP1252`) and route all 6 sites through it. *Priority: maintainability, correctness, hygiene.*

### 1.3 Trading cluster ignores `Season::isOffseasonPhase()` — 6 inline duplications
`Season::isOffseasonPhase()` exists and is used correctly in FreeAgency/Waivers/CapSpace. Trading reinvents it: inline `phase === "Playoffs" || === "Draft" || ...` in `TradeValidator:102,154`, `TradeProcessor:283`, **plus** a private `TradingService::isOffseasonPhase()` (3 callers). **Verified.**
**Fix:** delete `TradingService::isOffseasonPhase()`, replace all inline checks with `$season->isOffseasonPhase()`. *Priority: maintainability.* (Note: `isOffseasonPhase()` reads `$this->phase` — call on the Season object, not pass phase string.)

### 1.4 Close the last ADR-0014 gap: `FreeAgencyOfferValidator` reimplements `CommonContractValidator`
`FreeAgencyOfferValidator::validateRaisesAndContinuity()` (`:250-299`) duplicates the gap/decrease/raise checks that `CommonContractValidator` already provides (used by Extension). Both call `ContractRules::calculateMaxRaise()`.
**Fix:** inject `CommonContractValidator`, delegate to its `validateNoGaps/SalaryDecreases/Raises`. *Priority: maintainability — finishes the ADR-0014 centralization.*

### 1.5 Centralize the points formula `2·fgm + ftm + 3·tgm`
`StatsFormatter::calculatePoints()` exists but is bypassed inline in `SeasonLeaderboardsService:257,278,293`, `ComparePlayersView:199`. SQL variants live in `SeasonLeaderboardsRepository:114`, `SeasonHighsService:33,50`.
**Fix:** use `StatsFormatter::calculatePoints()` at all PHP sites; cross-reference one docblock for the unavoidable SQL copies. *Priority: maintainability (StatsFormatter is ADR-0003-mandated).*

### 1.6 Three confirmed defects found incidentally
- **`TeamOffDefStatsView:49`** — `<h2 ...>League-wide Statistics</h1>` — tag mismatch. **Verified.** One-char fix.
- **`OneOnOneGameEngine:135`** — `$currentPossession` is a mutable instance property never reset between `simulateGame()` calls; second call on the same instance starts with stale possession. Latent (engine is new'd per request). Make it a local threaded through `runPossession()`.
- **`PlrParserService:189`** — `computeDerivedFields()` does a `getTeamnameFromTeamID()` DB lookup per player (~450/import) and the resulting `'teamName'` key is **never consumed** by `upsertPlayer`/`buildSnapshotData`. Real N+1 producing a discarded value. Delete it.

---

## Tier 2 — Medium impact

### 2.1 `TeamQueryRepository` holds business logic that belongs in a service
`canAddContractWithoutGoingOverHardCap`, `canAddBuyoutWithoutExceedingBuyoutLimit`, `getSalaryCapArray`, `getTotalCurrentSeasonSalaries` (`:319-451`) compute cap compliance — they touch `League::HARD_CAP_MAX`, `Team::BUYOUT_PERCENTAGE_MAX`, aggregate `Player` objects. All callers are services (`CapSpaceService`, `FreeAgencyCapCalculator`, `ExtensionService`). This is the ADR-0001 Repository-is-data-access boundary leaking. Medium-term: extract to a `CapCalculator` collaborator; repo exposes raw rows. *Priority: maintainability.*

### 2.2 Hard-wired `new` deps blocking testability (consistent pattern across cluster)
- `TeamService::prepareDraftPicksData:459` — `new TeamQueryRepository`/`new League` inside a private method.
- `TeamQueryRepository::canAddBuyout...:427` — `new Season($this->db)` while every sibling method takes `Season` as a param.
- `TradeOffer:53-57` / `TradeProcessor:56-60` — `TradeCashRepository`, `BuyoutLedgerRepository`, `TradeExecutionRepository`, `TradeValidator` always `new`'d though `TradeOfferRepository` is injectable.
- `InjuriesService` — takes raw `\mysqli`, builds `League` internally; no repository (only service in scope doing this).
**Fix:** promote to optional ctor params (`?Type $x = null`), the pattern `ExtensionService`/`CapSpaceService` already use. *Priority: maintainability/testability.*

### 2.3 Cap/salary-slot lookups duplicated
- `match($cy){1=>salary_yr1...}` slot lookup duplicated in `TeamQueryRepository:361` & `:438`; also in `Player/Contract/PlayerContractCalculator:34` & `PlayerContractValidator:192`. Extract `salaryForContractYear(array $row, int $cy): int` (move to `PlayerData` for the Player pair).
- Cash-record cy-offset walk duplicated `TradeOffer::sumCashRecordSalaries:249` vs `FreeAgencyCapCalculator::calculateTotalSalaries:69`.
*Priority: maintainability — these are ADR-0014-adjacent cap formulas.*

### 2.4 Duplicated stat-expression & date-filter registries within `RecordHolders`
`RecordHoldersService` (`:73-99` PLAYER_STAT_EXPRESSIONS, `:123-127` DATE_FILTERS) and `RecordBreakingDetector` (`:32-58`, `:439-447`) each declare the same 9 player + 8 team SQL expressions and the same 3 game-type filters in different shapes.
**Fix:** one `RecordStatDefinitions` value object in the `RecordHolders` namespace; both build their own-shaped arrays from it. *Priority: maintainability — a column rename currently needs edits in both.*

### 2.5 View layer doing Service work
- `FreeAgencyView::renderPlayersUnderContract:97-120` — calls `canRookieOption/canRenegotiateContract` and branches URL building (business routing in a View). Push a `contractAction` scalar from the Service; View becomes a `match`.
- `TradingView::renderTeamSelectionLinks:300-337` — conference split + 2× `usort` + interleave (data transform in a View). Move to `TradingService::buildTeamList()`.
*Priority: readability, ADR-0001 View role.*

### 2.6 Identical ratings/markup blocks duplicated across modules
- 21-cell player-ratings table rendered in both `FreeAgencyFormComponents::renderPlayerRatings:42` and `NegotiationOfferView::renderPlayerRatings:237`. Make the FA one `public static`, have Negotiation call it.
- `TradingView` repeats a 5-key alert map verbatim at `:68` and `:225` → one `private const`.
- `DepthChartEntryView` dcValue clamp (0..5) duplicated in desktop `:183` and mobile `:360` paths → `clampDepthValue()`.
- `TeamRepository::getRegularSeasonHistory:176` vs `getHEATHistory` — identical but for `game_type` 1 vs 3 → one `getSeasonHistory($name, $gameType)`.
*Priority: readability, proximity.*

### 2.7 `RecordHoldersView` — 5 near-identical table-block renderers (~35 LOC each)
`:232-395` five renderers share colgroup→thead→tbody-loop scaffold; only column count, CSS class, row delegate differ. **Same caller flow**, so this is a *helper extraction within the file* (not a split): `renderCategoryTable(class, colgroup, thead, callable $rowRenderer)`. Shrinks file ~100 LOC. *Priority: readability.*

### 2.8 More dead surface (verified per agent grep)
- `Player::getTeamCity():183` — body is `return null;` always; column never existed. Remove from interface + facade + tautological tests. **Verified.**
- `JsbImportRepository::resolveTeamId(int)` — no callers (everything uses `resolveTeamIdByName`); identity map 0-28. Remove from interface + impl.
- `ProjectedDraftOrderService::applyTiebreakers` `$direction` param — never read, single hard-coded `'better_wins'` caller. Remove.
- `PlayerDatabaseView` ctor — injected `$service` never used (suppressed w/ phpstan-ignore). Remove param.
- `PlayerStats::withPlayerObject` — no production callers, does a redundant `loadByID` round-trip on an already-hydrated Player.
- `PlayerStats` `season/careerPlayoffDouble/TripleDoubles` — 4 props hardcoded `0`, no DB column, rendered as `0` in trading-card back. Remove or wire.

---

## Tier 3 — Low / cosmetic (do opportunistically)

- **`safeHtmlOutput()` vs `e()` drift** — 299 long-form calls across 54 files; `e()` is the documented View alias (71 files). Standardize Views on `::e()`. Real noise but ~300-site churn → batch into one mechanical PR, not piecemeal. *Priority: readability.*
- **Magic `82` (games/season)** in `StandingsUpdater:285,353,412,463` → add `League::GAMES_PER_SEASON`.
- **Magic `1440` (max player ordinal)** in PlrParser declared 3×; `PlrLineParser:26` uses a bare literal. Reference `PlrFileWriter::MAX_PLAYER_ORDINAL` everywhere.
- **`+1` JSB-year decode** duplicated in `RcbFileParser:343,428`, `TrnFileParser:119`, `HisFileParser:102` → `decodeJsbYear(int): int`.
- **`object` type-erasure** in `PlayerPageServiceInterface:33,59` (`object $userTeam, object $season` + inline `@var` casts) → concrete `Team`/`Season`; single caller already passes concrete types.
- **`CommonContractValidator` namespaced under `FreeAgency`** but imported by `Extension` — cross-module dependency. Move to root namespace beside `ContractRules`.
- **`SeasonHighsService` `HOME_AWAY_STATS`** is `STATS` minus TURNOVERS, hand-maintained → derive via `array_diff_key`.
- **`RecordHolders` All-Star stub fields** (`teams/teamTids/years` always `''`) + unreachable View branch (`RecordHoldersView:420-431`) → remove or wire from `ibl_awards`.
- **`RecordHoldersView` raw `modules.php?...teamid=` URLs** at `:284,535,537` while using `TeamCellHelper::teamPageUrl()` elsewhere in the same file → use the helper consistently.
- **`AuthService::getCookieArray():181` legacy positional array** (indices 2-10 dead stubs; only `[1]`=username read) → expose `getUsername()` and migrate the 14 module callers off positions.
- **`BulkImportRunner` / `ArchiveExtractor`** use bare `echo` for progress (24 calls) → inject an output callable for testability.
- **`TopicsView:11-67`** defines 15 PHP-Nuke `define()` fallbacks at file scope (only View doing this) → move to a bootstrap stub.
- **Api controllers** repeat `new ETagHandler()` + `new Repo()` + `new Transformer()` in every `handle()` (~17 controllers). A thin `AbstractApiController` with `protected readonly ETagHandler` is fine (stays in `Api` namespace — **not** a generic bucket). Repos/transformers should be injected, not `new`'d, for test substitution.

---

## Dropped (would re-litigate settled decisions or hurt proximity)

- "Split RecordHoldersRepository (974 LOC)" — its announcement-tracking trio and record-query methods are confirmed cohesive within the file; private caching helpers (`getRegularSeasonGames`, `resolveTeamName`) are correctly scoped. No independent-caller seam → splitting hurts proximity.
- "Bootstrap is large" — ADR-0029/0030 intentional composition wiring.
- `LeagueControlPanelView`/`UpdaterView` rendering full `<!DOCTYPE>` docs — intentional standalone admin tools (worth a one-line comment, not a refactor).
- `TeamRepository` vs `TeamQueryRepository` — coherent split *except* `getFreeAgencyRoster`/`getRosterUnderContract` (`TeamRepository:307`) which duplicate ordered variants in `TeamQueryRepository` and have a single caller (`TeamTableService`); consolidate those two methods (see 2.6 spirit) but keep the two-repo split.

## Healthy — no action
`ApiKeys, TrainingCampRatingsDiff, DraftPickLocator, FreeAgencyPreview, PlayerMovement, TransactionHistory, Mail, Cache, Debug, Validation, Scripts, Migration, Navigation, Voting, Settings, Security/CsrfGuard, ActivityTracker`. JsbParser & core Player architecture are well-executed.
