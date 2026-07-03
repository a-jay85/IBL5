---
description: Historical archive: completed/declined maintenance-audit findings, extracted from maintenance-backlog.md.
last_verified: 2026-07-03
---

# Maintenance-Cost Reduction Backlog — Archive

Read-only historical record of ✅ Implemented / 🚫 Declined findings. For OPEN items see ../maintenance-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

## Axis 1: God Classes / Large Files (>500 LOC)

### 1.1 RecordHoldersService — Hardcoded Team Registry + Multi-Concern Formatter
**Location:** `ibl5/classes/RecordHolders/RecordHoldersService.php`
**Problem:** Contains a 28-entry hardcoded `TEAM_REGISTRY` constant that duplicates DB data, plus seven distinct `format*` private methods, two essentially identical `getTeamAbbreviationByName`/`getTeamIdByName` helpers, and two stub methods (`getAllStarYears`, `getAllStarTeams`) that silently return empty. Mixes team-lookup infrastructure with record-formatting concerns.
**Suggested direction:** Extract `TeamRegistry` (or reuse `League`/`TeamQueryRepository`); implement or delete the two silent stubs.
**Est. effort:** M
**Risk if untouched:** Team rename requires DB and constant update; silent stubs create invisible display gaps.
**Status:** Implemented — registry/stubs collapsed (2026-05-19, −39 LOC: `nameToIdCache`→static lookup, `getAllStarYears`/`getAllStarTeams` deleted); the multi-concern `format*` split completed via `RecordFormatter` extraction (#1167, merged 2026-06-21). Residual ~687 LOC is typed-getter/query bulk, not this concern.

### 1.2 RecordHoldersRepository — Streak/Season-Start Logic in Repository Layer
**Location:** `ibl5/classes/RecordHolders/RecordHoldersRepository.php` lines 401-566
**Problem:** `getLongestStreak()` and `getBestWorstSeasonStart()` contain in-PHP iteration and state-machine logic that belongs in the Service layer. A 100-line private `buildMostTitlesByTypeQuery()` further bloats the file.
**Suggested direction:** Move streak/season-start computation to `RecordHoldersService` or a dedicated `StreakCalculator`; extract HEAT champion CTE into a DB view.
**Est. effort:** M
**Risk if untouched:** Streak/season-date logic must be found inside a repository — a persistent layer violation attracting future bugs.
**Status:** Completed — StreakCalculator extracted from RecordHoldersRepository (merged #1040, maintenance-38); HEAT-champion CTE relocated to `vw_heat_champions` view via migration 149 (merged #1090, maintenance-47).

### 1.3 RecordBreakingDetector — Discord Responsibility Bleed
**Location:** `ibl5/classes/RecordHolders/RecordBreakingDetector.php`
**Problem:** Mixes record-change detection with Discord notification dispatch; `sendDiscordNotification` is tightly coupled to detection, making it untestable without Discord.
**Suggested direction:** Return announcement list from `detectAndAnnounce()`; or inject `AnnouncementDispatcher` interface with a null impl for tests.
**Est. effort:** S
**Risk if untouched:** Discord outage silently aborts announcements mid-way; dry-run impossible without modifying class.
**Status:** Implemented (2026-06-25) — injected `AnnouncementDispatcherInterface` (defaulted to `DiscordAnnouncementDispatcher`); `detectAndAnnounce()` dispatches per-message with `try/catch` isolation so a Discord outage no longer aborts the loop; `NullAnnouncementDispatcher` enables dry-run/testable detection. Private `sendDiscordNotification()` removed.

### 1.4 JsbImportService — One Class Handling 10 File Formats
**Location:** `ibl5/classes/JsbParser/JsbImportService.php` (853 lines)
**Problem:** Single service implements `processCarData`, `processTrnData`, `processHisData`, `processAswData`, `processAwaData`, `processRcbData`, `processPlbData`, `processDraData`, `processRetData`, `processHofData` — 10 independent binary-format importers with no shared logic.
**Suggested direction:** Extract per-format importers injected into a thin `JsbImportOrchestrator`.
**Est. effort:** L
**Risk if untouched:** Adding/changing one format requires reading 800+ lines; bug in one importer's type handling cascades into adjacent code during reviews.
**Status:** Completed (2026-05-19) — split into 10 per-format importers under `JsbParser/Importers/`; `JsbImportService` is a 177-LOC thin facade. `JsbImportServiceInterface` unchanged.

### 1.5 ProjectedDraftOrderService — Sorting + Tiebreaker Logic at 600+ Lines
**Location:** `ibl5/classes/ProjectedDraftOrder/ProjectedDraftOrderService.php` (615 lines)
**Problem:** Full draft-order pipeline + multi-way tiebreaker comparators, head-to-head matrix, `resolveTiedGroups` pass, playoff seeding, conference-winner logic, pick ownership resolution.
**Suggested direction:** Extract `DraftOrderTiebreakerResolver` for H2H/PD sorting and `PlayoffSeedingCalculator` for seeding; service becomes a thin orchestrator.
**Est. effort:** M
**Risk if untouched:** Tiebreaker logic is unit-test-hostile at 600 LOC; any rule change forces reasoning across the whole service.
**Status:** Completed (merged #1148) — extracted `NonHeadToHeadTiebreaker`, `DraftOrderTiebreakerResolver`, and `PlayoffSeedingCalculator`; service is now a ~386-LOC thin orchestrator.

### 1.6 StandingsView — Cross-Cutting State and Dual-Path Rendering
**Location:** `ibl5/classes/Standings/StandingsView.php` (609 lines)
**Problem:** Three nullable cache properties populated on `render()` but must be conditionally re-fetched in `renderRegion()` — two code paths for the same data loading. `adaptBulkRows()` translates between two almost-identical row shapes. `sortStandings()` replicates SQL ORDER BY in PHP.
**Suggested direction:** Unify data-loading behind a single lazy-load helper; remove `adaptBulkRows()` by canonicalizing the repository row shape; push PHP sort back into SQL.
**Est. effort:** M
**Risk if untouched:** Dual-path loading returns inconsistently sorted/enriched data when shared state is partially populated.
**Status:** Completed (merged #1146) — unified bulk-data loading behind a single lazy-load helper so `render()` and `renderRegion()` can no longer diverge; byte-identical output (green-green refactor).

### 1.7 TeamService — Data Preparation Mixed with View Instantiation
**Location:** `ibl5/classes/Team/TeamService.php` (555 lines)
**Problem:** Prepares data AND directly instantiates view classes (`BannersView`, `CurrentSeasonView`, `AwardsView`, etc.), storing rendered HTML in an array. `prepareBannerData()` (60 LOC) does banner-type classification; `preparePlayoffData()` (70 LOC) maps DB rows to typed round aggregates.
**Suggested direction:** Keep `TeamService` as pure data-prep; move view instantiation to `TeamPageController` or the calling `index.php`.
**Est. effort:** M
**Risk if untouched:** Business logic transformations locked inside a class also owning HTML output — both harder to test independently.
**Status:** Completed (merged #1144) — extracted `TeamPageDataPreparer` (data-prep) and `TeamCardRenderer` (view instantiation); `TeamService` is now a ~115-LOC orchestrator.

### 1.8 FreeAgencyView — Direct DB Access Inside View Layer
**Location:** `ibl5/classes/FreeAgency/FreeAgencyView.php` (605 lines)
**Problem:** Directly instantiates `TeamQueryRepository` (L26) and `BuyoutLedgerRepository` (L133) inside `renderPlayersUnderContract()`; creates `Player` objects via `Player::withPlrRow()` inside renders — executing DB queries during HTML generation.
**Suggested direction:** Move roster/cash fetching to the service layer; pass pre-fetched arrays into render methods.
**Est. effort:** M
**Risk if untouched:** Slow queries inside renders block page output; view can't be unit-tested or screenshotted without a live DB.
**Status:** Completed (verified 2026-05-29 audit) — constructor now accepts injected `TeamIdentityRepositoryInterface`; no inline `new TeamQueryRepository`/`new BuyoutLedgerRepository`; render methods receive pre-fetched arrays.

### 1.9 SavedDepthChartService — Three Separate Fetches for the Same Active DC
**Location:** `ibl5/classes/SavedDepthChart/SavedDepthChartService.php` (593 lines)
**Problem:** `saveOnSubmit()`, `buildCurrentLiveLabel()`, and `getDropdownOptions()` each call `getActiveDepthChartForTeam()` separately; `getDropdownOptions()` re-fetches inside `buildDropdownLabel()`. Constructor does `new SavedDepthChartRepository($db)` rather than accepting an interface.
**Suggested direction:** Memoize active DC per call chain; accept `SavedDepthChartRepositoryInterface`.
**Est. effort:** S
**Risk if untouched:** Every DC dropdown render triggers 3+ redundant queries; pattern proliferates.
**Status:** Completed (PR for finding 1.9) — `SavedDepthChartService` constructor now accepts an optional `SavedDepthChartRepositoryInterface` (defaults to `new SavedDepthChartRepository($db)`); the active-DC fetch is memoized per teamid via private `getActiveDc()`, reused by `buildCurrentLiveLabel()`, `getDropdownOptions()`, and `nameOrCreateActive()` — collapsing the two redundant reads in `SavedDepthChartApiHandler::handleList()` into one. Cache invalidated after `nameOrCreateActive()` writes. Green-green.

### 1.10 Player (facade) — 50+ Public Properties Duplicating PlayerData
**Location:** `ibl5/classes/Player/Player.php` (558 lines)
**Problem:** 50+ public nullable properties (L44-252) duplicated from `PlayerData` and synced via `syncPropertiesFromPlayerData()`. Adding a field requires 4 changes: `PlayerData`, `Player`, `PlayerRepository::FIELD_MAP`, `syncPropertiesFromPlayerData()`.
**Suggested direction:** Deprecate direct property access; add typed getters delegating to `$this->playerData`; remove parallel properties.
**Est. effort:** L
**Risk if untouched:** New properties land in wrong place causing PHPStan errors or silent nulls; 4-place-update is a persistent bug attractor.
**Status:** Completed (verified 2026-05-29 audit) — public nullable properties replaced by typed getters delegating to `$this->playerData`; `syncPropertiesFromPlayerData()` deleted.

### 1.11 BoxscoreProcessor — Hardcoded 30-Parameter Method Call
**Location:** `ibl5/classes/Boxscore/BoxscoreProcessor.php` lines 260-295 (557 LOC)
**Problem:** `insertTeamBoxscore(...)` is called with 30 positional arguments — reordering, adding, or removing any column requires auditing 30 positional matches. Processor also calls `flush()` every 50 games — leaks HTTP concern into the domain layer.
**Suggested direction:** Array-parameter version of `insertTeamBoxscore()`; move `flush()` into an injectable `ProgressReporter` callback.
**Est. effort:** S
**Risk if untouched:** Column reorder breaks silently; `flush()` corrupts tests/queued jobs.
**Status:** Implemented — `insertTeamBoxscore()` now takes a typed array-keyed `$row` (SQL/bind type string unchanged); `flush()` moved behind `Boxscore\Contracts\ProgressReporterInterface` (`FlushProgressReporter` default, `NoOpProgressReporter` for tests). Pinned by the all-34-column byte-identical round-trip in `tests/DatabaseIntegration/BoxscoreRepositoryTest.php`.

### 1.12 SeasonArchiveView — Two Fundamentally Different Pages ✓ Done

Split completed in PR #1145. `SeasonArchiveView.php` deleted; replaced by `ibl5/classes/SeasonArchive/SeasonArchiveIndexView.php`, `ibl5/classes/SeasonArchive/SeasonDetailView.php`, and shared `ibl5/classes/SeasonArchive/SeasonArchiveRenderHelpers.php` trait. Output byte-identical (verified by golden-master snapshots).

### 1.13 SeasonArchiveService — Mutable Instance State Accumulator
**Status:** ✅ Implemented — `$collectedPlayerNames` instance property replaced by a local variable in `getSeasonDetail()` passed by reference into `extractAward()`/`extractAwardList()` (optional `&$collected = []` param). Cross-call contamination structurally impossible; `getAllSeasons()` unchanged. Green-green (existing `testAccumulatorResetsBetweenCalls` + new `testReusedInstanceCollectsSameNamesAsFreshInstance`).
**Location:** `ibl5/classes/SeasonArchive/SeasonArchiveService.php` line 35 (530 LOC)
**Problem:** `private array $collectedPlayerNames = []` is a side-effect accumulator populated during `extractAward()`/`extractAwardList()` inside `getSeasonDetail()`. Reset only at start of `getSeasonDetail()` — not on construction — creating cross-contamination risk if reused.
**Suggested direction:** Replace with a local variable passed into extract helpers; or use a `PlayerNameCollector` value object.
**Est. effort:** S
**Risk if untouched:** Reusing the service instance across detail calls accumulates stale names.

### 1.14 TradeOffer — Salary Cap Calculation in an "Offer" Class
**Location:** `ibl5/classes/Trading/TradeOffer.php`
**Problem:** Contains `calculateSalaryCapData()` (50 LOC) and `sumCashRecordSalaries()` (30 LOC) — cap computation embedded in a class also managing offer creation, Discord notifications, cash persistence. Constructor instantiates 5 collaborators via `new`.
**Suggested direction:** Extract `TradeCapCalculator`; inject collaborators.
**Est. effort:** M
**Risk if untouched:** Cap bugs buried in offer-creation code; CY-offset logic mixed with offer-writing.
**Status:** Completed (merged #1143) — extracted `TradeCapCalculator`; cap-math (`calculateSalaryCapData`/`sumCashRecordSalaries`) moved out of the offer class.

### 1.16 DepthChartEntryView — Business Logic Inside View
**Location:** `ibl5/classes/DepthChartEntry/DepthChartEntryView.php` line 167
**Problem:** `computeJsbProduction()` implements a JSB engine formula (`2×FGM + TGM + FTM + ORB + DRB + AST + STL + BLK`) inside a View. View also `echo`s directly (inconsistent with codebase).
**Suggested direction:** Move to `DepthChartEntryService` or `JsbProductionCalculator`; migrate to `return`-based output.
**Est. effort:** S
**Risk if untouched:** Formula invisible to tests; will silently break if column names change.
**Status:** Completed (verified 2026-05-29 audit) — `computeJsbProduction()` moved to `DepthChartEntryService`; View now calls `$this->service->computeJsbProduction()`.

### 1.17 BaseMysqliRepository — Slow-Query Logging via Static Global
**Location:** `ibl5/classes/BaseMysqliRepository.php` lines 225-237 (481 LOC)
**Problem:** `executeQuery()` calls `\Logging\LoggerFactory::getChannel('perf')` — a static global dependency that can't be swapped for tests. `logError()` has the same pattern.
**Suggested direction:** Accept `\Psr\Log\LoggerInterface $perfLogger = null` in constructor.
**Est. effort:** S
**Risk if untouched:** Repository unit tests require full logging subsystem; slow-query behavior can't be disabled for tests.
**Status:** Completed — slow-query perf logging made injectable via a `setPerfLogger()` setter + `$this->perfLogger ?? \Logging\LoggerFactory::getChannel('perf')` fallback at L281 (mirrors the existing `setLogger()`/`db`-channel precedent at L129/L585; no constructor-signature change, so all 24 subclasses are untouched). The last static logging global in `executeQuery()` is gone.

### 1.20 SearchView — String-Concatenation HTML at 485 Lines
**Location:** `ibl5/classes/Search/SearchView.php`
**Problem:** String concatenation (no `ob_start()`) for form, story/comment/user results, pagination. Three result-type renderers are duplicated 40-60 LOC each.
**Suggested direction:** Extract `renderResultTable(string $title, array $headers, array $rows): string`; migrate to `ob_start()`.
**Est. effort:** S
**Risk if untouched:** New result types require copy-paste; `NukeCompat` dep invisible in type signature.
**Status:** Implemented (2026-06-26) — migrated all renderers to `ob_start()` and extracted shared `renderResultList()` scaffold; byte-identical output (golden-master test + VR pin); escaping preserved (`RequireEscapedOutputRule` green).

---


## Axis 2: Module Structure Inconsistency

### 2.2 ActivityTracker / AllStarAppearances — No Service Layer
**Location:** `classes/ActivityTracker/`, `classes/AllStarAppearances/`
**Problem:** Repository + View, no Service. Business logic lives in repository (SRP violation) or in `index.php`.
**Suggested direction:** Extract calculation/assembly to a thin Service.
**Est. effort:** S each
**Risk if untouched:** Queries carry business logic; no seam to unit-test rules separately from DB.

### 2.4 GMContactList / Topics — No Service Layer; Topics Cross-Module Coupling
**Location:** `classes/GMContactList/`, `classes/Topics/`
**Problem:** No Service. Topics' `index.php` directly depends on `SearchRepository` from another module.
**Suggested direction:** Add Services; move cross-module dep inside the Service.
**Est. effort:** S each
**Risk if untouched:** Cross-module breakage when `SearchRepository` changes.

### 2.6 Draft — No Service Layer; Two Legacy Globals + Mis-Named Handler
**Location:** `classes/Draft/`, `modules/Draft/index.php`
**Problem:** Has Processor/Repository/Validator/View, no Service. `index.php` defines `userinfo()`/`main()` globals. `DraftSelectionHandler` duplicates what a Controller would do.
**Suggested direction:** Add `DraftService`; promote `DraftSelectionHandler` to `DraftController` (or merge).
**Est. effort:** M
**Risk if untouched:** Globals untestable; `DraftSelectionHandler` confuses future contributors.
**Status:** Completed (2026-06-28, #1240) — DraftService + DraftBoardData added; DraftSelectionHandler promoted to DraftController; userinfo()/main() globals removed from modules/Draft/index.php.

### 2.7 Injuries — No Repository Layer
**Location:** `classes/Injuries/`
**Problem:** Service + View, no Repository. Queries are either in Service (SRP) or delegated to `CommonMysqliRepository` (cross-cutting utility, not domain repo).
**Suggested direction:** Extract `InjuriesRepository extends BaseMysqliRepository`; add to Contracts/.
**Est. effort:** S
**Risk if untouched:** Injury queries un-traceable without reading service body.
**Status:** Completed — InjuriesRepository already extracted and injected (PR #970, 2026-06-03); backlog was stale.

### 2.8 Search / Standings — No Service Layer
**Location:** `classes/Search/`, `classes/Standings/`
**Problem:** Both have Repository + View, no Service. `AggregateTiebreaker` is a floating class with no orchestrator.
**Suggested direction:** Wrap in a Service; for Search a pass-through maintains the pattern.
**Est. effort:** S each
**Risk if untouched:** Tiebreaker class has no obvious home; contributors may bypass it.
**Status (Search):** Declined — pass-through Service is dead code; SearchRepository is the correct seam.
**Status (Standings):** Declined — AggregateTiebreaker has a home (StandingsView + ProjectedDraftOrderService); no orchestration exists to wrap.

### 2.9 DraftHistory — No Service; ApiHandler Naming Ambiguous
**Location:** `classes/DraftHistory/`
**Problem:** Repository + View + `DraftHistoryApiHandler`, no Service. ApiHandler implies REST API namespace.
**Suggested direction:** Rename to Controller; add Service for sorting/grouping.
**Est. effort:** S
**Risk if untouched:** Wrong-namespace confusion; unclear where new actions go.
**Status:** Declined — *ApiHandler is the established HTMX-fragment convention (9 handlers); rename would harm consistency. No Service target exists.

### 2.11 RookieOption — No Service; Mis-Named FormView
**Location:** `classes/RookieOption/`
**Problem:** Controller/Repository/Validator/`RookieOptionFormView`. No Service; `FormView` is non-canonical.
**Suggested direction:** Add `RookieOptionService`; rename to `RookieOptionView`.
**Est. effort:** S
**Risk if untouched:** Service-level logic ends up in Controller/Validator; FormView name visible inconsistency.
**Status:** Completed (2026-06-09) — renamed RookieOptionFormView → RookieOptionView / RookieOptionViewInterface. Service half declined as pass-through ceremony (RookieOptionController owns orchestration).

### 2.12 Negotiation — No Standalone Module; Six Non-Standard Role Names
**Location:** `classes/Negotiation/`
**Problem:** `NegotiationDemandCalculator`, `NegotiationDemandsBreakdownView`, `NegotiationOfferView` are non-canonical. No `modules/Negotiation/`.
**Suggested direction:** Consolidate `BreakdownView` as a sub-view; document the Player sub-domain relationship.
**Est. effort:** S
**Risk if untouched:** Three view-ish files with different conventions confuse the View boundary.
**Status:** ✅ Implemented (2026-06-26) — `NegotiationOfferView` canonicalized under 4.22; `NegotiationDemandsBreakdownView` → `Negotiation\Views\DemandsBreakdownView` sub-view (this PR). `NegotiationDemandCalculator` is a legitimate Calculator role (see 4.8).

### 2.16 DebugMenu Module — No `classes/DebugMenu/`; Business Logic in `index.php`
**Location:** `modules/DebugMenu/index.php`
**Problem:** `toggleExtensions()`, `sanitizeRedirect()` defined as globals. Related class `DebugSession` lives in `classes/Debug/` (mismatched name).
**Suggested direction:** Move globals into `Debug\DebugController`; align names.
**Est. effort:** S
**Risk if untouched:** `sanitizeRedirect()` is security-sensitive and shadowable as a global.
**Status:** Completed — `toggleExtensions()`/`sanitizeRedirect()` moved into `Debug\DebugController`; `modules/DebugMenu/index.php` now `use`s it (verified 2026-06-20).

### 2.19 SiteStatistics Module — Empty Placeholder
**Location:** `modules/SiteStatistics/` (only `language/` subdir)
**Problem:** No `index.php`, no classes — listed in nav, renders nothing.
**Suggested direction:** Implement or remove.
**Est. effort:** S (remove) / M (implement)
**Risk if untouched:** Blank page or error if referenced.
**Status:** Completed (#733) — `modules/SiteStatistics/` deleted (verified absent 2026-06-20).

### 2.20 Schedule Module — No `classes/Schedule/`; Splits Across Two Namespaces
**Location:** `modules/Schedule/index.php`, `classes/LeagueSchedule/`, `classes/TeamSchedule/`
**Problem:** `index.php` (82 LOC) orchestrates both `LeagueSchedule\*` and `TeamSchedule\*` via conditional. Directly instantiates 7 classes.
**Suggested direction:** Create `Schedule\ScheduleController` that delegates to either service.
**Est. effort:** S
**Risk if untouched:** Branching only in `index.php`; untestable; new schedule types bolt onto it.
**Status:** ✅ Implemented (2026-06-26) — `Schedule\ScheduleController::render(int)` owns the league/team branch; `modules/Schedule/index.php` is now thin glue. League + team pages verified byte-identical via existing E2E flows.

### 2.22 `Services/` — Catch-All Mixing Cross-Cutting and Domain
**Location:** deleted (2026-05-16)
**Problem:** Contains both legitimate utilities (`CommonMysqliRepository`, `CommonValidator`, `ValidationResult`, `QueryConditions`) and domain classes (`NewsService`, `PlayerDataConverter`, `CommonContractValidator`). Contracts/ only covers one.
**Suggested direction:** Move domain classes to their home modules; keep cross-cutting utilities; add interfaces.
**Est. effort:** M
**Risk if untouched:** Dumping ground grows; missing interfaces block tests.
**Status:** Completed (2026-05-16) — domain classes relocated to home modules; cross-cutting to Validation/ and Repositories/; Services/ deleted entirely.

### 2.23 `Shared/` — Minimal Catch-All
**Location:** deleted (2026-05-16)
**Problem:** `SharedRepository` (draft picks + extension) and `SalaryConverter` (utility) with no shared concern.
**Suggested direction:** Move `SalaryConverter` to `BasketballStats/`; split `SharedRepository` into module repositories.
**Est. effort:** S
**Risk if untouched:** `SharedRepository` will accumulate misfits into a god repo.
**Status:** Completed (2026-05-16) — SalaryConverter moved to BasketballStats/; Shared/ deleted entirely.

### 2.24 Navigation — Interface Files Stranded Outside `Contracts/`
**Location:** `classes/Navigation/Contracts/`
**Problem:** Only `NavigationMenuBuilderInterface` and `NavigationRepositoryInterface` in Contracts; `NavigationView` and 4 sub-views (`DesktopNavView`, `MobileNavView`, `LoginFormView`, `TeamsDropdownView`) lack interfaces. `NavigationConfig` non-canonical role.
**Suggested direction:** Add `NavigationViewInterface`; consider sub-view interfaces; rename `Config` if applicable.
**Est. effort:** S
**Risk if untouched:** Views unmockable; architecture hard to explain.
**Status:** ✅ Implemented (2026-06-26) — `NavigationViewInterface` + `DesktopNavViewInterface`/`MobileNavViewInterface`/`LoginFormViewInterface`/`TeamsDropdownViewInterface` added; all five views implement them.

### 2.26 Updater — CLI-Only, No `modules/` Entrypoint
**Location:** `classes/Updater/`, `scripts/updateAllTheThings.php`
**Problem:** Full canonical Controller/Service/View + 11 Steps, but instantiated from a standalone script. No web-accessible route or `modules/Updater/`. Architectural layer ambiguous.
**Suggested direction:** Document explicitly (ADR or README) as CLI-only module.
**Est. effort:** S (document)
**Risk if untouched:** Confusion about why there's no module entrypoint.
**Status:** ✅ Implemented (2026-06-21) — documented in `classes/Updater/README.md`. Correction: the audit's "CLI-only / no web-accessible route" label was inaccurate — the Updater is **web-only**, invoked via the root-level admin POST endpoint `scripts/updateAllTheThings.php` (the LCP "Update All The Things" button). True observation retained: there is no `modules/Updater/` route and no CLI entry point.

### 2.30 Statistics / StrengthOfSchedule — Single-Class Modules With No Contracts
**Location:** `classes/Statistics/`, `classes/StrengthOfSchedule/`
**Problem:** Each holds one calculator with no Contracts/Repository/View. Not self-contained modules.
**Suggested direction:** Move both to `BasketballStats/` (alongside `StatsFormatter`) or a new `Analytics/`.
**Est. effort:** S each
**Risk if untouched:** Two-file dirs proliferate the pattern; codebase fragments.
**Status:** ✅ Implemented (2026-07-02) — moved into BasketballStats\ namespace (this PR).

### 2.34 Draft Selection — Standalone POST Handler
**Status:** Completed — collapsed into `modules/Draft/index.php?op=select`.
**Location:** `modules/Draft/draft_selection.php` (deleted)
**Problem:** `require __DIR__/../../mainfile.php` directly; returns bare JSON/HTML. Same anti-pattern as Voting handlers.
**Suggested direction:** Move into `modules/Draft/index.php?op=select` or `DraftController::handleSelection()`.
**Est. effort:** S
**Risk if untouched:** Bootstrap changes must be applied to standalone files separately.

### 2.35 ASGVote / EOYVote — Duplicate Standalone Handlers
**Status:** Completed — collapsed into `modules/Voting/index.php?op=submit_asg` and `?op=submit_eoy`.
**Location:** `modules/Voting/ASGVote.php`, `EOYVote.php` (deleted)
**Problem:** Both `require mainfile.php`; nearly identical, differing only by vote-type constant.
**Suggested direction:** Merge into `modules/Voting/index.php` as `?op=submit_asg|submit_eoy`; `VotingSubmissionService` already accepts vote-type param.
**Est. effort:** S
**Risk if untouched:** Submission-flow changes must be applied twice; drift.

### 2.36 ProjectedDraftOrder — Two Entrypoints With Duplicate Auth
**Status:** Completed (2026-06-05) — `save_order.php` folded into `index.php?op=save_order` via an early-return guard (single op, no named functions); `jslib/draft-order-drag.js` + both E2E specs updated `file=` → `op=`.
**Location:** `modules/ProjectedDraftOrder/index.php`, `save_order.php` (deleted)
**Problem:** `save_order.php` is a JSON POST handler with its own auth check, hiding in the module dir.
**Suggested direction:** Move into `index.php?op=save` or an `ApiHandler` class.
**Est. effort:** S
**Risk if untouched:** Two auth implementations; access-control auditing harder.

### 2.37 TeamOffDefStats — `view.php` Partial-Template Anti-Pattern
**Status:** Completed (2026-06-05) — `view.php`'s 3 meaningful lines (`header()` / `echo $leagueStatsHtml` / `footer()`) inlined into `index.php`; partial template deleted.
**Location:** `modules/TeamOffDefStats/index.php`, `view.php` (deleted)
**Problem:** `view.php` is a 9-line template echoing `$leagueStatsHtml`, an implicit contract between two files.
**Suggested direction:** Eliminate `view.php`; call `echo $view->render()` directly in `index.php`.
**Est. effort:** S
**Risk if untouched:** Implicit contract; silent `undefined variable` errors on rename.

### 2.38 `Topics/copyright.php` — Dead PHP-Nuke Boilerplate
**Status:** Completed (2026-06-05) — deleted. `PageLayout`'s footer self-guards via `file_exists(modules/$name/copyright.php)`, so removal is a no-op (drops the legacy "Copyright" popup link that only Topics still surfaced).
**Location:** `modules/Topics/copyright.php` (deleted)
**Problem:** Verbatim PHP-Nuke 2007 boilerplate. Uses banned `<font>`, `<b>`, `<center>` tags + inline CSS. Not referenced anywhere.
**Suggested direction:** Delete.
**Est. effort:** S
**Risk if untouched:** Adding `modules/` to PHPStan scan path immediately fails CI.

---


## Axis 3: Top-Level Legacy PHP Files

### 3.2 `DEMO_LOGIN_TOKEN` Hardcoded to `'demo'`
**Location:** `ibl5/config.php:3`
**Problem:** `define('DEMO_LOGIN_TOKEN', 'demo')` — anyone reading the source can demo-login via `/ibl5/demo-login.php?token=demo`. `hash_equals` is constant-time but the secret is trivial.
**Suggested direction:** Random 32-char secret via env var.
**Est. effort:** S
**Risk if untouched:** Public URL grants authenticated read-only "Warriors GM" session.
**Status (2026-05):** Hardened — `demo-login.php` now fails closed via `Auth\DemoLoginGate`. The weak `'demo'` literal and any empty token are rejected with HTTP 403 regardless of a stale `config.php`; demo login is disabled unless a non-weak token is configured via the `DEMO_LOGIN_TOKEN` env var. See ADR-0034.

### 3.3 `config.php` display_errors Logic Always Disables Errors
**Location:** `ibl5/config.php:10`
**Problem:** `if ($_SERVER['SERVER_NAME'] != "localhost" OR $_SERVER['SERVER_NAME'] != '127.0.0.1')` is always true. `$display_errors = false` runs everywhere, hiding PHP notices in dev.
**Suggested direction:** Change `OR` to `AND` (or `!in_array(...)`).
**Est. effort:** S
**Risk if untouched:** No error output on localhost; debugging painful; regressions hidden.
**Status:** Completed (#1008) — `config.php` now `$display_errors = true` (verified 2026-06-20).

### 3.4 `configOlympics.php` Is Dead-Code Credential File
**Location:** ibl5/configOlympics.php
**Problem:** Identical production credentials, never `require`d. Olympics now uses the same DB via `LeagueContext`. Only reference is a `ibl5/bin/e2e-local.sh` guard comment.
**Suggested direction:** Delete; update `ibl5/bin/e2e-local.sh`; add to `.gitignore` as safety.
**Est. effort:** S
**Risk if untouched:** Dead credential file confuses; risks recommit.
**Status:** Completed (verified 2026-05-29 audit) — `configOlympics.php` deleted from disk.

### 3.5 `mainfile.php` and `LegacyFunctions.php` Define the Same Functions — Already Diverged
**Location:** `ibl5/mainfile.php:374-666` and `ibl5/classes/Bootstrap/LegacyFunctions.php`
**Problem:** Both define `is_admin()`, `blocks()`, `cookiedecode()`, `check_words()`, etc. `LegacyFunctions::blocks()` escapes `$currentlang` via `real_escape_string`; `mainfile::blocks()` interpolates raw.
**Suggested direction:** Remove duplicates from `mainfile.php`; `require_once 'LegacyFunctions.php'`.
**Est. effort:** M
**Risk if untouched:** Latent SQL injection in production `blocks()` if any request path supplies untrusted `$currentlang`.
**Status:** Completed (verified 2026-05-29 audit) — `mainfile.php` reduced to ~65 LOC; defines no functions, `require_once`s `LegacyFunctions.php` (single source). Duplicate definitions gone. See ADR-0030 / [[14.3]].

### 3.6 `$_REQUEST → $GLOBALS` Injection — Incomplete Denylist
**Location:** `ibl5/mainfile.php:165-193`
**Problem:** Copies `$_REQUEST` keys into `$GLOBALS` with a denylist that omits `$prefix`, `$AllowableHTML`, `$CensorList`, `$CensorReplace`, `$sitekey`, `$tipath`, `$commercial_license`. Crafted `?AllowableHTML=` could override the HTML allowlist before `filter()`.
**Suggested direction:** Extend denylist OR replace with whitelist-only (`newlang`, `redirect`).
**Est. effort:** M
**Risk if untouched:** Overriding `$AllowableHTML` enables stored XSS through `filter()` consumers.
**Status:** Completed (verified 2026-05-29 audit) — wholesale `$_REQUEST`→`$GLOBALS` block removed from `mainfile.php`; `ConfigBootstrap` now uses a 2-key allowlist (`newlang`, `redirect`). See [[14.12]].

### 3.7 `block.php` Violates `BanInlineCssRule` and `BanNumberFormatRule`
**Location:** `ibl5/block.php:138-224, 283`
**Problem:** Large inline `<style>` block + `number_format($offer['perceivedValue'], 2)`. Both are enforced-banned, but `block.php` may sit outside PHPStan's scan path.
**Suggested direction:** Move CSS to `design/components/block-fa-admin.css`; use `StatsFormatter::formatWithDecimals()`; verify PHPStan scans `block.php`.
**Est. effort:** S
**Risk if untouched:** Inline CSS grows unchecked; false confidence in rule enforcement.
**Status:** Completed (#1008) — no inline `<style>`/`number_format` remain in `block.php` (verified 2026-06-20).

### 3.8 `mainfile.php`, `modules.php`, `index.php` Missing `declare(strict_types=1)`
**Location:** `ibl5/mainfile.php:1`, `ibl5/modules.php:1`, `ibl5/index.php:1`
**Problem:** Three most-included files lack strict_types. PHPStan rule `RequireStrictTypesRule` likely excludes top-level files.
**Suggested direction:** Add the declaration; ensure PHPStan scans `ibl5/*.php` root files.
**Est. effort:** M
**Risk if untouched:** Loose type coercion throughout bootstrap; native-type DB bugs go undetected.
**Status:** Completed (#1008) — `declare(strict_types=1)` present on mainfile.php/modules.php/index.php (verified 2026-06-20).

### 3.10 Legacy gzip Output Buffering — Dead IE-Era Code
**Location:** `ibl5/mainfile.php:66-82`
**Problem:** Gzip logic checks `HTTP_USER_AGENT` for `'compatible'`/`MSIE`. Modern servers handle gzip at proxy level. Conflicts with `modules.php` `ob_start()` for page cache.
**Suggested direction:** Remove; confirm server-level compression.
**Est. effort:** S
**Risk if untouched:** `ob_gzhandler` + `PageCache` `ob_start` can cache gzip content and serve to non-gzip clients as plain text.
**Status:** Completed (verified 2026-05-29 audit) — no `ob_gzhandler` / `HTTP_USER_AGENT` MSIE check remains in `mainfile.php`.

### 3.11 `config.php` and `config.php.example` Drift on Keys
**Location:** `ibl5/config.php.example`, `ibl5/config.php:69,132`
**Problem:** `config.php` defines `$admin_file` and `IBL6_BASE_URL`; example file omits both. `DEMO_LOGIN_TOKEN` also missing from example.
**Suggested direction:** Add all keys with placeholders/comments.
**Est. effort:** S
**Risk if untouched:** New-developer setup fails when code references undefined constants.
**Status:** Completed (#1008) — `config.php.example` now carries `admin_file`/`IBL6_BASE_URL`/`DEMO_LOGIN_TOKEN` (verified 2026-06-20).

### 3.12 `index.php` Duplicates `modules.php` Routing
**Location:** `ibl5/index.php`
**Problem:** Hardcodes `$name = 'News'`, sets `$_SERVER['PHP_SELF'] = 'modules.php'`, then manually resolves theme/module paths. Duplicates `modules.php` dispatch logic. `Home_File` constant unused elsewhere.
**Suggested direction:** Replace with `require_once 'modules.php'` after setting `$name`; remove dup.
**Est. effort:** S
**Risk if untouched:** Any module-routing change must be mirrored in two files.
**Status:** Completed (verified 2026-05-29 audit) — `index.php` is now a thin shim: sets `$name='News'` then delegates to `mainfile.php`; dispatch lives in one place.

### 3.13 `Bootstrap\Application` Exists But Is Wired Nowhere
**Location:** `ibl5/classes/Bootstrap/Application.php` (+ all step classes)
**Problem:** Full OOP bootstrap pipeline with DI container and tests, but `mainfile.php` still uses procedural inline code. Classes docstrings say "Extracted from mainfile.php lines 87-103" — stalled migration.
**Suggested direction:** Complete the migration OR mark classes `@internal not-yet-wired` with an ADR note.
**Est. effort:** L (complete) / S (mark)
**Risk if untouched:** Developers updating bootstrap miss the parallel classes; classes accumulate drift.
**Status:** Completed (2026-05-17) — Bootstrap\Application is composition root for web/api/test. ADR-0030.

### 3.14 `leagueControlPanel.php` Uses `$_SERVER['DOCUMENT_ROOT']`
**Location:** `ibl5/leagueControlPanel.php:5`
**Problem:** Only top-level file using `DOCUMENT_ROOT` instead of `__DIR__`. Breaks in Docker worktrees.
**Suggested direction:** Change to `require __DIR__ . '/mainfile.php'`.
**Est. effort:** S
**Risk if untouched:** Mysterious auth failures in worktree dev.
**Status:** Implemented (2026-06-28) — replaced `$_SERVER['DOCUMENT_ROOT']`-relative require with `__DIR__`-relative; fixes worktree auth bootstrap.

### 3.15 `test-state.php` String-Interpolated SQL
**Location:** `ibl5/test-state.php:196-201, 246-248`
**Problem:** Two DELETE/UPDATE actions interpolate `$year`/`$count` directly. Currently safe due to int casts, but violates the prepared-statement pattern.
**Suggested direction:** Replace with `$db->prepare()` + `bind_param()`; or add a code comment citing the int-cast safety.
**Est. effort:** S
**Risk if untouched:** If int casts are removed by a refactor, becomes a real injection vector.
**Status:** Completed (#1008) — `test-state.php` DELETE/UPDATE actions use `prepare()`+`bind_param` (verified 2026-06-20).

### 3.16 `filter()` Uses `addslashes()` — Double-Escapes in Prepared-Statement Era
**Location:** `ibl5/mainfile.php:619`, `ibl5/classes/Bootstrap/LegacyFunctions.php:302`
**Problem:** `filter(..., ..., $save=1)` calls `addslashes()`. With `mysqli` prepared statements everywhere, this double-escapes inserts.
**Suggested direction:** Audit all `filter(..., ..., 1)` callers; remove the branch; add PHPStan rule banning the `save=1` form.
**Est. effort:** M
**Risk if untouched:** Any path using `filter($x, "", 1)` before prepared insert stores `\\'`-escaped content.
**Status:** Completed (verified 2026-05-29 audit) — `mainfile.php` no longer contains `filter()`/`addslashes`; the `save=1` branch is gone from the active path. (`LegacyFunctions.php:320` `addslashes` is JS-string escaping in `loginbox()`, not the SQL `save=1` path.)

### 3.17 `modules.php` Loose Equality on Module Name
**Location:** `ibl5/modules.php:20`
**Problem:** `if (isset($name) && $name == $_REQUEST['name'])` uses `==` and `$name` may already be a user-controlled global injection.
**Suggested direction:** Read only from `$_GET['name']` with string narrowing and `===`.
**Est. effort:** S
**Risk if untouched:** Edge cases pass when they shouldn't (mostly fixed in PHP 8, but the pattern is wrong).
**Status:** Completed (verified 2026-05-29 audit) — `modules.php` reads `$_GET['name']`, validates via `preg_match('/^[a-zA-Z0-9_]+$/', ...)` + `ModuleRegistry::isValid()`; no loose `==`.

---


## Axis 4: Naming Clarity and Disambiguation

### 4.1 `Trading` vs `Trade` Prefix Within the Same Module
**Location:** `ibl5/classes/Trading/`
**Problem:** Split between `Trade*` (entities/ops) and `Trading*` (feature-level) with no documented rule. New devs check existing classes to guess.
**Suggested direction:** Reserve `Trading*` for module-level entry points (Service, View); `Trade*` for domain objects; document.
**Est. effort:** S
**Risk if untouched:** Wrong prefix on new classes; grep returns partial results.
**Status:** Documented (2026-06-22) — the `Trading*`/`Trade*` rule has a canonical home at `ibl5/classes/Trading/README.md` (inventory + rationale), advisory-enforced by `TradingPrefixConventionRule` (`ibl5/phpstan-rules/TradingPrefixConventionRule.php`), and is cross-linked from `ibl5/docs/ARCHITECTURE_PATTERNS.md`. Optional rename sweep explicitly out of scope.

### 4.2 `CashConsiderationRepository` vs `TradeCashRepository`
**Location:** `ibl5/classes/Trading/BuyoutLedgerRepository.php`, `TradeCashRepository.php`
**Problem:** Both handle trade-context cash. Boundary is a table name, not a concept name.
**Suggested direction:** Rename to `BuyoutLedgerRepository` and `TradeCashRepository` (kept).
**Est. effort:** M
**Risk if untouched:** New cash queries land in the wrong repo; domains drift into each other.
**Status:** Completed (2026-05-20) — Renamed `CashConsiderationRepository` → `BuyoutLedgerRepository`.

### 4.3 `Services/` Module Is a Dumping Ground
**Location:** deleted (2026-05-16)
**Problem:** `CommonMysqliRepository`, `CommonValidator`, `CommonContractValidator`, `NewsService`, `PlayerDataConverter`, `QueryConditions`, `ValidationResult` — no shared trait except "we couldn't decide where to put it."
**Suggested direction:** Split: cross-cutting → `Shared/`; `NewsService` → `Topics/News/`; `PlayerDataConverter` → `Player/`; deprecate `Services/`.
**Est. effort:** L
**Risk if untouched:** Every new utility class lands here, accelerating sprawl.
**Status:** Completed (2026-05-16) — Services/ deleted entirely; all classes relocated to domain or purpose-named homes.

### 4.4 `Shared/SharedRepository` vs `Services/CommonMysqliRepository`
**Location:** Both extend `BaseMysqliRepository`
**Problem:** Two cross-module repositories with no naming distinction. `SharedRepository` has draft-pick/extension-reset; `CommonMysqliRepository` has user/team/player lookups.
**Suggested direction:** Merge or rename one to `CrossModuleRepository` with explicit scope.
**Est. effort:** M
**Risk if untouched:** Duplicate lookups written in each.
**Status:** Completed (2026-05-16) — Shared/SharedRepository deleted; methods relocated to Draft/Updater.

### 4.7 ~~`FreeAgencyNegotiationView`~~ → `FreeAgencyOfferView` ✅ Done
**Status:** Renamed to `FreeAgencyOfferView` (2026-05-17).
**Location:** `ibl5/classes/FreeAgency/FreeAgencyOfferView.php`

### 4.8 `FreeAgencyDemandCalculator` vs `NegotiationDemandCalculator`
**Location:** Both modules' `*DemandCalculator`
**Problem:** Both compute "player demand" — FA market vs Bird-rights extension. Names give no hint.
**Suggested direction:** `FreeAgencyMarketDemandCalculator` and `ExtensionContractDemandCalculator`.
**Est. effort:** S
**Risk if untouched:** Demand logic copy-pasted between types; agents open the wrong class.
**Status:** Implemented (2026-06-28) — renamed `FreeAgencyDemandCalculator`→`FreeAgencyMarketDemandCalculator` and `NegotiationDemandCalculator`→`ExtensionContractDemandCalculator` (concrete classes + interfaces + tests + PHPStan baseline regenerated).

### 4.9 `*ApiHandler` (Module-Local HTMX) vs `Api/Controller/*Controller` (REST)
**Location:** 8 `*ApiHandler` classes in feature modules vs `Api/Controller/` subtree
**Problem:** Two parallel HTTP-endpoint conventions with no documented distinction.
**Suggested direction:** Document or rename `*ApiHandler` → `*HtmxHandler`/`*PartialHandler`.
**Est. effort:** S (doc) / M (rename)
**Risk if untouched:** New HTMX endpoints land in `Api/Controller/`; new REST endpoints land as module-local handlers.
**Status:** Documented (2026-06-22) — distinction now in `ibl5/docs/ARCHITECTURE_PATTERNS.md` § Naming Conventions (`*ApiHandler` = module-local HTMX partial handler instantiated in `ibl5/modules/*/index.php`; `Api\Controller\*Controller` = routed REST endpoint in `ibl5/classes/Api/Router.php`). Cheap doc path taken; rename out of scope.

### 4.11 `TradeProcessor` vs `TradeQueueProcessor`
**Location:** `ibl5/classes/Trading/`
**Problem:** "Queue" is the only signal that the second is batch. Sounds like a wrapper around the first.
**Suggested direction:** Rename `TradeQueueProcessor` → `NightlyTradeBatchRunner`.
**Est. effort:** S
**Risk if untouched:** Batch logic bleeds into single-trade path or vice versa.
**Status:** Implemented (2026-06-28) — renamed `TradeQueueProcessor`→`NightlyTradeBatchRunner` (no production caller; test renamed alongside).

### 4.12 `ibl_plr.car_to` vs `stats_tvr` — Turnover Intra-Table Inconsistency
**Status:** Completed (2026-05-20) — migration 128 renamed `car_to` → `car_tvr` on `ibl_plr`, `ibl_plr_snapshots`, `ibl_olympics_plr`. `BanInconsistentColumnNamesRule` extended.

### 4.13 `car_tgm`/`car_tga` vs `stats_3gm`/`stats_3ga` — 3-Point Naming Split
**Status:** Completed (2026-05-20) — migration 128 renamed `car_tgm` → `car_3gm`, `car_tga` → `car_3ga` on the same 3 tables. View `vw_player_career_stats` recreated with new names.

### 4.14 Undocumented Two-Letter Rating Columns (`oo`, `od`, `dd`, `po`, `pd`, `td`)
**Location:** `ibl_plr` schema
**Problem:** Map to Outside Offense, Outside Defense, Drive Defense, etc. No column comment; only `PlayerData.php` translates them. Siblings `r_drive_off` etc. already use the expanded `r_*` convention.
**Suggested direction:** Add schema comments / ADR glossary. Longer-term: rename to `r_*`.
**Est. effort:** S (doc) / L (rename)
**Risk if untouched:** Agents waste time tracing; visual inconsistency on every `SELECT *`.
**Status:** Completed (merged #1039, maintenance-29) — documented opaque DB columns (column-naming docs).

### 4.15 `cy` and `cyt` — Opaque Contract-Year Abbreviations
**Location:** `ibl_plr` schema; `Player/PlayerData.php`
**Problem:** ADR-0010 Tier 4 excluded these. `CLAUDE.md` footnote needed to know `cy=2` → read `cy2`.
**Suggested direction:** Rename to `contract_current_year` / `contract_total_years`.
**Est. effort:** M
**Risk if untouched:** Tribal knowledge; footnote-based comprehension.
**Status:** Completed (merged #1039, maintenance-29) — documented opaque DB columns (column-naming docs).

### 4.16 `bird` and `exp` — Single-Word Columns With Domain Meanings
**Location:** `ibl_plr` schema
**Problem:** `bird` = Bird rights years; `exp` = years of experience. `exp` easily confused with "expiring contract."
**Suggested direction:** Rename to `bird_rights_years`, `years_experience`; or at minimum schema comments.
**Est. effort:** M (rename) / S (comments)
**Risk if untouched:** Queries filtering "expiring players" accidentally filter on experience.
**Status:** Completed (merged #1039, maintenance-29) — documented opaque DB columns (column-naming docs).

### 4.17 Stat Prefix Groups `sh_`/`sp_`/`ch_`/`cp_`/`s_dd`/`c_dd` Undocumented
**Location:** `ibl_plr` schema; `Player/PlayerStats.php`
**Problem:** Six prefix groups (season highs, season playoff highs, career season highs, career playoff highs, season DD/TD, career DD/TD). Convention not documented.
**Suggested direction:** Add schema comment block; reference in `PlayerStats.php` docblock.
**Est. effort:** S
**Risk if untouched:** Wrong reading (`ch_` as "championship" instead of "career high"); new columns ignore the pattern.
**Status:** Completed (merged #1039, maintenance-29) — documented opaque DB columns (column-naming docs).

### 4.18 `game_2gm`/`game_2ga` — `2g` Naming Convention Is Unusual
**Location:** `ibl_box_scores` schemas
**Problem:** Conventional basketball stats use `fg`. Anyone reading the POINTS formula must know `2gm` is specifically two-pointers.
**Suggested direction:** ADR-0010 deferred to Tier 6; add schema column comments now.
**Est. effort:** L (Tier 6)
**Risk if untouched:** New SQL writes `game_fgm` (doesn't exist); silent wrong totals.
**Status:** Already implemented — migration 121 (`121_snake_case_boxscore_and_schedule_columns.sql`) added `COMMENT 'Two-point field goals made/attempted'` to `game_2gm`/`game_2ga` on `ibl_box_scores` AND `ibl_box_scores_teams` (live DB confirmed 2026-06-28). The #1039-sweep marker was missing but DB comments predate this audit. Out of scope: `ibl_box_scores_engine_shadow*` (intentionally minimal mirror tables) and `ibl_olympics_box_scores*` (comments say "Field goals") — not the finding's named target; no migration authored.

### 4.19 `dc_of`, `dc_df`, `dc_oi`, `dc_di`, `dc_bh` Undocumented Depth-Chart Codes
**Location:** `ibl_plr`, `ibl_saved_depth_chart_players`, `JsbParser/JsbImportRepository.php`
**Problem:** Likely offensive/defensive focus + intensity + ball handling. No documentation.
**Suggested direction:** Add to `JSB_FILE_FORMATS.md`; schema comments.
**Est. effort:** S
**Risk if untouched:** Magic numbers with no semantic check.
**Status:** Completed (merged #1039, maintenance-29) — documented opaque DB columns (column-naming docs).

### 4.22 ~~`NegotiationViewHelper`~~ → `NegotiationOfferView` ✅ Done
**Location:** `ibl5/classes/Negotiation/`
**Resolved:** Renamed to `NegotiationOfferView` / `NegotiationOfferViewInterface`.

### 4.23 ~~`VotingResultsTableRenderer`~~ → `VotingResultsView` ✅ Done
**Location:** `ibl5/classes/Voting/`
**Resolved:** Renamed to `VotingResultsView` / `VotingResultsViewInterface`.

### 4.24 `InjuriesService` Has No `InjuriesRepository`
**Location:** `ibl5/classes/Injuries/InjuriesService.php`
**Problem:** Holds raw `\mysqli $db`; delegates to `League::getInjuredPlayersResult()`. Breaks the Repository/Service/View pattern.
**Suggested direction:** Extract `InjuriesRepository`; inject.
**Est. effort:** S
**Risk if untouched:** New injury DB logic lands as raw SQL in the Service.
**Status:** Completed — InjuriesRepository already extracted and injected (PR #970, 2026-06-03); backlog was stale.

### 4.25 `Services/NewsService` Misplaced
**Location:** `ibl5/classes/Topics/News/NewsRepository.php` (relocated and renamed)
**Problem:** Creates news stories, manages topic IDs — functionally part of `Topics/`.
**Suggested direction:** Move to `Topics/NewsStoryService.php` or extract a `News/` module.
**Est. effort:** S
**Risk if untouched:** `Services/` continues as catch-all.
**Status:** Completed (2026-05-16) — moved to `Topics\News\NewsService`. Renamed to `Topics\News\NewsRepository` on 2026-05-19 per ADR-0001 (Service classes may not extend `BaseMysqliRepository`).

### 4.27 `Updater/Steps/PreseasonCleanupRepository.php` Misplaced
**Location:** `ibl5/classes/Updater/PreseasonCleanupRepository.php` (moved from `Updater/Steps/` — this PR)
**Problem:** Repository nested in `Steps/` subdir; namespace `Updater\Steps`. All other Repositories live at module root.
**Suggested direction:** Move to `Updater/` root with namespace `Updater`.
**Est. effort:** S
**Risk if untouched:** Agents grep `Updater/` root and miss it.
**Status:** ✅ Implemented (2026-07-02) — moved to Updater\ root (this PR).

---


## Axis 5: Type-Safety Debt Concentration

### 5.1 Systemic: 286 of 293 `class.notFound` Errors Are One Mock-Alias Mismatch
**Location:** ~40 test files using `\MockDatabase` (global) instead of `Tests\WideUnit\Mocks\MockDatabase`
**Problem:** PHPUnit `bootstrap.php` aliases at runtime, but PHPStan resolves before the alias → 286 baseline entries hiding real type checks.
**Suggested direction:** Global find-replace `\MockDatabase` → namespaced; add `class_alias` to PHPStan stubs.
**Est. effort:** M
**Risk if untouched:** PHPStan can't type-check mock interactions in ~40 test files; mock-signature drift goes silent.
**Status:** Completed (2026-05-16) — baseline `class.notFound` reduced from 293 → 7

### 5.2 Trading Module — 91 `staticMethod.dynamicCall` Masking Stub Coverage
**Location:** `ibl5/tests/Trading/` (13 test files, 228 errors total)
**Problem:** `$this->createStub(...)` is non-static call to static PHPUnit method. 95 codebase-wide; 91 in Trading.
**Suggested direction:** Per-rule baseline suppression OR swap to `createMock()`.
**Est. effort:** S
**Risk if untouched:** Noise masks real Trading validator/processor issues.
**Status:** Completed (verified 2026-05-29 audit) — Trading `staticMethod.dynamicCall` now 0 (was 91), resolved by the 5.1 MockDatabase-alias fix. Rule-wide total grew to ~218 elsewhere; if still a concern, re-scope as a new finding.

### 5.3 UpdateAllTheThings Tests — 151 Errors Hiding Missing Interface Coverage
**Location:** `ibl5/tests/UpdateAllTheThings/` (259 errors)
**Problem:** 76 `class.notFound` + 75 `method.notFound` on stubs (`::method()` fluent API on unresolved interfaces). Mocks not verified against real contracts.
**Suggested direction:** Fix mock alias first; then trace `method.notFound` to interface stubs.
**Est. effort:** M
**Risk if untouched:** Nightly update pipeline mocks methods that don't exist on production interface.
**Status:** Completed (verified 2026-05-29 audit) — the 151 `class.notFound`+`method.notFound` errors are gone (5.1 alias fix). ~51 UAT baseline entries remain but are different error types; re-scope if pursuing.

### 5.4 `MockDatabase` Untyped Arrays — 46 Cascade Errors
**Location:** `ibl5/tests/WideUnit/Mocks/MockDatabase.php`, `MockDatabaseResult.php`
**Problem:** Bare `array` everywhere on the core mock used by ~40 tests; cascades into downstream `argument.type`.
**Suggested direction:** Add `array<K,V>` generics to properties + method PHPDocs.
**Est. effort:** S
**Risk if untouched:** Wrong fixture shapes pass mock; tests pass with malformed data.
**Status:** Completed (merged #1028, maintenance-33) — MockDatabase argument.type entries cleared (down to 0).

### 5.5 SeasonLeaderboardsView — 71 `ibl.unescapedOutput` (Highest XSS Surface)
**Location:** `ibl5/classes/SeasonLeaderboards/SeasonLeaderboardsView.php`
**Problem:** Largest single XSS-debt file in production. Player/team names in stat rows are live injection points.
**Suggested direction:** Wrap echoes in `HtmlSanitizer::e()`; cast numerics to `(int)/(float)` for zero-overhead path.
**Est. effort:** M
**Risk if untouched:** Stored XSS via player/team text fields.
**Status:** Completed (2026-05-17) — 71 entries cleared; file in zero-floor.

### ~~5.6 CareerLeaderboardsView — 44 `ibl.unescapedOutput`~~ ✅ RESOLVED
**Resolved:** 2026-05-17 via `xss-c-career-leaderboards-remainder`. All 44 entries cleared; file added to zero-floor.

### ~~5.7 Navigation Views — 58 `ibl.unescapedOutput` Across 5 Files~~ ✅ RESOLVED
**Resolved:** 2026-05-17 via PR `xss-navigation-views-zero-floor`. All 58 violations fixed; files added to zero-floor ratchet (ADR-0031) preventing regression.

### ~~5.8 YourAccountView — 27 `ibl.unescapedOutput`~~ ✅ RESOLVED
**Resolved:** 2026-05-17 via `xss-c-career-leaderboards-remainder`. All 27 entries cleared; file added to zero-floor.

### 5.9 Bootstrap Layer — 7 `ibl.rawSuperglobal` in Non-Controller Classes
**Location:** `Bootstrap/ConfigBootstrap.php` ($_REQUEST), `Bootstrap/LeagueBootstrap.php` ($_GET×3 + $_COOKIE×2), `League/LeagueContext.php` ($_GET + $_COOKIE), `Api/Middleware/ApiKeyAuthenticator.php` ($_GET)
**Problem:** Bootstrap and `LeagueContext` reach into superglobals inline — untestable. `ApiKeyAuthenticator` reads raw `$_GET['key']` for auth.
**Suggested direction:** Extract PSR-7-style `ServerRequest` injected at bootstrap; bootstrap reads superglobals once.
**Est. effort:** L
**Risk if untouched:** `ApiKeyAuthenticator` raw GET access — harder to add input validation; league selection untestable.
**Status:** Gated (2026-05-17) — BanRawSuperglobalsRule expanded to all superglobals with per-variable allowlists; BanGlobalKeywordRule added. Bootstrap files remain on allowlist by design. ADR-0032.

### 5.10 229 `missingType.return` on Test Methods
**Location:** Top: Player (50), Waivers (41), Negotiation (32), Draft (31), Extension (19), DepthChartEntry (14)
**Problem:** Test methods missing return type. Data providers without typed returns allow wrong-shape arrays.
**Suggested direction:** Codemod: add `: void` to all `public function test*()`; manual review for helpers/providers.
**Est. effort:** M
**Risk if untouched:** Data providers ship wrong shapes silently.
**Status:** Completed (verified 2026-06-09 audit) — missingType.return is 0 in phpstan-tests-baseline.neon and produces no live analyse:tests errors; test-method return types added in #939, baseline cleared in #958.

### 5.11 WideUnit Tests — 105 `phpunit.assertEquals` (Should Be `assertSame`)
**Location:** WideUnit (105), BasketballStats (84), Statistics (84), Updater (59), CareerLeaderboards (48), FreeAgency (46), Services (46), OneOnOneGame (31)
**Problem:** `assertEquals` uses loose `==`. With native DB types, `assertEquals(1, $row['tid'])` masks type mismatches `assertSame` would catch.
**Suggested direction:** Global sed `assertEquals` → `assertSame`.
**Est. effort:** S
**Risk if untouched:** String/int type confusion in DB row assertions slips through.
**Status:** Completed (verified 2026-06-09 audit) — phpunit.assertEquals is 0 in phpstan-tests-baseline.neon; phpstan-phpunit's AssertEqualsIsDiscouragedRule is active and reports no live errors. Converted in #940 (identical pass count); remaining assertEquals() calls are object/array comparisons the rule does not flag. Baseline cleared in #958.

### 5.12 JsbParser Tests — Interface Contract Not Statically Enforced
**Location:** `ibl5/tests/JsbParser/` (93 errors)
**Problem:** 25 `method.notFound` on `JsbImportRepositoryInterface::method()` (PHPUnit fluent API on unresolved interface).
**Suggested direction:** Resolve via mock-alias fix; then real interface method names enforce correctness.
**Est. effort:** M
**Risk if untouched:** Parser interface drift goes silent.
**Status:** Completed (verified 2026-05-29 audit) — 25 `method.notFound` cleared by 5.1 alias fix; ~16 JsbParser baseline entries remain (other identifiers).

### 5.13 Waivers Tests — Stubs Stub a Concrete Class, Not an Interface
**Location:** `ibl5/tests/Waivers/` (133 errors)
**Problem:** 27 `method.notFound` on `Services\CommonMysqliRepository::method()` — tests stub the concrete class.
**Suggested direction:** Extract `CommonMysqliRepositoryInterface`; have Waivers depend on it.
**Est. effort:** M
**Risk if untouched:** Waivers is business-critical; mock signatures pass even after class refactors.
**Status:** Completed (verified 2026-05-29 audit) — `CommonMysqliRepositoryInterface` extraction (7.1, 2026-05-16) cleared the 27 `method.notFound`; Waivers now stubs the interface.

### 5.14 36 `constructor.missingParentCall` — Anonymous mysqli Subclasses
**Location:** Inline `new class extends mysqli` in tests (Trading 9, DepthChartEntry 8, Draft 8, FreeAgency 2)
**Problem:** Anonymous classes override `prepare()`/`query()` without `parent::__construct()`. PHPStan treats them as broken subtypes.
**Suggested direction:** Replace with shared `MockDatabase`.
**Est. effort:** M
**Risk if untouched:** Degraded static analysis on 40+ test files.
**Status:** Completed (verified 2026-05-29 audit) — `constructor.missingParentCall` now 0 in baselines (was 36).

### 5.16 Trading-Card & PageLayout Inline CSS — 6 `ibl.inlineCss` Baselines
**Location:** `Player/Views/PlayerStatsCardView.php` (1), `PlayerTradingCardBackView.php` (1), `PlayerTradingCardFrontView.php` (1), `DepthChartEntry/DepthChartEntryView.php` (2), `PageLayout/PageLayout.php` (2), `Trading/TradingView.php` (1)
**Problem:** `PageLayout` inline `<style>` is on every page; trading cards are a deliberate component not yet migrated.
**Suggested direction:** Fix `PageLayout` first (widest); migrate trading cards together.
**Est. effort:** S per file
**Risk if untouched:** Inline CSS overrides component styles invisibly.
**Status:** Completed (verified 2026-05-29 audit) — no `ibl.inlineCss` entries remain in `phpstan-baseline.neon`; PageLayout's 2 cases are now inline `@phpstan-ignore` (see [[11.1]]).

### 5.17 4 `ibl.cookieBeforeHeader` Suppressions — Possibly Real Auth Ordering Bugs
**Location:** `FreeAgencyController.php` (2 — possibly false-positive), `SeriesRecordsController.php` (1), `TeamController.php` (1), `WaiversController.php` (2)
**Problem:** Reads `$cookie` before `PageLayout::header()` populates auth/CSRF state.
**Suggested direction:** Investigate the three controllers; fix ordering or add explicit `@phpstan-ignore` with reason.
**Est. effort:** S
**Risk if untouched:** Reading stale/missing CSRF or session state — security-relevant.
**Status:** Completed (2026-05-17) — 4 baselines cleared, 4 files added to zero-floor. Rule namespace detection fixed. See [ADR-0032](decisions/0032-cookie-before-header-zero-floor.md).

### 5.18 ConfigBootstrap — 6 Baseline Entries Block Deprecated `Database\MySQL` Removal
**Location:** `ibl5/classes/Bootstrap/ConfigBootstrap.php`
**Problem:** Calls `Database\MySQL::sql_query()` (deprecated); reads `$_REQUEST`; generates `<b>`/`<center>` tags.
**Suggested direction:** Migrate config-read to prepared statements via `$mysqli_db`; fix HTML.
**Est. effort:** M
**Risk if untouched:** Blocks `Database\MySQL` deprecation removal.
**Status:** Completed (2026-06-25) — `Database\MySQL` deleted. The "no remaining app consumers" claim was incorrect; the actual consumers were `modules/News/index.php`, `modules/News/categories.php`, and `classes/Bootstrap/LegacyFunctions.php::blocks()`, all migrated to `$mysqli_db` prepared statements. `db/db.php` instantiation removed and its connection-health check reworked to probe `$mysqli_db->connect_errno`.

### 5.20 152 `missingType.iterableValue` — Untyped Arrays as Primary Test-Debt Vector
**Location:** WideUnit (46 — on `MockDatabase`), Trading (21), Team (11), RookieOption (9), SeriesRecords (9), Negotiation (4)
**Problem:** Bare `array` return types prevent shape verification.
**Suggested direction:** Fix `MockDatabase` first (46 of 152); then WideUnit fixture builders.
**Est. effort:** M
**Risk if untouched:** Fixture builders ship wrong shapes; failures appear at SUT runtime, not at fixture site.
**Status:** Completed (verified 2026-06-09 audit) — missingType.iterableValue is 0 in phpstan-tests-baseline.neon and produces no live errors; array-shape types added in #939, baseline cleared in #958.

### 5.21 `ibl.deprecatedHtmlTag` in Business-Logic Class
**Location:** `ConfigBootstrap.php` (`<b>`, `<center>`); `Trading/TradeOffer.php` (`<i>` ×2)
**Problem:** `TradeOffer` (business logic) generates HTML directly — violates Repository/Service/View.
**Suggested direction:** Extract `TradeOffer` HTML to a View class; replace `ConfigBootstrap` tags with `<strong>` + CSS.
**Est. effort:** S (ConfigBootstrap) / M (TradeOffer)
**Risk if untouched:** Presentation tangled with domain logic; untestable.
**Status:** Completed (verified 2026-05-29 audit) — no `ibl.deprecatedHtmlTag` entries remain in either baseline.

---


## Axis 6: Test Coverage Gaps

### 6.1 BulkImport — Zero Tests, 9 Files
**Location:** `ibl5/classes/BulkImport`
**Problem:** `BulkImportSummary`, `ArchiveExtractor`, `ImportEntry`, `JsbFileType`, `BackupArchiveLocator`, + 4 others. Bulk imports of JSB/PLR/stats files have no test coverage.
**Suggested direction:** PHPUnit for `BulkImportSummary` aggregation, `ArchiveExtractor` file handling, `ImportEntry` state transitions.
**Est. effort:** M
**Risk if untouched:** Bulk imports fail silently; error aggregation untested.
**Status:** ✅ Already covered (verified 2026-06-26) — all 7 concrete classes have substantive unit tests under tests/Unit/BulkImport/ (ArchiveExtractorTest 27, BulkImportSummaryTest 9, ImportEntryTest 3, plus BulkImportRunner/BackupArchiveLocator/FileTypeHandler/JsbFileType). The finding's "zero tests" premise is stale; no new tests added.

### 6.3 Module/ModuleAccessControl — Zero Tests
**Location:** `ibl5/classes/Module/ModuleAccessControl.php`
**Problem:** Role-based access gating with no tests.
**Suggested direction:** PHPUnit for authorization rules.
**Est. effort:** S
**Risk if untouched:** Unauthorized module access; feature gates unvalidated.
**Status:** Completed (verified 2026-05-29 audit) — `tests/Module/` now has `ModuleAccessControlTest.php` + `ModuleRegistryTest.php`.

### 6.5 Statistics/TeamStatsCalculator — Zero Tests
**Location:** `ibl5/classes/BasketballStats/TeamStatsCalculator.php` (moved from `classes/Statistics/` — see 2.30)
**Problem:** PPG, FG%, rebounds, assists aggregation with no tests.
**Suggested direction:** PHPUnit for aggregation, rounding, div-by-zero.
**Est. effort:** M
**Risk if untouched:** Stats mismatches vs player-level box scores.
**Status:** Completed (verified 2026-05-29 audit) — `tests/BasketballStats/TeamStatsCalculatorTest.php` now exists.

### 6.6 StrengthOfScheduleCalculator — Zero Tests
**Location:** `ibl5/classes/BasketballStats/StrengthOfScheduleCalculator.php` (moved from `classes/StrengthOfSchedule/` — see 2.30)
**Problem:** Opponent weighting + win-rate normalization with no tests.
**Suggested direction:** PHPUnit for weighting math.
**Est. effort:** M
**Risk if untouched:** SoS rankings mathematically wrong; playoff seeding affected.
**Status:** Completed (verified 2026-05-29 audit) — `tests/BasketballStats/StrengthOfScheduleCalculatorTest.php` now exists.

### 6.7 LeagueStarters — Thin (7 files, 2 tests, 0.29 ratio)
**Location:** `ibl5/classes/LeagueStarters`
**Problem:** Repository/Service/View/ApiHandler with thin coverage.
**Suggested direction:** Service all-star selection, Repository correctness, API response format.
**Est. effort:** M
**Risk if untouched:** All-Star eligibility/voting aggregation untested.
**Status:** Implemented (2026-06-27) — added LeagueStartersApiHandler handle() response-format + invalid-display-fallback tests and Service boundary tests (non-int teamid skip, per-team/position dedupe) in tests/LeagueStarters/. Repository SQL correctness remains owned by the gated tests/DatabaseIntegration/LeagueStartersRepositoryTest.php.

### 6.8 ApiKeys — Thin (6 files, 2 tests, 0.33 ratio)
**Location:** `ibl5/classes/ApiKeys`
**Problem:** Key generation, rotation, access control thinly tested.
**Suggested direction:** Service uniqueness, Repository revocation, rate-limiter integration.
**Est. effort:** M
**Risk if untouched:** Key collisions, revocation failures, rate-limit bypass.
**Status:** Implemented (verified 2026-06-27) — tests/ApiKeys/ApiKeysRepositoryTest.php added (findByUserId latest-key selection, createKey, revokeByUserId active-only scoping). Rate-limiter integration covered by tests/Api/Repository/RateLimitRepositoryTest.php (6.16).

### 6.9 ContractList — Thin (6 files, 2 tests)
**Location:** `ibl5/classes/ContractList`
**Problem:** CY salary, cap space, sorting thinly tested.
**Suggested direction:** Service CY salary mapping (cy1 vs cy2), cap aggregation, sort stability.
**Est. effort:** M
**Risk if untouched:** Wrong contract years displayed; cap off by year.
**Status:** Completed (verified 2026-06-20) — `tests/ContractList/ContractListServiceTest.php` extended with cap2-6/acap2-6 math, cy=6 boundary, cy=4 full-vector, cy=0 years 4-6, and mixed-cy accumulation coverage.

### 6.10 FreeAgencyPreview — Thin (6 files, 2 tests)
**Location:** `ibl5/classes/FreeAgencyPreview`
**Problem:** Off-season player availability prediction thinly tested.
**Suggested direction:** Eligibility filtering, contract-expiration, projection accuracy.
**Est. effort:** M
**Risk if untouched:** Preview misses eligible players or includes ineligible.
**Status:** Implemented (2026-06-27) — future-year projection restored via URL param (`feat: restore future-year free-agent preview via URL param`, PR #1162) and FreeAgencyPreviewService tests expanded with contract-end boundary cases (cy=6 final-year eligible, cy=5 with year-6 salary excluded) in tests/FreeAgencyPreview/. Repository correctness owned by gated tests/DatabaseIntegration/FreeAgencyPreviewRepositoryTest.php. Projection-accuracy edge coverage still open.

### 6.11 SeasonHighs — Thin (6 files, 2 tests)
**Location:** `ibl5/classes/SeasonHighs`
**Problem:** Season-best stat lines thinly tested.
**Suggested direction:** High-water filtering, repository correctness, stat-type formatting.
**Est. effort:** M
**Risk if untouched:** Wrong records on player profile pages.
**Status:** Implemented (2026-06-27) — added host-runnable SeasonHighsRepository transformation tests (normalizeRow int-casts/color defaults/optional keys, getSeasonHighsBatch bucketing/sort/tiebreak/empty-stats short-circuit) in tests/SeasonHighs/SeasonHighsRepositoryTest.php. Gated tests/DatabaseIntegration/SeasonHighsRepositoryTest.php still covers the SQL.

### 6.12 TeamSchedule — Thin (6 files, 2 tests)
**Location:** `ibl5/classes/TeamSchedule`
**Problem:** Regular + playoff schedule logic thinly tested.
**Suggested direction:** Playoff bracket construction, game-order, opponent lookup.
**Est. effort:** M
**Risk if untouched:** Seeding incorrect; game sequence out of order.
**Status:** Implemented (2026-06-27) — added TeamScheduleService opponent-lookup/opponent-text, SOS-tier (populated vs empty rankings), and next-sim-highlight tests in tests/TeamSchedule/. Repository correctness owned by gated tests/DatabaseIntegration/TeamScheduleRepositoryTest.php; no playoff-bracket unit exists on master (View-only month relabel).

### 6.15 Voting Module — Subthreshold (17 files, 4 tests)
**Location:** `ibl5/classes/Voting`
**Problem:** Ballot/submission/results services and renderer with 4 tests.
**Suggested direction:** Ballot validation, duplicate-vote prevention, ranking aggregation.
**Est. effort:** M
**Risk if untouched:** Award voting corruption; duplicates counted.
**Status:** Implemented (verified 2026-06-27) — tests/Voting/VotingRepositoryTest.php (vote aggregation, column-allowlist rejection, save/cooldown writes, name→pid mapping) + tests/Voting/SubmissionResultTest.php added. Results service/controller/view already covered under tests/VotingResults/.

### 6.20 Anonymous rookie-option lockdown E2E assertion is non-discriminating
**Location:** `tests/e2e/security/draft-rookie-anon-lockdown.spec.ts` (under `ibl5/`; added by PR #1107, not yet on `master`)
**Problem:** The unauthenticated `processrookieoption` lockdown test asserts the response `toContain('YourAccount')` — a string emitted by `loginbox()` / global nav chrome on *every* anonymous page. It therefore does not discriminate a working auth gate from a broken one: if the `is_user()` gate regressed, the success marker (`result=rookie_option_success`) appears only in the redirect *URL*, not the response body, so the test would still pass. (The sibling Draft lockdown test is independently saved by its `not.toMatch(/select\s*\*\*.*!\*\*/)` negative matcher; the rookie test has no such backstop.)
**Suggested direction:** Re-issue the anonymous POST with `maxRedirects: 0` and assert the redirect status + `Location` (login / `error=`), or assert absence of the rookie-option success side-effect — a marker present only when the gate is bypassed.
**Est. effort:** S
**Risk if untouched:** A future regression that drops the `is_user()` gate on `processrookieoption` ships green; the lockdown test gives false confidence.
**Status:** ✅ Implemented — surfaced in the PR #1107 review (low-confidence 75/100 reviewer note). Test-only, green-green (🟩). Strengthened the anon `processrookieoption` assertion to `maxRedirects:0` + `toBe(200)`, so a regressed `is_user()` gate now fails the test (a bypassed gate 302s instead of the 200 loginbox).


## Axis 7: Repository Contract Gaps / Shared Abstractions

### 7.1 `CommonMysqliRepository` Has No Interface
**Location:** `ibl5/classes/Repositories/Contracts/` (interfaces extracted here)
**Problem:** Concrete class with no `Contracts/CommonMysqliRepositoryInterface`. Every caller depends on the concrete class.
**Suggested direction:** Extract interface; split into `TeamIdentityRepositoryInterface`, `PlayerLookupRepositoryInterface`, `SalaryCapRepositoryInterface`.
**Est. effort:** M
**Risk if untouched:** Behavior changes can't be mocked; regressions only caught at integration time.
**Status:** Completed (2026-05-16) — interface extracted, all 34 sites inject via `CommonMysqliRepositoryInterface`.

### 7.2 `CommonMysqliRepository` Instantiated Directly at 40+ Sites
**Location:** Pattern across `classes/` and `modules/` — Trading, FreeAgency, DepthChartEntry, Player, etc.
**Problem:** `new Services\CommonMysqliRepository($db)` inline (including inside methods). No DI.
**Suggested direction:** Constructor-inject; one factory/DI helper for the shared instance.
**Est. effort:** M
**Risk if untouched:** Duplicate queries per request; can't add caching decorator.
**Status:** Completed (2026-05-16) — bare instantiation banned by `ibl.directCommonMysqliInstantiation` PHPStan rule.

### 7.3 `PlayerViewFactory` Reaches Into `$GLOBALS['mysqli_db']`
**Location:** `ibl5/classes/Player/Views/PlayerViewFactory.php` lines 68-69
**Problem:** Fallback when `commonRepository` not injected. Hidden global dependency PHPStan can't check.
**Suggested direction:** Remove fallback; make `CommonMysqliRepository` mandatory.
**Est. effort:** S
**Risk if untouched:** Silent break in CLI/test contexts.
**Status:** Completed (2026-05-16) — PlayerViewFactory fallback removed; constructor requires `CommonMysqliRepositoryInterface`.

### 7.4 `SeasonArchiveRepository::getPlayerIdsByNames` Bypasses `BaseMysqliRepository`
**Location:** `ibl5/classes/SeasonArchive/SeasonArchiveRepository.php` lines 251-279
**Problem:** Uses `$db->prepare()` + `$stmt->execute($array)` directly, bypassing logging/error codes.
**Suggested direction:** Refactor to `fetchAll($query, $types, ...$names)` pattern.
**Est. effort:** S
**Risk if untouched:** Silent error swallowing; inconsistent observability.
**Status:** Completed (verified 2026-05-29 audit) — `getPlayerIdsByNames` now calls `$this->fetchAllInList()` (see [[7.6]]); no raw prepare/execute.

### 7.5 `DatabaseCache` Bypasses `BaseMysqliRepository`
**Location:** `ibl5/classes/Cache/DatabaseCache.php`
**Problem:** Own `private \mysqli $db`; silently returns null on every failure with no logging.
**Suggested direction:** Extend `BaseMysqliRepository`; or at least add `LoggerFactory` on failure paths.
**Est. effort:** S
**Risk if untouched:** Cache poisoning silent; cache-read failures cause expensive cold queries undetected.
**Status:** Completed (merged #1089, maintenance-39b) — `DatabaseCache` now extends `BaseMysqliRepository`; failure paths log instead of silently returning null.

### 7.8 `NegotiationRepository` Instantiates `CommonMysqliRepository` for a Single Lookup
**Location:** `ibl5/classes/Negotiation/NegotiationRepository.php` lines 78-79
**Problem:** Already extends `BaseMysqliRepository`, but news a second repo for `getTeamCapSpaceNextSeason()`.
**Suggested direction:** Inline the salary view query or inject `CommonMysqliRepository`.
**Est. effort:** S
**Risk if untouched:** Hidden dependency; harder integration tests.
**Status:** Completed (2026-05-16) — resolved by the DI sweep; `NegotiationRepository` now receives `CommonMysqliRepositoryInterface` via constructor.

### 7.9 `CommonMysqliRepository` Mixes 3 Domains
**Location:** `ibl5/classes/Repositories/PlayerLookupRepository.php`, `ibl5/classes/Repositories/SalaryCapRepository.php`, `ibl5/classes/Repositories/TeamIdentityRepository.php`
**Problem:** Identity lookups + salary cap + player lookups all in one class. Callers needing only one get all three.
**Suggested direction:** Split: `TeamIdentityRepository`, `PlayerLookupRepository`, `SalaryCapRepository`.
**Est. effort:** M
**Risk if untouched:** Junk-drawer growth; salary drift with `CapSpace/`.
**Status:** Completed (2026-05-16) — split into TeamIdentity/PlayerLookup/SalaryCap repos.

### 7.10 `WaiversRepositoryInterface` Lacks `@phpstan-type` Shapes
**Location:** `ibl5/classes/Waivers/Contracts/WaiversRepositoryInterface.php`
**Problem:** Interface methods take `array $team`, `array $contractData` — no shape annotations.
**Suggested direction:** Add `@phpstan-type` shapes when next touched.
**Est. effort:** S
**Risk if untouched:** Mis-keyed arrays fail at runtime, not analysis time.
**Status:** Completed (merged #1032, maintenance-39) — promoted WaiversRepositoryInterface shapes.

### 7.12 `fetchAllRealTeams` `orderBy` Whitelist Not Enforced By Type
**Location:** `ibl5/classes/BaseMysqliRepository.php` lines 365-378
**Problem:** `$orderBy` is `string`; invalid values silently fall back to `team_name ASC`. PHPDoc doesn't list valid values.
**Suggested direction:** Extract `enum TeamOrderBy`; or `const ALLOWED_ORDER_BY` on a future `TeamInfoRepository`.
**Est. effort:** S
**Risk if untouched:** Valid-sounding strings (`'team_city DESC'`) silently fall back.
**Status:** Completed (merged #1032, maintenance-39) — TeamOrderBy enum + enumified fetchAllRealTeams (repository contract cleanup).

### 7.13 `DraftRepository` Constructs Two Repository Objects
**Location:** `ibl5/classes/Draft/DraftRepository.php` line 26
**Problem:** Extends `BaseMysqliRepository` AND stores `new Services\CommonMysqliRepository($db)`. Two repo objects from same connection; neither injectable.
**Suggested direction:** Constructor-inject `CommonMysqliRepository`.
**Est. effort:** S
**Risk if untouched:** Three+ instances during draft operations; team-lookup behavior can't be stubbed.
**Status:** Completed (verified 2026-05-29 audit) — `DraftRepository` constructor now injects `TeamIdentityRepositoryInterface`; no inline `new CommonMysqliRepository`.

### 7.14 `StandingsRepository` Contains Business Logic
**Status:** ✅ Implemented -- extracted the points-scored/allowed math into stateless `Standings\PythagoreanCalculator::calculate()`; SQL subquery builders left private in the repository.
**Location:** `ibl5/classes/Standings/StandingsRepository.php` lines 602-663
**Problem:** `calculatePythagoreanStats` returns a derived shape; subquery builders carry basketball semantics.
**Suggested direction:** Move calc to `StandingsService` or `StatsFormatter`; keep SQL builders private.
**Est. effort:** S
**Risk if untouched:** Duplicate Pythagorean math; testing requires full repository.

### 7.15 `PlayerRepository` Includes Mapping Logic
**Location:** `ibl5/classes/Player/PlayerRepository.php` lines 189-420
**Problem:** 8 private `map*` methods + `FIELD_MAP`/`EXCLUDED_FROM_FIELD_MAP` constants = mapping concerns mixed with data access (622 LOC).
**Suggested direction:** Extract `PlayerDataMapper` / `PlayerHydrator`.
**Est. effort:** M
**Risk if untouched:** Two responsibilities change together; both harder to test.
**Status:** Implemented — extracted `ibl5/classes/Player/PlayerDataMapper.php` (constants + 8 map* methods); `PlayerRepository` delegates. Green-green refactor.

### 7.16 `RecordHoldersRepository` Has Hidden In-Memory Caches
**Location:** `ibl5/classes/RecordHolders/RecordHoldersRepository.php` lines 45-49
**Problem:** `$regularSeasonGamesCache`, `$teamNameCache` private instance caches; no `invalidateCache()` on interface; no decorator.
**Suggested direction:** Move caches to Service; or document and add interface notes.
**Est. effort:** S
**Risk if untouched:** Reuse across long-lived processes returns stale data invisibly.
**Status:** Completed (merged #1040, maintenance-38) — dropped hidden caches in RecordHolders layering.


## Axis 8: Scripts Proliferation

### 8.1 Committed Database Dumps (~1.1GB)
**Location:** `ibl5/shellScripts/database_dump_*.sql` (7 files, Feb 19 – Mar 9)
**Problem:** `.gitignore` has `database_dump_*.sql` but dumps were committed earlier. Sizes up to 192MB each. `.env` with `REMOTE_PASSWORD=...` also committed.
**Suggested direction:** `git filter-repo` to remove; rotate credentials; verify `.gitignore` blocks future dumps.
**Est. effort:** M
**Risk if untouched:** Clones slow; shallow-clone fragility; credential exposure.
**Status:** Completed (verified 2026-06-20) — dump files absent from `git log --all --full-history`; `.git` pack down to 279M (not 1.1GB). Credential rotated + scrubbed from HEAD + gitleaks gate (ADR-0034); git-history rewrite was explicitly declined there (rotation is the remediation), so the literal remains in history by design.

### 8.2 Three Homes for Scripts With Unclear Separation — RESOLVED
**Resolution:** Convention documented in `bin/README.md` and `ibl5/bin/README.md`.
Repo/git/CI/worktree/prod-ops tooling → `bin/` (host only); scripts needing the
PHP app or run inside the Docker container → `ibl5/bin/` (pinned by the
`./ibl5`-only bind mount); PHP admin/data entry points → `ibl5/scripts/`.
Two dead scripts were removed in the same change: the Olympics schema-parity
CLI (superseded by `OlympicsSchemaParityTest.php`) and the franchise-seasons
one-time backfill (its tables now live in the baseline schema + migrations).

### 8.4 `shellScripts/` Has No Shell Scripts
**Location:** ibl5/shellScripts/
**Problem:** Only contains DB dumps + a `.env` file. No `.sh` files. Misleading name.
**Suggested direction:** Delete dir after removing dumps from history.
**Est. effort:** M
**Risk if untouched:** Confusion; credential exposure.
**Status:** Completed (verified 2026-06-20) — `shellScripts/` absent from working tree and git index.

### 8.6 `classes/Scripts/` vs `scripts/` Overlap
**Location:** Both directories
**Problem:** Two "Scripts" dirs; classes/Scripts holds business-logic; scripts/ holds web entry points.
**Suggested direction:** Rename `classes/Scripts/` → `classes/Maintenance/`; document the split.
**Est. effort:** M
**Risk if untouched:** New scripts placed in wrong folder.
**Status:** ✅ Implemented (2026-07-02) — classes/Scripts → classes/Maintenance (this PR).

### 8.7 Symlink Strategy Undocumented — RESOLVED
**Location:** `/bin/db-query` → `../ibl5/bin/db-query`
**Problem:** One-off symlink; no manifest or convention.
**Suggested direction:** bin/README.md documenting the pattern; optional `.symlinks` manifest.
**Est. effort:** S
**Risk if untouched:** Symlinks rot when scripts move.

### 8.8 Archive Directory Disorganized — RESOLVED
**Location:** `ibl5/scripts/archive/`
**Problem:** 2 legacy scripts; no README documenting why archived.
**Suggested direction:** Add `scripts/archive/README.md` with filename + deprecation date + reason + safe-to-delete flag.
**Est. effort:** S
**Risk if untouched:** Archive grows; future refactors re-reference old code.

### 8.12 Plaintext Credentials in `.env`
**Location:** ibl5/shellScripts/.env
**Problem:** Production SSH + MariaDB credentials in plaintext.
**Suggested direction:** Rotate; remove from history; use GitHub Secrets for CI; CLAUDE.md note "no credentials in git ever."
**Est. effort:** M
**Risk if untouched:** Credentials compromised on any leak; production DB at risk.
**Status:** Completed (verified 2026-06-20) — `shellScripts/.env` not present in working tree or git history; prod credential rotated + gitleaks scanning gate added (ADR-0034). The already-leaked literal is allowlisted in `.gitleaks.toml`, **not** purged from history (rewrite declined as too disruptive).

### 8.13 Web-Accessible Mutation Scripts Without Auth Audit
**Location:** `ibl5/classes/LeagueControlPanel/LeagueControlPanelView.php`
**Problem:** Links to `/ibl5/scripts/updateAllTheThings.php` and `tradition.php`. Need to verify session/role checks.
**Suggested direction:** Audit auth; move to authenticated POST endpoints.
**Est. effort:** M
**Risk if untouched:** Unauthorized script execution; accidental data corruption.
**Status:** Resolved (2026-06-09). Tradition half — unauthenticated `scripts/tradition.php` deleted; mutation now runs only via the `is_admin()`-guarded `update_tradition` POST action in the LCP. `updateAllTheThings.php` half — the unauthenticated-CSRF GET link was replaced by an `is_admin()`-guarded, CSRF-validated POST button in the LCP; the script is now POST-only (`is_admin()` 403 before the method check; `CsrfGuard::validateToken(..., 'lcp_update_all')` on the POST), so a cross-site GET can no longer fire the full-league mutation.

### 8.16 Check Scripts Have Inconsistent Output / Exit Codes
**Location:** `/bin/check-*` and `ibl5/bin/check-*`
**Problem:** No standardized "pass" output format.
**Suggested direction:** Define check-script standard in bin/README.md or bin/lib/check-helpers.sh.
**Est. effort:** S
**Risk if untouched:** CI jobs misinterpret check results.
**Status:** ✅ Implemented (2026-06-21) — documented the existing de-facto standard (exit 0 = pass, 1 = violation, 2 = usage/env error; stdout violations with UPPERCASE prefix tags, stderr diagnostics) in `bin/README.md`. A shared `check-helpers.sh` helper under `bin/lib/` is deferred as a separate item (a new `bin/` script would trip `adr-check`).


## Axis 9: Documentation Drift and Onboarding Cost

### 9.1 DATABASE_GUIDE — Stale Table Counts and Dropped References
**Location:** `ibl5/docs/DATABASE_GUIDE.md`
**Problem:** Claims 136 tables (51 InnoDB, 84 MyISAM, 23 views); actual: 120 (93 InnoDB, 0 MyISAM, 27 views). Lists `ibl_plr_chunk` as live (dropped in migration 035). Body date conflicts with frontmatter.
**Suggested direction:** Update counts; remove `ibl_plr_chunk`; remove MyISAM section; sync dates.
**Est. effort:** S
**Risk if untouched:** Agent writes queries against dropped tables.
**Status:** Completed branch `doc-freshness-catchup` (2026-05-19) — table counts refreshed (93 InnoDB/27 views/33 FKs), ibl_plr_chunk removed, MyISAM section deleted, Schema Version line removed, PostgreSQL section removed, body date removed.

### 9.2 DATABASE_GUIDE — Dead PostgreSQL Compatibility Section
**Location:** `ibl5/docs/DATABASE_GUIDE.md` lines 100-105
**Problem:** Section advises avoiding MEDIUMINT/TINYINT and prepping for ORM migration. No such migration is planned.
**Suggested direction:** Delete the section.
**Est. effort:** S
**Risk if untouched:** Agents avoid valid MariaDB constructs for a migration that won't happen.
**Status:** Completed (verified 2026-05-29 audit) — PostgreSQL compatibility section removed from `DATABASE_GUIDE.md` (part of 9.1 / `doc-freshness-catchup`).

### 9.3 `schema-reference.md` — Dropped `nuke_users` Listed as Live
**Location:** `.claude/rules/schema-reference.md` lines 18, 26
**Problem:** Says `nuke_users` is the Users table; dropped in migration 102. Auth now uses `auth_users`.
**Suggested direction:** Replace with `auth_users`; add `gm_username` mapping note.
**Est. effort:** S
**Risk if untouched:** Agent JOINs against nonexistent table; auth queries fail.
**Status:** Completed branch `doc-freshness-catchup` (2026-05-19) — schema-reference.md now cites auth_users with ibl_team_info.gm_username mapping.

### 9.5 `ibl5/docs/README.md` Lists API_GUIDE as "(planned)"
**Location:** `ibl5/docs/README.md` line 23
**Problem:** Index contradicts the 17-controller API reality.
**Suggested direction:** Update description after 9.4.
**Est. effort:** S
**Risk if untouched:** Agents skip API_GUIDE assuming nothing's implemented.
**Status:** Completed branch `doc-freshness-catchup` (2026-05-19) — index row reflects built API.

### 9.6 DEVELOPMENT_GUIDE — Internally Inconsistent Test Counts
**Location:** `ibl5/docs/DEVELOPMENT_GUIDE.md` lines 8, 25, 46
**Problem:** Header: 4393 tests; line 25: 3033; line 46: "3033 to 3089."
**Suggested direction:** Remove inline counts; point to `composer test`.
**Est. effort:** S
**Risk if untouched:** Inconsistent counts cited in PRs.
**Status:** Completed branch `doc-freshness-catchup` (2026-05-19) — inline test counts removed; doc points to `composer test`.

### 9.7 DEVELOPMENT_GUIDE — "Power_Rankings" Module Doesn't Exist
**Location:** `ibl5/docs/DEVELOPMENT_GUIDE.md` lines 20, 422
**Problem:** Lists it as a "Display module"; actual code lives in `Updater/PowerRankingsUpdater.php`.
**Suggested direction:** Remove from display-modules list; update count from 8 to 7.
**Est. effort:** S
**Risk if untouched:** Agent searches for nonexistent dir.
**Status:** Completed branch `doc-freshness-catchup` (2026-05-19) — Power_Rankings removed from display-modules list; count corrected to 7.

### 9.8 ARCHITECTURE_PATTERNS — Outdated "Established" Modules
**Location:** `ibl5/docs/ARCHITECTURE_PATTERNS.md` line 15
**Problem:** Cites PlayerDatabase/FreeAgency/Player as canonical; CLAUDE.md says Waivers.
**Suggested direction:** Align with CLAUDE.md's Waivers designation.
**Est. effort:** S
**Risk if untouched:** Inconsistent canonical pointers confuse agents.
**Status:** Completed branch `doc-freshness-catchup` (2026-05-19) — "Established" example aligned with ADR-0001 / CLAUDE.md (Waivers).

### 9.9 DEVELOPMENT_GUIDE Refers to .github/skills/ — Doesn't Exist
**Location:** `ibl5/docs/DEVELOPMENT_GUIDE.md` lines 10, 82, 435
**Problem:** Skills live at `.claude/skills/`, not .github/skills/.
**Suggested direction:** Find-replace.
**Est. effort:** S
**Risk if untouched:** Agent looks in wrong dir; may create .github/skills/.
**Status:** Completed (verified 2026-06-20) — the stale GitHub-skills path no longer appears in `DEVELOPMENT_GUIDE.md`; skills live at `.claude/skills/` (fixed in the doc-freshness-catchup pass).

### 9.10 `copilot-instructions.md` — Retired (Copilot no longer used)
**Location:** `.archive/copilot-instructions.md`
**Problem:** Copilot-specific instruction mirror, superseded by `.claude/rules/` + `CLAUDE.md`. Not actually loaded by Claude Code (no `.claude/`/settings/hook reference); only GitHub Copilot read it.
**Suggested direction:** Archive — done.
**Est. effort:** S
**Risk if untouched:** None.
**Status:** Resolved 2026-06-10 — Copilot retired; the instruction mirror and prompt files were moved into `.archive/copilot-instructions.md` and `.archive/copilot-prompts/`, and all live references repointed to `.claude/rules/` / `CLAUDE.md` / `TESTING_STANDARDS.md`. (Earlier `doc-freshness-catchup` 2026-05-19 pass had synced the XSS-helper style and skills-dir paths while the file was still live.)

### 9.11 DOCUMENTATION_STANDARDS — Stranded `SECURITY.md` Example
**Location:** `ibl5/docs/DOCUMENTATION_STANDARDS.md` line 35
**Problem:** Cites `DepthChartEntry/SECURITY.md` as canonical; no other module has one.
**Suggested direction:** Remove from list or note as one-off.
**Est. effort:** S
**Risk if untouched:** Agents create spurious `SECURITY.md` files.
**Status:** Implemented — investigation found TWO active exemplars (DepthChartEntry + ComparePlayers, both genuine security-refactor docs); DOCUMENTATION_STANDARDS now lists both and states SECURITY.md is not a per-module requirement (do not create spuriously). The original "single exemplar" premise was inaccurate.

### 9.12 `ibl5/docs/archive/` Is Out of `bin/check-docs` Scope
**Location:** `ibl5/docs/DOCUMENTATION_STANDARDS.md` lines 40-44
**Problem:** "Recent archive" vs `.archive/` (older) is an arbitrary split adding maintenance surface without value. Docs in `ibl5/docs/archive/` silently rot.
**Suggested direction:** Consolidate to one archive location; document freshness-scope explicitly.
**Est. effort:** S
**Risk if untouched:** Archived docs become invisible to CI checks.
**Status:** Completed (merged #1044, maintenance-30) — documentation-drift sweep.

### 9.13 DATABASE_GUIDE "Schema Version: v1.5" Is Meaningless
**Location:** `ibl5/docs/DATABASE_GUIDE.md` line 9
**Problem:** Schema is versioned via migration numbers (past 120), not v1.x.
**Suggested direction:** Remove or point to `SELECT MAX(version) FROM schema_migrations`.
**Est. effort:** S
**Risk if untouched:** Agents cite "v1.5" misleadingly.
**Status:** Completed (verified 2026-05-29 audit) — "Schema Version: v1.5" line removed from `DATABASE_GUIDE.md` (part of 9.1 / `doc-freshness-catchup`).

### 9.14 `css-architecture.md` Loading Verification
**Location:** `.claude/rules/css-architecture.md`
**Problem:** 160-line rule; correctly path-conditional. Verify post-plan / skills correctly re-import what they need.
**Suggested direction:** Verify path-conditional loading; no immediate change.
**Est. effort:** S (verification)
**Risk if untouched:** Low — note for completeness.
**Status:** Implemented — verified accurate, no change needed. css-architecture.md carries paths: frontmatter, so the harness loads it path-conditionally; zero by-name imports is expected for a path-triggered rule, not a defect. Backlog-only resolution.

### 9.16 REFACTORING_HISTORY — Living Doc Indexed in Onboarding
**Location:** `ibl5/docs/REFACTORING_HISTORY.md`
**Problem:** "100% complete" — purely historical; indexed under "For New Contributors."
**Suggested direction:** Move to `ibl5/docs/archive/`; replace onboarding pointer with a one-line summary.
**Est. effort:** S
**Risk if untouched:** New contributors read 600 LOC of completed history instead of architecture.
**Status:** Completed (merged #1044, maintenance-30) — documentation-drift sweep.

### 9.17 PLR_VS_BOXSCORES_ANALYSIS — High-Value Reference With No Hook
**Location:** `ibl5/docs/PLR_VS_BOXSCORES_ANALYSIS.md`
**Problem:** Critical knowledge (All-Star/Rookie filters, finals-phase rule) not referenced from any rule file or CLAUDE.md.
**Suggested direction:** Add pointer in `database-access.md` or `schema-reference.md`.
**Est. effort:** S
**Risk if untouched:** Agent queries produce 12% wrong game counts due to missing filters.
**Status:** Completed (merged #1044, maintenance-30) — documentation-drift sweep.

### 9.18 STRATEGIC_PRIORITIES — Stale Coverage Numbers
**Location:** `ibl5/docs/STRATEGIC_PRIORITIES.md` lines 28-29
**Problem:** Says ~72%; DEVELOPMENT_GUIDE says ~80%. No coverage threshold visible in phpunit.xml/CI.
**Suggested direction:** Run coverage, align docs; identify enforcement location.
**Est. effort:** S
**Risk if untouched:** Wrong coverage target cited in PR reviews.
**Status:** Completed branch `doc-freshness-catchup` (2026-05-19) — coverage figures aligned across STRATEGIC_PRIORITIES + DEVELOPMENT_GUIDE (~80%, 70% threshold).

### 9.21 `ibl5/migrations/README.md` — Dead Reference to Dropped Table FK
**Location:** `ibl5/migrations/README.md` line 282
**Problem:** Lists `ibl_plr_chunk.pid → ibl_plr.pid` (dropped in 035).
**Suggested direction:** Remove the stale FK row.
**Est. effort:** S
**Risk if untouched:** Agent writes queries against nonexistent table.
**Status:** Completed (merged #1044, maintenance-30) — documentation-drift sweep.

### 9.22 DOCUMENTATION_STANDARDS — README Trigger Is "When Refactoring"
**Location:** `ibl5/docs/DOCUMENTATION_STANDARDS.md`
**Problem:** Refactor is 100% done; trigger never fires retroactively. 65 modules remain undocumented.
**Suggested direction:** Add retroactive coverage policy: any PR to a module without README must add one.
**Est. effort:** S (policy) / L (backfill)
**Risk if untouched:** Gap persists indefinitely.
**Status:** Implemented — added a "Retroactive README coverage policy" subsection to DOCUMENTATION_STANDARDS: any PR making a non-trivial change to a module dir under ibl5/classes/<Module>/ that lacks a README must add one in that PR (opportunistic backfill); bulk immediate backfill explicitly out of scope.

### 9.23 `IBL6/README.md` Is Default SvelteKit Scaffolding
**Location:** `IBL6/README.md`
**Problem:** Only `npx sv create` boilerplate. STRATEGIC_PRIORITIES says IBL6 is the live SvelteKit frontend at `ibl6.iblhoops.net`.
**Suggested direction:** Replace with IBL6-specific content (dev mode, API base URL, IBL5 relationship).
**Est. effort:** S
**Risk if untouched:** Any IBL6 contributor starts from zero context.
**Status:** Completed (merged #1044, maintenance-30) — documentation-drift sweep.

### 9.25 STRATEGIC_PRIORITIES — Lists Completed Work as Pending
**Location:** `ibl5/docs/STRATEGIC_PRIORITIES.md`
**Problem:** Section 1 still lists `nuke_users` as remaining (already dropped in migration 102).
**Suggested direction:** Process change: strike completed items per drop PR; add "Completed drops" subsection.
**Est. effort:** S per PR
**Risk if untouched:** Agent re-audits already-completed work.
**Status:** Completed branch `doc-freshness-catchup` (2026-05-19) — Section 1 audited; nuke_users drop moved to explicit "Completed drops" subsection with migration numbers. Remaining count updated to 10 tables. Stale intro text ("~20 tables", "Nine DROP migrations") corrected.

### 9.27 `NegotiationServiceInterface` Lacks PHPDoc — Only Gap in Sampled Set
**Location:** `ibl5/classes/Negotiation/Contracts/NegotiationServiceInterface.php`
**Problem:** No `@param`/`@return`/`@throws`. Out of 20 sampled, only this one.
**Suggested direction:** Add PHPDoc matching `WaiversRepositoryInterface` style.
**Est. effort:** S
**Risk if untouched:** Agents read implementation instead of contract.
**Status:** Completed (merged #1044, maintenance-30) — documentation-drift sweep.

---


## Axis 10: PHPStan Rule Coverage Gaps

### 10.1 Baseline-Counts.json Stale: `ibl.rawSuperglobal` Claims 7 Entries (Actual: 0)
**Location:** `ibl5/phpstan-baseline-counts.json`
**Problem:** 7 entries were burned down; snapshot never updated. Drift detector warns on large decreases but doesn't fail. (2026-05-29 audit: `phpstan-baseline.neon`'s entry list is now empty entirely — the drift is broader than just `rawSuperglobal`.)
**Suggested direction:** `php bin/check-baseline-drift --update`; tighten drift detector to FAIL on large decreases.
**Est. effort:** S
**Risk if untouched:** Misleads maintainers about true baseline.
**Status:** Completed (merged #1028, maintenance-33) — PHPStan baseline hygiene (duplicate of 10.25, already cleared).

### 10.2 `$_SESSION` Direct Access Outside Session Boundary
**Location:** `Discord/Discord.php`, `Extension/ExtensionService.php`, `DepthChartEntry/DepthChartEntrySubmissionHandler.php`, `Bootstrap/LeagueBootstrap.php`, `Trading/TradeProcessor.php`, `Trading/TradeOfferRepository.php`, `PageLayout/PageLayout.php`
**Problem:** `BanRawSuperglobalsRule::BANNED_SUPERGLOBALS` doesn't include `_SESSION`. Services/Repositories/ApiHandlers reading session state are untestable.
**Suggested direction:** Add `_SESSION` to banned list; allowlist `AuthService.php`, `DevAutoLogin.php`, `CsrfGuard.php`, `LeagueBootstrap.php`.
**Est. effort:** S
**Risk if untouched:** Implicit precondition on session order; untestable.
**Status:** Completed (verified 2026-05-29 audit) — `_SESSION` now in `BanRawSuperglobalsRule` allowlist-by-superglobal (banned outside allowlist); part of the 2026-05-17 rule expansion (see [[5.9]]).

### 10.3 `$_SERVER` Direct Access Outside HTTP Boundary
**Location:** `Discord/Discord.php:43`, `Extension/ExtensionService.php:267`, `Trading/TradeProcessor.php:378`, `Trading/TradeOfferRepository.php:58`
**Problem:** Services/Repositories reading `$_SERVER['SERVER_NAME']` to branch behavior; `TradeOfferRepository` behaves differently in CLI vs HTTP.
**Suggested direction:** Add `_SERVER`, `_FILES` to banned list; allow `HtmxHelper`, `ETagHandler`, `LeagueContext`, `PageLayout`, Controllers, Bootstrap, ApiHandlers.
**Est. effort:** S
**Risk if untouched:** Repository behaves differently per environment with no type signal.
**Status:** Completed (verified 2026-05-29 audit) — `_SERVER` now banned in `BanRawSuperglobalsRule` with a broad allowlist (HtmxHelper, ETagHandler, controllers, etc.); part of the 2026-05-17 expansion (see [[5.9]]).

### 10.4 `echo` in Non-View, Non-CLI Classes
**Location:** `DepthChartEntry/DepthChartEntryController.php` (15+ HTML lines), `NextSim/NextSimTabApiHandler.php`, `LeagueStarters/LeagueStartersApiHandler.php`
**Problem:** Controllers emitting HTML directly violate the View-renders-HTML contract; untestable without output buffer.
**Suggested direction:** New `BanEchoOutsideViewRule`; allow `LegacyFunctions.php`, `PageLayout.php`, `DebugOutput.php`, `BulkImport/`, `UI/Tables/`.
**Est. effort:** S
**Risk if untouched:** Controller output un-bufferable; regression magnet.
**Status:** Rule landed (2026-05-19) — `BanEchoInNonViewClassesRule` (`ibl.echoInNonView`). 16 baseline violations in Controllers/ApiHandlers; burndown deferred to incremental follow-up PRs.

### 10.5 `global` Keyword Outside Bootstrap/PageLayout/NukeCompat
**Location:** `UI/DebugOutput.php:26`, `DepthChartEntry/DepthChartEntryView.php:31`, `Team/TeamController.php:91`, `Team/TeamService.php:52`, `Waivers/WaiversController.php:69`, `Updater/ScheduleUpdater.php:58`, several more
**Problem:** `global` pulls named globals into method scope — invisible coupling. Services with `global $leagueContext` aren't injectable for tests.
**Suggested direction:** New `BanGlobalKeywordRule`; allow `LegacyFunctions.php`, `ConfigBootstrap.php`, `NukeCompat.php`, `PageLayout.php`.
**Est. effort:** S
**Risk if untouched:** Silent dep on procedural init order.
**Status:** Rule landed (verified 2026-05-29 audit) — `BanGlobalKeywordRule` exists in `phpstan-rules/` (added with the 2026-05-17 superglobal expansion; see [[5.9]]). Any remaining call-site burndown is incremental.

### 10.6 `$GLOBALS` Access Outside Bootstrap
**Location:** `Player/Views/PlayerViewFactory.php:68-69` (fallback to `$GLOBALS['mysqli_db']`)
**Problem:** View factory bypasses DI; test instantiation silently uses real DB.
**Suggested direction:** New `BanRawGlobalsRule` for variable name `GLOBALS`; allow Bootstrap.
**Est. effort:** S
**Risk if untouched:** Untestable fallback paths.
**Status:** Completed (verified 2026-05-29 audit) — `GLOBALS` covered by `BanRawSuperglobalsRule` allowlist (Bootstrap suffix + `ApiApplicationFactory.php`); no separate `BanRawGlobalsRule` needed. See also [[7.3]] (PlayerViewFactory fallback removed).

### 10.7 `die`/`exit` in Non-CLI Production Classes
**Location:** `Utilities/HtmxHelper.php:33` (`exit;`), `Bootstrap/ConfigBootstrap.php:86`, `Bootstrap/SecurityBootstrap.php:35`, `Bootstrap/LegacyFunctions.php:327`
**Problem:** `HtmxHelper::redirect()` terminates the process — prevents post-redirect logging, cleanup, testability.
**Suggested direction:** New `BanExitInNonCliRule`; allow Bootstrap + `LegacyFunctions.php`.
**Est. effort:** S
**Risk if untouched:** Audit logging post-redirect never runs.
**Status:** Rule landed (2026-05-19) — `BanDieExitInProductionRule` (`ibl.dieExit`). 0 baseline violations. HtmxHelper.php allowlisted pending module lifecycle refactor.

### 10.8 `intval` / `floatval` / `strval` Instead of Casts
**Location:** `Bootstrap/LegacyFunctions.php` (~20), `Bootstrap/ConfigBootstrap.php` (~15)
**Problem:** CLAUDE.md mandates casts; PHPStan can narrow `(int)` but not `intval`.
**Suggested direction:** New `BanCastFunctionsRule`; allow `LegacyFunctions.php`, `ConfigBootstrap.php`.
**Est. effort:** S
**Risk if untouched:** Implicit-radix bugs (`intval('08')` → 0); narrower type inference.
**Status:** Rule landed (2026-05-19) — `BanCastFunctionsRule` (`ibl.castFunction`). 0 baseline violations. Boxscore and NegotiationDemandCalculator intval→(int) burned down. StatsSanitizer allowlisted.

### 10.9 `htmlspecialchars` / `htmlentities` Direct Calls
**Location:** `UI/DebugOutput.php:57`, `Bootstrap/LegacyFunctions.php:293`
**Problem:** Bypasses `HtmlSanitizer::e()` (canonical flags/charset). View rules see opaque method calls.
**Suggested direction:** New `BanRawHtmlEscapeFunctionsRule`; allow `HtmlSanitizer.php`, `LegacyFunctions.php`.
**Est. effort:** S
**Risk if untouched:** Double-encoding or missed XSS vectors.
**Status:** Rule landed (2026-05-31) — `BanRawHtmlEscapeFunctionsRule` (`ibl.rawHtmlEscape`). 0 baseline violations. `HtmlSanitizer.php` allowlisted (canonical escaper). `DebugOutput.php` allowlisted, NOT collapsed to `HtmlSanitizer::e()`: `e()` applies `stripslashes()`, which would mangle debug dumps — the raw call is a deliberate two-step `<br>`-restore pattern from PR #360.

### 10.10 `HtmlSanitizer::trusted()` Is a 70-Site Escape Hatch
**Location:** 70 calls across `FreeAgencyView`, `FreeAgencyOfferView`, `TradingView`, `WaiversView`
**Problem:** `trusted()` is no-op whitelisted in `RequireEscapedOutputRule::SAFE_STATIC_CALLS`. Distinguishes only by code review whether arg is composed `$this->renderX()` (safe) or raw DB value (unsafe).
**Direction (implemented):** `RequireTrustedAnnotationRule` fires `ibl.trustedVariable` when `trusted()`'s first arg is not a string/numeric literal, an `(int)/(float)/(bool)` cast, or a `$this->...()` call. Genuinely-safe new sites acknowledge with a native `// @phpstan-ignore ibl.trustedVariable` comment; existing sites are baselined.
**Est. effort:** M
**Risk if untouched:** Refactors silently extract `trusted($someVar)` with no signal.
**Status:** ✅ Implemented — `RequireTrustedAnnotationRule` (`ibl.trustedVariable`) + ADR-0077. Existing sites baselined (green-green); no runtime code changed. Acknowledgment = native `@phpstan-ignore` suppression, not a bespoke `// @trusted` comment (rejected — see ADR-0077 Alternatives).

### 10.11 `header()` Outside Response Classes
**Location:** `Utilities/HtmxHelper.php:29,31`
**Problem:** `header()` in a Utility class couples response emission to a utility; combined with `exit` makes redirect untestable.
**Suggested direction:** New `BanDirectHeaderCallRule`; allow `*Responder.php`, Bootstrap, `PageLayout.php`, `*ApiHandler.php`.
**Est. effort:** S
**Risk if untouched:** Post-redirect logging never runs.
**Status:** Rule landed (2026-05-31) — `BanDirectHeaderCallRule` (`ibl.directHeader`). Allowlist by suffix: `Bootstrap.php` / `Responder.php` / `ApiHandler.php`, plus exact `HtmxHelper.php` (same allowlist shape as `BanEchoInNonViewClassesRule`). 1 baseline entry: `TradingController.php:94` (raw `header()` for JSON content-type — should route through `JsonResponder`; existing debt).

### 10.12 Direct `$db->query()` in Updater Steps
**Location:** `Updater/Steps/RefreshTeamSeasonRecordsStep.php:38,41,45`, `RefreshPlayoffSeriesResultsStep.php:38,41`, `RefreshIblHistStep.php:36,39`
**Problem:** Bypasses `BaseMysqliRepository::execute()` error codes, logging, type validation.
**Suggested direction:** New `BanDirectMysqliQueryRule` flagging `->query()` on `\mysqli` outside `BaseMysqliRepository`/`Database\MySQL`.
**Est. effort:** M (needs type-scope check)
**Risk if untouched:** Silent `false` on failures vs typed exception.
**Status:** Rule landed (2026-05-31) — `BanDirectMysqliQueryRule` (`ibl.directMysqliQuery`). Flags `->query()` on a `\mysqli`-typed receiver outside the DB-access boundary; allowlist: `BaseMysqliRepository.php` + `Database/MySQL.php`. 7 baseline occurrences across the 3 Updater steps. Refactoring them to `execute()` deferred.

### 10.13 SQL ORDER BY / Table-Name Interpolation Without Whitelist Enforcement
**Location:** `AwardHistory/AwardHistoryRepository.php:59`, `Statistics/TeamStatsCalculator.php:52,194`, `SeasonLeaderboards/SeasonLeaderboardsRepository.php:80`
**Problem:** Currently safe (whitelist maps) but PHPStan can't tell. Next dev may write the same pattern with user input.
**Suggested direction:** New `BanSqlStringInterpolationRule` — flag SQL strings (`SELECT|INSERT|UPDATE|DELETE|FROM|JOIN|ORDER BY`) with interpolated vars; allow `QueryConditions::toWhereClause()`.
**Est. effort:** M
**Risk if untouched:** Pattern is contagious; first user-controlled use is a real injection.
**Status:** Rule landed (2026-05-31) — `BanSqlStringInterpolationRule` (`ibl.sqlStringInterpolation`). Flags `InterpolatedString` SQL literals via an anchored SQL-statement pattern, so prose mentioning SQL keywords as English words is NOT matched. `SeasonLeaderboards:80` is dot-concatenation, correctly NOT a site. Plan estimated 3 sites; the rule found 66 genuine interpolated-SQL sites across ~40 files (32 baseline entries) — all baselined. Refactoring (e.g. AwardHistory `$sortColumn` → match-validated enum) deferred to a separate plan.
**Status (2026-06-25, burndown complete):** All interpolated-SQL sites converted to bound `?` params (values) or validated allowlist/`match()`/concatenated-literal identifiers (table/column names, ORDER BY, grouping columns); every `ibl.sqlStringInterpolation` baseline entry cleared. Done across the burndown PR1/PR2 pair (PR2 the finisher). `grep -c 'ibl.sqlStringInterpolation' phpstan-baseline.neon` → 0; `composer run analyse` green.

### 10.15 `echo ob_get_clean()` Anti-Pattern in `DebugOutput`
**Location:** `UI/DebugOutput.php:61,79`
**Problem:** `ob_start()` → immediately `echo ob_get_clean()` — pointless indirection; in nested ob_start context, corrupts outer buffer.
**Suggested direction:** Code fix (not a rule); document in code review.
**Est. effort:** S
**Risk if untouched:** Nested output buffer corruption.
**Status:** Implemented (2026-06-28) — removed `ob_start()`/`echo ob_get_clean()` wrapper in `UI/DebugOutput::display()` (inline HTML now emits directly; output byte-identical, characterization-pinned by `tests/UI/DebugOutputTest.php`).

### ~~10.16 `ibl.unescapedOutput` Baseline: 11 Entries Remaining~~ ✅ RESOLVED
**Resolved:** 2026-05-17 via `xss-c-career-leaderboards-remainder`. All view-level baseline entries cleared across Plans A/B/C; 11 files now in zero-floor. Zero `ibl.unescapedOutput` entries remain in baseline.

### 10.17 `ibl.cookieBeforeHeader` Baseline: 4 Controllers
**Location:** `FreeAgencyController.php`, `SeriesRecordsController.php`, `TeamController.php`, `WaiversController.php`
**Problem:** All 4 read `$cookie` before `PageLayout::header()` — same bug class as CsrfGuard MAX_TOKENS incident.
**Suggested direction:** Burn down in one PR (one-line reorder each); add to zero-floor.
**Est. effort:** S
**Risk if untouched:** Stale CSRF/auth read in 4 controllers.
**Status:** Completed (2026-05-17) — 4 baselines cleared, 4 files added to zero-floor. See [ADR-0032](decisions/0032-cookie-before-header-zero-floor.md).

### 10.18 ADR-0001: No Rule Blocks `Service extends BaseMysqliRepository`
**Location:** No existing rule
**Problem:** A `class FooService extends BaseMysqliRepository` copy-paste passes PHPStan; Service gains unbounded direct DB access.
**Suggested direction:** New `BanServiceExtendsRepositoryRule`; flag `*Service` whose parent chain includes `BaseMysqliRepository`.
**Est. effort:** M
**Risk if untouched:** Architectural boundary unenforced.
**Status:** Rule landed (2026-05-19) — `BanServiceExtendsBaseRepositoryRule` (`ibl.serviceExtendsRepository`). 0 baseline violations. NewsService renamed to NewsRepository (sole violation).

### 10.19 ADR-0014: No Rule Blocks Forking Modifier Formulas
**Location:** No existing rule
**Problem:** `ContractRules::calculate*Modifier` formulas are centralized; test catches divergence between existing impls, not new parallel impls in calculators.
**Suggested direction:** New `BanDuplicateModifierMethodRule` — flag methods named `calculate*Modifier` outside `ContractRules`.
**Est. effort:** S
**Risk if untouched:** Formula drift root cause re-introducible.
**Status:** Rule landed (2026-05-31) — `BanDuplicateModifierMethodRule` (`ibl.duplicateModifierMethod`). Flags `/^calculate.+Modifier$/` methods outside `ContractRules.php`; skips interface/abstract declarations and suffixless `calculateModifier()` helpers (`NegotiationDemandCalculator`/`FreeAgencyDemandCalculator` are NOT matched). 5 baseline sites (`ExtensionOfferEvaluator`); delegating them to `ContractRules` deferred.

### 10.20 `assertNotNull` on Known-Non-Null Value Slips Through
**Location:** `RequireMeaningfulAssertionsRule` is AST-only
**Problem:** `assertNotNull($value)` when PHPStan knows `$value: string` is meaningless; current rule misses it.
**Suggested direction:** Extend rule using `$scope->getType()->isNull()` — requires type-scope access.
**Est. effort:** L
**Risk if untouched:** False-positive assertion count inflates test metrics.
**Status:** Rule landed (2026-06-29) — `RequireMeaningfulAssertionsRule` sub-check 3 flags `assertNotNull($x)` when `$scope->getType($x)->isNull()->no()` (statically non-null), reporting the resolved type via `VerbosityLevel::typeOnly()`. Scoped to `assertNotNull` only (`assertInstanceOf`/`assertIs*` deferred — they need `isSuperTypeOf` reasoning with higher false-positive risk). The conservative `isNull()->no()` gate (production runs `treatPhpDocTypesAsCertain: false`, so `?Foo` stays `maybe`) flagged zero existing test sites — `phpstan-tests-baseline.neon` is byte-unchanged, no burndown needed.

### 10.21 `BanBareTableIdentifierRule` Doesn't Cover Column Identifiers in SQL Strings
**Location:** Bare column refs in `League/League.php:119,202,221,241` (`p.retired != 1`) and elsewhere
**Problem:** Column renames silently leave stale references — same as `feedback_column_rename_sweep_scripts.md` documents.
**Suggested direction:** Extend `BanInconsistentColumnNamesRule` to match unbackticked occurrences too.
**Est. effort:** M
**Risk if untouched:** Column renames leave silent runtime errors.
**Status:** Rule landed (2026-05-31) — `BanBareColumnIdentifierRule` (`ibl.bareColumnIdentifier`). NOT an extension of `BanInconsistentColumnNamesRule` (global `name`/`value` matching would false-positive every table). New rule narrowly targets bare `ibl_<table>.<column>` qualified refs — the `ibl_` prefix disambiguates them; bare unqualified columns, alias-qualified columns (`p.name`), and `ibl_plr.*` are NOT flagged. Zero false positives in a trial `composer run analyse` → ships blocking (no advisory downgrade needed). 23 baseline occurrences across 3 files (`FreeAgencyAdminRepository`, `PlayerDatabaseRepository`, `Team`).
**Status (2026-06-25, burndown complete):** All bare `ibl_<table>.<column>` refs in the 3 shared files backticked both halves (`` `ibl_plr`.`pid` ``); every `ibl.bareColumnIdentifier` baseline entry cleared. `PlayerDatabaseRepository`'s big query was an interpolated literal (hidden from the column rule) — de-interpolating its `WHERE` clause exposed its `ibl_` refs, which were backticked in the same edit to avoid new violations. `grep -c 'ibl.bareColumnIdentifier' phpstan-baseline.neon` → 0; `composer run analyse` green.

### 10.22 `json_decode` Without `JSON_THROW_ON_ERROR`
**Location:** `Cache/DatabaseCache.php:52`, `Utilities/TestCookieOverrides.php`, `Negotiation/NegotiationRepository.php`, `Trading/TradeQueueProcessor.php`
**Problem:** Malformed JSON returns null silently; downstream code fails far from the parse site.
**Suggested direction:** New `RequireJsonThrowOnErrorRule` checking bitmask arg.
**Est. effort:** M
**Risk if untouched:** Corrupted cache/JSON columns mis-traced.
**Status:** Rule landed (2026-05-19) — `BanJsonDecodeWithoutThrowFlagRule` (`ibl.jsonDecodeWithoutThrow`). 0 baseline violations. All 4 sites burned down with try/catch fallbacks.

### 10.23 Hardcoded Environment Strings in Domain Classes
**Location:** `Trading/TradeOfferRepository.php:58` (`SERVER_NAME === "localhost"`), `Trading/TradeProcessor.php:378`, `Extension/ExtensionService.php:267`, `Discord/Discord.php:43`
**Problem:** Hardcoded `"localhost"`, `"iblhoops.net"`, `"main.localhost"` — invisible config, untestable branching, server-name leak.
**Suggested direction:** New `BanHardcodedEnvironmentStringsRule`; allow Bootstrap, config files, `.example` files, stubs.
**Est. effort:** S
**Risk if untouched:** Production/test divergence silent.
**Status:** Rule landed (2026-05-31) — `BanHardcodedEnvironmentStringsRule` (`ibl.hardcodedEnvString`). Matches `String_` literals `localhost` / `127.0.0.1` / `iblhoops.net` / `www.iblhoops.net` / `main.localhost`. Allowlist: `Bootstrap.php` suffix + `DevAutoLogin.php` + `Discord.php`. 8 baseline entries across 6 existing env-branching classes (`DebugSession`, `ExtensionService`, `Navigation/Views/DesktopNavView`, `PageLayout`, `TradeOfferRepository`, `TradeProcessor`). Config-injection fix of those sites deferred to a separate plan; rule lands to stop new ones.

### 10.24 `RequireStrictTypesRule` Doesn't Cover `phpstan-rules/` Itself
**Location:** `phpstan-rules/RequireStrictTypesRule.php` — `str_contains($file, '/classes/')` skips PHPStan rule files
**Problem:** A new rule file without `declare(strict_types=1)` passes; PHP coercion can misfire inside the rule.
**Suggested direction:** Extend check to `phpstan-rules/`.
**Est. effort:** S
**Risk if untouched:** Rule files self-exempt; coercion bugs in rules.
**Status:** Completed (2026-05-31) — `RequireStrictTypesRule` scope extended to also cover `phpstan-rules/`. All rule files already declare `strict_types` — no baseline change. Modifies an existing rule (no new file) so `adr-check` does not fire.

### 10.25 Baseline Burn-Down Targets (no new rule)
**Location:** `phpstan-baseline.neon` — `ibl.unescapedOutput` (0), `ibl.cookieBeforeHeader` (0 — zero-floored), `ibl.inlineCss` (0 — now inline `@phpstan-ignore`), `ibl.deprecatedHtmlTag` (0) — all zero as of 2026-05-29 audit (was 17/0/6/3)
**Problem:** Existing rules have actionable backlogs; staleness of `rawSuperglobal` (10.1) shows snapshot drift.
**Status:** Largely completed (verified 2026-05-29 audit) — the four cited counts are all 0 in `phpstan-baseline.neon`; remaining work is the snapshot/drift fix in [[10.1]].
**Suggested direction:** Sprint focus + `ibl5/bin/check-baseline-drift --update`.
**Est. effort:** M (cumulative burn-down)
**Risk if untouched:** Baselines stagnate; new violations indistinguishable from inherited.

---


## Axis 11: CSS, Themes, Design System

### 11.5 `import-demands.css` Loaded Standalone Without Token Definitions
**Location:** `import-demands.php` line 203
**Problem:** Uses `var(--token)` references but `style.css` isn't loaded; all tokens fall through to hardcoded fallbacks.
**Suggested direction:** Add to `input.css` and load `style.css` first (matches LeagueControlPanel), or remove `var()` references.
**Est. effort:** S
**Risk if untouched:** Token updates don't reach this page.
**Status:** Completed (merged #1027, maintenance-32) — CSS orphan + dead-code cleanup.

### 11.6 Duplicate Google Fonts Headers in Standalone Pages
**Location:** `classes/LeagueControlPanel/LeagueControlPanelView.php` lines 28-31; `PageLayout::header()` lines 176-178
**Problem:** Both emit preconnect + fonts; LeagueControlPanel uses absolute path, PageLayout uses cache-busted relative. No shared helper.
**Suggested direction:** Extract `PageLayout::renderStandaloneHead(string $title)`; use from LCP, Updater, `demo-403.php`.
**Est. effort:** S
**Risk if untouched:** Font/stylesheet changes need multi-location edits.
**Status:** Completed (merged #1027, maintenance-32) — CSS orphan + dead-code cleanup.

### 11.7 `editor.css` Is Orphaned (99 LOC, Zero References)
**Location:** `themes/IBL/style/editor.css`
**Problem:** TinyMCE 2.x button/toolbar styles; no PHP, CSS, or module references. PHP-Nuke era artifact.
**Suggested direction:** Delete.
**Est. effort:** S
**Risk if untouched:** Dead CSS bloats theme dir.
**Status:** Completed (merged #1027, maintenance-32) — CSS orphan + dead-code cleanup.

### 11.8 PHP-Nuke Era Menu GIFs Likely Unreferenced
**Location:** `themes/IBL/images/menu/` — 6 GIFs (comments.gif, exit.gif, home.gif, info.gif, themes.gif)
**Problem:** Site uses SVG inline icons in NavigationView; quick grep shows no references.
**Suggested direction:** Verify; delete the dir.
**Est. effort:** S
**Risk if untouched:** Misleading assets; no functional risk.
**Status:** Completed (merged #1027, maintenance-32) — CSS orphan + dead-code cleanup.

### 11.9 Dual Token Naming: `@theme` Variables vs `:root` Aliases
**Location:** `design/input.css` `@theme` block + `design/tokens/tokens.css`
**Problem:** Every color declared twice (`--color-navy-900` for Tailwind + `--navy-900` alias for components). Convention not documented; new CSS may use `--color-*` directly, breaking the alias abstraction.
**Suggested direction:** Document the rule in `css-architecture.md`; CI lint `grep -r -- '--color-' design/components/`.
**Est. effort:** S
**Risk if untouched:** Silent inconsistency that bites on Tailwind version bumps.
**Status:** Completed (merged #1027, maintenance-32) — CSS orphan + dead-code cleanup.

### 11.10 Depth Chart CSS Split With Load-Order Coupling
**Location:** `design/components/depth-chart.css` (+ `components/tables/depth-chart.css`, `saved-depth-charts.css`)
**Problem:** Comment at line ~242 warns mobile overrides must load after `tables.css`; multi-file load-order coupling invisible to CSS engine. (2026-05-29 audit: the originally-cited `depth-chart-mobile.css`/`depth-chart-changes.css` are JS files, not CSS — locations corrected.)
**Suggested direction:** Consolidate into `depth-chart.css` (720 LOC combined — manageable).
**Est. effort:** S
**Risk if untouched:** `input.css` reorders silently break depth chart layout.
**Status:** Implemented — `tables/depth-chart.css` + `saved-depth-charts.css` merged into `depth-chart.css`; single `@import` repositioned after `tables.css` to preserve the `.depth-chart-table td` vs `.ibl-data-table td` cascade tie-break; VR-pinned pixel-identical.

### 11.11 `themecenterbox()` Content-Sniffs HTML to Pick CSS Layout
**Location:** `themes/IBL/theme.php` lines 281-314
**Problem:** Uses `strpos($content, 'leaders-tabbed')` etc. to decide wrapper `<div>`. CSS class renames silently change layout.
**Suggested direction:** Replace with explicit `$type` parameter from call sites.
**Est. effort:** S
**Risk if untouched:** CSS renames break homepage layout invisibly.
**Status:** Implemented (#1232, merged 2026-06-28) — `themecenterbox()` now takes an explicit `$type` argument from call sites; the `strpos()` content-sniffing is gone.

### 11.12 DepthChartEntryView Two `style="display:none"` Baselines
**Location:** `classes/DepthChartEntry/DepthChartEntryView.php` lines 340, 342
**Problem:** Simple inline styles that map directly to `hidden` attribute (already used in `TradingView.php:491`).
**Suggested direction:** Replace with `hidden` attribute; remove baseline entries.
**Est. effort:** S
**Risk if untouched:** Two baseline slots stay occupied.
**Status:** Completed (verified 2026-05-29 audit) — no `style="display:none"` remains in `DepthChartEntryView.php`; the two baseline entries are gone.

### 11.13 TradingView `str_replace` Patches CSS Custom Property Into Rendered HTML
**Location:** `classes/Trading/TradingView.php` line 357
**Problem:** `str_replace('style="', 'style="--mobile-order: ' . ...)` patches an already-rendered `TeamCellHelper` output. Fragile.
**Suggested direction:** Pass `--mobile-order` as a parameter to `TeamCellHelper::renderTeamCell()` or accept `$extraStyles` array.
**Est. effort:** S
**Risk if untouched:** Future helper changes silently produce malformed style attrs.
**Status:** Implemented (#1232, merged 2026-06-28) — `--mobile-order` is now passed as an explicit render param into the team-cell helper; the post-render `str_replace` patch is removed.

### 11.15 `transaction-history.css` Contains Dead Section
**Location:** `design/components/transaction-history.css` lines 31-37
**Problem:** Empty Contact List section with "moved to tables.css" comment; misleading.
**Suggested direction:** Remove dead section; consider folding 37-LOC file into `tables.css`.
**Est. effort:** S
**Risk if untouched:** Misleading "moved" comment.
**Status:** Completed (merged #1027, maintenance-32) — CSS orphan + dead-code cleanup.


## Axis 12: Data Files Committed to Repo

### 12.1 `IBL5.log` — 1.1 GB Runtime Log On Disk
**Location:** ibl5/IBL5.log
**Problem:** 1.1 GB simulation log; correctly gitignored via `*.log` but undocumented next to source.
**Suggested direction:** Add a comment in `.gitignore` clarifying it's runtime-generated.
**Est. effort:** S
**Risk if untouched:** Already fine; lack of doc is a trap.
**Status:** Resolved (PR maintenance-31-data-file-gitignore-cleanup) — clarifying comment added beside *.log in root .gitignore:47.

### 12.2 `IBL5.sch` and `Olympics.sch` Tracked With No `*.sch` Gitignore Rule
**Location:** `ibl5/IBL5.sch`, `ibl5/Olympics.sch`
**Problem:** `IBL5.sch` is a test fixture for `SchFileParserTest`. `Olympics.sch` is a runtime disk-fallback source (see 12.14). No `*.sch` gitignore exists, so future season `.sch` files will be tracked accidentally.
**Suggested direction:** Add ibl5/*.sch to .gitignore with !ibl5/IBL5.sch and !ibl5/Olympics.sch exceptions. (Olympics.sch is runtime-referenced — see 12.14 — not deletable. Relocating IBL5.sch to tests/fixtures/ is deferred as a separate test-path PR.)
**Est. effort:** S (gitignore) / M (test paths)
**Risk if untouched:** New season `.sch` files committed by accident; dead `Olympics.sch` clutters every clone.
**Status:** Resolved (gitignore rule + exceptions added). Fixture relocation deferred.

### 12.3 `IBL5.lge` — Test Fixture At App Root
**Location:** `ibl5/IBL5.lge`
**Problem:** JSB binary used by `LgeFileParserTest`, `LeagueConfigServiceTest`. Lives at app root, not `tests/fixtures/`.
**Suggested direction:** Move to `tests/fixtures/IBL5.lge`; update path resolution in 4 tests.
**Est. effort:** S
**Risk if untouched:** Clutter; functional otherwise.
**Status:** Deferred — out of scope for the gitignore-cleanup PR. IBL5.lge stays tracked (already has a !ibl5/IBL5.lge exception and commit 25416a33d "fix: keep IBL5.lge tracked — used as test fixture"); consumed by LgeFileParserTest.php:25 and LeagueConfigServiceTest.php:15. Relocation to tests/fixtures/ requires repointing those tests — a separate PR.

### 12.6 `phpstan-tests-baseline.neon` — 392 KB / 9,519 LOC of Suppression Noise
**Location:** `ibl5/phpstan-tests-baseline.neon`
**Problem:** 35x larger than prod baseline. Dominated by `class.notFound` (MockDatabase alias issue, see 5.1) and `alreadyNarrowedType`. Large diffs mask real changes.
**Suggested direction:** Fix MockDatabase alias issue first (5.1); clean `alreadyNarrowedType` assertions. Target ~50 entries in 1-2 PRs.
**Est. effort:** L
**Risk if untouched:** Baseline diffs mask real new errors; grows with every refactor.
**Status:** Completed (2026-05-16) — baseline reduced from 9,519 → 7,557 lines (mock alias fix); remaining entries are real type errors

### 12.10 `Thumbs.db` Committed
**Location:** ibl5/images/language/Thumbs.db (deleted — git rm'd in this PR)
**Problem:** Windows thumbnail cache; not in `.gitignore`.
**Suggested direction:** `git rm`; add `Thumbs.db` to `.gitignore`.
**Est. effort:** S
**Risk if untouched:** More Windows caches accumulate.
**Status:** Resolved (PR maintenance-31-data-file-gitignore-cleanup) — git rm'd; already ignored via ibl5/.gitignore:18.

### 12.12 `coverage-baseline.json` — Auto-Committed Every CI Run
**Location:** `ibl5/coverage-baseline.json`
**Problem:** 3-line JSON committed by CI; `as_of` date field forces commit even when percentage doesn't change. 10+ sequential `[auto]` commits.
**Suggested direction:** Store in CI artifact (GH Actions cache/gist) or gate auto-commit on meaningful change.
**Est. effort:** S
**Risk if untouched:** Commit noise; rare merge conflicts.
**Status:** Deferred — intentionally tracked. CI consumes and regenerates it (.github/workflows/tests.yml:128 check-coverage-regression, :515 git add; ADR-0018). Untracking would break the coverage-regression gate. Gating auto-commit on meaningful change is a separate CI-behavior PR.

### 12.13 `phpunit-baseline.xml` — Vendor Deprecation Suppressions
**Location:** `ibl5/phpunit-baseline.xml`
**Problem:** Suppresses PHP 8 deprecation warnings from `delight-im/auth`; vendor update can silently break the match.
**Suggested direction:** CI step verifying baseline matches installed vendor; or pin to a fork with deprecation fix.
**Est. effort:** S
**Risk if untouched:** Silent drift after vendor updates.
**Status:** Deferred — intentionally tracked. Referenced by ibl5/phpunit.xml:287 <source baseline="phpunit-baseline.xml">. Removing it breaks the PHPUnit config. Adding a CI vendor-match check is a separate PR.

### 12.14 `Olympics.sch` Runtime-Referenced Schedule File
**Location:** `ibl5/Olympics.sch`
**Problem:** 80 KB JSB schedule from PR #284. Earlier audit wrongly reported "no references" — it is a runtime disk-fallback source: ScheduleUpdater.php:235-237 builds {filePrefix}.sch and LeagueContext::getFilePrefix() (line 230) returns 'Olympics' in Olympics context, so SchFileParser::parseFile() reads ibl5/Olympics.sch.
**Suggested direction:** Complete Olympics pipeline or delete + gitignore.
**Est. effort:** S (delete) / M (complete)
**Risk if untouched:** False impression of working pipeline; ongoing binary drift.
**Status:** Resolved — keep tracked (NOT deleted). gitignore !ibl5/Olympics.sch exception added to document deliberate retention.

---


## Axis 13: Duplication Across Modules

### 13.1 Player Averages Views — Near-Identical Quartet
**Status:** RESOLVED (PR `player-averages-stats-renderer`)
**Solution:** Extracted `PlayerSeasonTableRenderer` with `PlayerSeasonTableMode::AVERAGES`/`TOTALS` enum and `PlayerSeasonTableConfig` value object. All 6 views (3 averages + 3 totals) delegate to the shared renderer.

### 13.2 Player Stats (Totals) Views — Near-Identical Triplet
**Status:** RESOLVED (PR `player-averages-stats-renderer`)
**Solution:** Merged with 13.1 — single `PlayerSeasonTableRenderer` handles both averages and totals mode via config.

### 13.3 `game_of_that_day` Subquery Copy-Pasted 5 Times
**Location:** `LeagueSchedule/LeagueScheduleRepository.php:42`, `TeamSchedule/TeamScheduleRepository.php:42`, `RecordHolders/RecordHoldersRepository.php:73,245,660`
**Problem:** Identical derived-table pattern.
**Suggested direction:** Promote to a SQL view (`vw_game_box_id`) or a protected static helper on `BaseMysqliRepository` (following `buildOffenseSubquery`).
**Est. effort:** S
**Risk if untouched:** Dedup logic change requires updating 5 copies.
**Status:** ✅ Implemented (verified 2026-06-28) — the protected-helper option was taken: `BaseMysqliRepository::gameOfThatDaySubquery()` returns the canonical derived-table fragment, called from `LeagueScheduleRepository`, `TeamScheduleRepository`, and `RecordHoldersRepository` (3 methods). No inline `MIN(game_of_that_day)` copies remain outside the helper. The doc had this stale-Open (no plan owned it).

### 13.5 `vw_series_records` Query Duplicated in Two Modules
**Location:** `Standings/StandingsRepository.php:261`, `SeriesRecords/SeriesRecordsRepository.php:44`
**Problem:** Identical `SELECT self, opponent, wins, losses FROM vw_series_records` in two repos.
**Suggested direction:** `StandingsRepository` delegates to `SeriesRecordsRepository::getSeriesRecords()`.
**Est. effort:** S
**Risk if untouched:** View rename or column change drifts one query.
**Status:** Completed (merged #1033, maintenance-40) — cross-module query dedup.

### 13.6 `SELECT DISTINCT year FROM ibl_hist` Duplicated
**Location:** `SeasonLeaderboards/SeasonLeaderboardsRepository.php:93`, `Api/Repository/ApiLeadersRepository.php:100`
**Problem:** Both query for the year-filter dropdown source.
**Suggested direction:** Add `getAvailableSeasonYears()` to `SeasonQueryRepository`; both delegate.
**Est. effort:** S
**Risk if untouched:** Minor drift risk.
**Status:** Completed (merged #1033, maintenance-40) — cross-module query dedup.

### 13.8 `p.retired` Filter Inconsistency: `= 0` vs `!= 1`
**Status:** Completed (2026-06-05) — `League/League.php`'s 4 `p.retired != 1` sites (getAllStar/MVP/SixthPerson/RookieOfYear candidate queries) standardized to `p.retired = 0`. Behavior-preserving: `ibl_plr.retired` holds exactly {0,1} (verified live: 659 active / 903 retired, 0 sentinels/NULLs). `tests/League/RetiredFilterCharacterizationTest` locks the emitted filter SQL. The `DEFAULT 0 NOT NULL` schema hardening stays out of scope (see [[15.4]]).
**Location:** `League/League.php:119,202,221,241` (`!= 1`); 139, 156, 182 (`= 0`); `Team/TeamQueryRepository.php` (`= 0`); `ContractList/ContractListRepository.php` (`= 0`)
**Problem:** Semantically equivalent in MySQL but different intent. With native types both work; future NULL would diverge silently.
**Suggested direction:** Standardize to `retired = 0`; add `DEFAULT 0 NOT NULL` constraint in migration.
**Est. effort:** S
**Risk if untouched:** If NULL ever becomes valid, the two forms return different rows.

### 13.9 Team Color CSS Custom Property — Four Variable Name Sets
**Location:** `UI/Components/TableViewDropdown.php` + `TableViewSwitcher.php` (`--team-tab-bg-color`/`--team-tab-active-color`); `TeamSchedule/TeamScheduleView.php` (`--team-primary`/`--team-secondary`); `Team/Views/BannersView.php` (`--banner-primary`/`--banner-secondary`); `UI/TableStyles.php` + tables (`--team-cell-bg`/`--team-cell-color`)
**Problem:** Four distinct naming schemes inject the same two colors; only some call sites sanitize.
**Suggested direction:** Canonical variable names + single `TableStyles::inlineTeamVars(string $c1, string $c2)` method with sanitization. Component-specific names become CSS aliases.
**Est. effort:** M
**Risk if untouched:** Hex-injection bypass through unsanitized site; CSS variable rename breaks only one variant.
**Status:** ✅ Implemented (verified 2026-06-28) — `UI\TableStyles::inlineTeamVars()` emits canonical `--team-color-primary`/`--team-color-secondary`, sanitizing each via `sanitizeColor()` (hex regex, `000000` fallback). Adopted across `TableViewDropdown`, `TableViewSwitcher`, `TeamScheduleView`, `BannersView`, `NextSimView`, `TeamCardRenderer`, `Ratings`, `Contracts`. The four old naming schemes are CSS aliases (`design/CSS_TABLE_MAP.md`: `--team-tab-bg-color`/`--banner-primary` → `var(--team-color-primary)`). The `--team-cell-bg`/`--team-cell-color` pair is emitted by the separate `TeamCellHelper` (also sanitized) because it renders a whole `<td>`, not just the vars — a deliberate second helper, not an unsanitized site. The hex-injection risk that drove the 🟦 human-merge tag is therefore fully closed; the doc had this stale-Open.

### 13.10 `CareerLeaderboards` vs `SeasonLeaderboards` Stat-Row Formatting Diverges
**Status:** 🚫 Declined (2026-06-24) — premise invalid. The two services' `processPlayerRow` return arrays are disjoint: Career's `FormattedPlayerStats` (22 keys, percentages interleaved, `pts`/`drb`, all values formatted strings, retired `*` appended) vs Season's `ProcessedStats` (44 keys, raw-totals block then grouped percentages then a per-game `mpg…ppg` block, `points`/`drebpg`, raw ints, plus `year`/`teamname`/team-color/`qa`). They differ in key membership, order, AND per-key value-type; the only identical residue is the `pid` passthrough. No shared `STAT_COLUMNS`/`assembleRow` surface exists, so no `StatRowFormatter` was extracted — forcing one would be cosmetic co-location that raises (not lowers) maintenance cost. The premise that both "iterate the same 18-stat-column set" is also false: neither service iterates a column list (both write positional array literals).
**Location:** `CareerLeaderboards/CareerLeaderboardsService.php` (`processPlayerRow`, 90 LOC), `SeasonLeaderboards/SeasonLeaderboardsService.php`
**Problem:** Both iterate over the same 18-stat-column set with `StatsFormatter::formatTotal`/`formatPerGameAverage`.
**Suggested direction:** Extract `StatRowFormatter::formatTotalsRow()`, `formatAveragesRow()`; both delegate.
**Est. effort:** M
**Risk if untouched:** Adding a stat column requires touching both services + views.

### 13.11 `FranchiseRecordBook` Apostrophe-Stripping Subquery — 3 Copies in One File
**Status:** Completed (2026-06-05) — extracted the identical `REPLACE(name,'''','')` derived-table LEFT JOIN into `private static function cleanNamePidSubquery()`, called from all three methods. SQL is equivalent; the existing `tests/DatabaseIntegration/FranchiseRecordBookRepositoryTest` (exercises all 3 methods) is the green-green characterization.
**Location:** `FranchiseRecordBook/FranchiseRecordBookRepository.php` lines 36, 66, 95
**Problem:** Same `REPLACE(name, '''', '')` derived-table subquery in all three public methods.
**Suggested direction:** Extract `private static function playerPidByCleanNameSubquery(): string`.
**Est. effort:** S
**Risk if untouched:** Special-character handling drift across copies.

### 13.13 `game_min > 0` DNP Filter Applied Inconsistently
**Location:** Applied correctly in `PlrParser/PlrBoxScoreRepository.php`, `UI/Tables/PeriodAverages.php`, `Team/SplitStatsRepository.php`; other consumers don't always apply it.
**Problem:** MEMORY.md documents the DNP-row invariant but it's not enforced; each consumer must remember.
**Suggested direction:** Constant `PlrBoxScoreRepository::PLAYED_CONDITION = 'game_min > 0'`; add a PHPStan rule or schema comment documenting the invariant.
**Est. effort:** S
**Risk if untouched:** New consumers inflate games-played counts or allow 0-stat season-high rows.
**Status:** Completed (merged #1033, maintenance-40) — cross-module query dedup. DNP-correctness follow-ups: playoff/HEAT career & per-season averages now exclude DNP rows in PHP (merged #1087, maintenance-40b) and in the leaderboard DB views (merged #1088, maintenance-40c).

---


## Axis 14: Bootstrap / Dependency Injection

### 14.1 `Bootstrap\Application` Container Built But Never Wired
**Location:** `classes/Bootstrap/Application.php`, `Container.php`, all step classes
**Problem:** Full DI container + 8 step classes exist; zero production files instantiate it. `LoggingBootstrap.php` comments "Ready to be wired into Bootstrap\Application." Only `tests/Bootstrap/ApplicationTest.php` uses it.
**Suggested direction:** Wire `Application` into `mainfile.php` as the composition root; delete duplicated procedural code.
**Est. effort:** M
**Risk if untouched:** Every bootstrap concern added twice; the two copies diverge.
**Status:** Completed (2026-05-17) — web + api + test wired via factories; duplicate procedural code deleted. ADR-0030.

### 14.2 Duplicate Bootstrap Logic — mainfile.php and Step Classes in Parallel
**Location:** `mainfile.php` lines 102-243 vs `Bootstrap/SessionBootstrap.php`, `HeadersBootstrap.php`, `ConfigBootstrap.php`, `AuthBootstrap.php`
**Problem:** `ConfigBootstrap::PROTECTED_GLOBALS` is verbatim copy of `mainfile.php:165-182`. Sessions/headers/auth/league hydration duplicated.
**Suggested direction:** After wiring `Application` (14.1), delete blocks from `mainfile.php`.
**Est. effort:** S (post 14.1)
**Risk if untouched:** Updates to one copy don't reach the other.
**Status:** Completed (2026-05-17) — duplicate session/header/auth blocks removed; mainfile.php reduced to 65 lines. ADR-0030.

### 14.3 `LegacyFunctions.php` Duplicates `mainfile.php` but Is Always Suppressed
**Location:** `classes/Bootstrap/LegacyFunctions.php`
**Problem:** Re-defines `is_admin`, `is_user`, `blocks`, `cookiedecode`, `filter`, etc. with `if (function_exists('include_secure')) return;` guard. In production mainfile.php fires first; LegacyFunctions does nothing. Bugs in mainfile copy don't reach LegacyFunctions.
**Suggested direction:** After eliminating mainfile copies, LegacyFunctions is the only source; keep guard for test safety.
**Est. effort:** S
**Risk if untouched:** Test/prod divergence on function fixes.
**Status:** Completed (2026-05-17) — LegacyFunctions is single source; mainfile delegates via require_once. ADR-0030.

### 14.4 Three Separate Bootstrap Paths — mainfile / api / tests
**Location:** `ibl5/mainfile.php`, `ibl5/api.php`, `ibl5/tests/bootstrap.php`
**Problem:** Each duplicates autoload + config + db + symlink fix. `api.php` builds its own mini-bootstrap (auth + rate limit), bypassing the step framework.
**Suggested direction:** `Bootstrap\Kernel::boot(string $mode)` factory accepting `'web'|'api'|'test'`; all three entry points call it.
**Est. effort:** M
**Risk if untouched:** Cross-cutting concerns silently missing in one path; API behaves differently from web.
**Status:** Completed (2026-05-17) — three modes via Factory pattern (WebApplicationFactory, ApiApplicationFactory, TestApplicationFactory). ADR-0030.

### 14.7 `PdoConnection::getInstance()` Hidden Service Locator Inside `AuthService`
**Location:** `classes/Database/PdoConnection.php:23`, `classes/Auth/AuthService.php:59`
**Problem:** `AuthService::getAuth()` lazily calls `PdoConnection::getInstance()` reading globals. The `?Auth $auth = null` parameter is the only escape valve.
**Suggested direction:** Register `\PDO` factory in the container; inject via `?Auth` parameter.
**Est. effort:** S
**Risk if untouched:** Tests can't swap PDO without global mutation.
**Status:** Completed (merged #1042, maintenance-45) — lazy PDO factory injected into AuthService (DI call-site burndown C16).

### 14.11 `api.php` Is Its Own Composition Root Bypassing Bootstrap
**Location:** `ibl5/api.php` lines 27-88
**Problem:** Manual `ApiKeyAuthenticator`, `RateLimiter`; dynamic `new $controllerClass($mysqli_db)` dispatch. Bypasses `Bootstrap\Application`.
**Suggested direction:** Extend `Bootstrap\Application` with `'api'` mode; replace dynamic instantiation with container-resolved factories.
**Est. effort:** M
**Risk if untouched:** API controllers can't share bootstrap state (LeagueContext, logging context, feature flags).
**Status:** Completed (2026-05-17) — api.php is ~40 lines, all bootstrap via ApiApplicationFactory.

### 14.13 `Season` Instantiated 30 Times in Class Files
**Location:** ~30 `new Season($db)` calls across `classes/`
**Problem:** `Season` queries the DB on construction (phase, dates, settings). N redundant queries per request.
**Suggested direction:** Register as shared factory in container; or cache `SeasonQueryRepository` results.
**Est. effort:** S
**Risk if untouched:** Every new season-aware feature adds another redundant query.
**Status:** Completed (merged #1096, maintenance-49 / chunk C16b) — the ~30/90 raw figure reconciled to 18 actionable `new Season($db)` construction sites (constant refs and already-DI'd params excluded); all 18 converted to trailing-optional `?Season $season = null` injection with the existing factory fallback (green-green). `Season::IBL_*` constant refs are not DI targets and are out of scope by nature.

### 14.14 `LoggerFactory` Static Service Locator Used in 20+ Sites
**Location:** `Logging/LoggerFactory.php`; called via `LoggerFactory::getChannel('audit')` across controllers
**Problem:** Static accessor bypasses DI; `LoggingBootstrap` registers in container, container isn't used in production.
**Suggested direction:** Inject `LoggerInterface` (PSR-3) per channel at construction; retire static calls outside bootstrap.
**Est. effort:** M
**Risk if untouched:** Log impl swaps require editing factory class; mock injection impossible in tests.
**Status:** Completed — per-channel `logger.<channel>` bindings registered in container (PR1, 2026-05-19); static call-site burndown done: `?LoggerInterface` injected into 20 single-channel classes (merged #1093, maintenance-48 / chunk C16c) and into 7 multi-channel classes (merged #1094, maintenance-52). `Auth\DevAutoLogin` also converted static→instance with an injectable `'auth'` logger (merged #1095).


## Axis 15: Migrations and Schema Clarity

### 15.2 `ibl_box_scores.teamID` — Stranded Camel Case Omitted From Migration 121
**Location:** `ibl_box_scores.teamID` line 324
**Problem:** Migration 121 (pending) renames `game*` family but leaves `teamID`. Post-121, this becomes the lone camelCase column on an otherwise-snake_case table. Used by 4 views.
**Suggested direction:** Add `CHANGE COLUMN teamID team_id` to migration 121 + view updates.
**Est. effort:** S
**Risk if untouched:** Visual anomaly; future queries use the wrong name.
**Status:** Completed — migration 114 already renamed teamID→teamid on ibl_box_scores and ibl_olympics_box_scores; the unified standard is single-token teamid (config/schema-assertions.php), not team_id. No further rename. Views already use bs.teamid (migration 121).

### 15.4 `retired` — `int(11)` on Career Tables, `tinyint(1)` on Player Tables
**Location:** `ibl_olympics_career_avgs.retired` and `ibl_olympics_career_totals.retired` (int(11) NOT NULL DEFAULT 0); `ibl_plr.retired` and `ibl_olympics_plr.retired` (tinyint(1) DEFAULT NULL)
**Problem:** Same boolean concept, two types + nullability. PHP native types give `int` from career tables vs nullable boolean-equivalent from player tables.
**Suggested direction:** Standardize career tables to `tinyint(1) NOT NULL DEFAULT 0`.
**Est. effort:** S
**Risk if untouched:** `=== 0` checks may fail silently.
**Status:** Completed (maintenance-27, migration 135) — `retired` on `ibl_olympics_career_avgs`/`_totals` is now `tinyint(1) NOT NULL DEFAULT 0`.

### 15.5 `HasMLE` / `HasLLE` / `Used_Extension_*` Booleans Stored as `int(11)`
**Location:** `ibl_team_info` lines 2351-2354
**Problem:** Boolean-intent columns use `int(11)`; convention is `tinyint(1)`. Storage and visual inconsistency.
**Suggested direction:** Downsize to `tinyint(1) NOT NULL DEFAULT 0`.
**Est. effort:** S
**Risk if untouched:** Storage inefficiency; cognitive overhead.
**Status:** Completed (maintenance-27, migration 135) — `has_mle`, `has_lle`, `used_extension_this_chunk`, `used_extension_this_season` on `ibl_team_info` are now `tinyint(1)`; existing nullability preserved (`used_extension_this_season` stays nullable).

### 15.8 `ibl_demands.pid` Lacks FK; `name` Is the PK
**Location:** `ibl_demands` lines 421-434
**Problem:** PK is `name varchar(32)` — name-string scan instead of int. No FK on `pid` despite migration 038 adding the column and index. Player rename → orphan demands.
**Suggested direction:** Add FK `pid → ibl_plr.pid ON DELETE CASCADE`; migrate PK to `pid`.
**Est. effort:** M
**Risk if untouched:** Rename migrations leave stale demand rows.
**Status:** Completed (merged #1037, maintenance-43) — ibl_demands pid FK + PK rebuild.

### 15.9 `ibl_trade_info` Missing FK to `ibl_trade_offers`
**Location:** `ibl_trade_info` lines 2466-2479
**Problem:** `tradeofferid` references `ibl_trade_offers.id` semantically; no FK; no cascade.
**Suggested direction:** Add `FOREIGN KEY (tradeofferid) REFERENCES ibl_trade_offers (id) ON DELETE CASCADE ON UPDATE CASCADE`.
**Est. effort:** S
**Risk if untouched:** Orphaned line items after offer deletion; phantom trade history.
**Status:** Completed (migration 067 `fk_trade_info_offer`) — verified 2026-06-05 against the migrated schema: `ibl_trade_info.tradeofferid` has an FK to `ibl_trade_offers.id`. No new work in maintenance-27.

### 15.10 `ibl_box_scores.teamID` Lacks FK
**Location:** `ibl_box_scores.teamID` line 324
**Problem:** Has index `idx_team_id` but no FK to `ibl_team_info.teamid`. Other team-ID columns on table have FKs.
**Suggested direction:** Add FK alongside migration-121 rename; ensure All-Star team IDs (50/51) are in `ibl_team_info`.
**Est. effort:** S
**Risk if untouched:** Invalid team IDs writable; stat views silently wrong.
**Status:** Completed (maintenance-41, migration 142) — fk_boxscore_team on ibl_box_scores.teamid → ibl_team_info.teamid (ON UPDATE CASCADE), plus parity fk_olympics_boxscore_team. Special teams 0/40/41/50/51 confirmed present in ibl_team_info; zero orphans. Signedness matches (int(11) both sides).

### 15.11 Migration Numbering Gaps — 018-023, 111
**Location:** `ibl5/migrations/`
**Problem:** Sequence jumps 017 → 024; 110 → 112 (no 111). Natural sort makes them harmless to execute, but ambiguous.
**Suggested direction:** Document gaps as intentional in migrations README; new migrations must use next sequential after 126 or timestamp format.
**Est. effort:** S
**Risk if untouched:** Future `019` or `111` could execute out of dependency order.

### 15.13 `ibl_box_scores_teams.name` — Denormalized Snapshot Without Clear Lifecycle
**Location:** `ibl_box_scores_teams.name varchar(16)` line 356
**Problem:** Denormalized team name; `DEFAULT ''` (empty); indexed; queried actively. No FK, no trigger maintaining it.
**Suggested direction:** Deprecate (join through `ibl_franchise_seasons` instead) or NOT NULL + backfill.
**Est. effort:** M
**Risk if untouched:** `WHERE name = 'Bulls'` returns empty for games where the column wasn't populated.
**Status:** Completed (maintenance-28, migration 138) — `name` is now `varchar(16) NOT NULL DEFAULT ''` on both `ibl_box_scores_teams` and the Olympics parity table `ibl_olympics_box_scores_teams`, with a comment documenting the intentional denormalization (no FK: `ibl_team_info.team_name` is not unique and renames would break a FK). A defensive `UPDATE … SET name='' WHERE name IS NULL` precedes the constraint so the change is safe even on dirty production rows. `OlympicsSchemaParityTest` green (it compares column names/indexes, not the NOT NULL change).

### 15.14 `ibl_one_on_one` — No FK on Player Names
**Location:** `ibl_one_on_one` lines 1679-1690
**Problem:** Identifies players by `winner`/`loser` name-string columns; no FK. Player rename migrations don't cascade.
**Suggested direction:** Add `winner_pid`, `loser_pid` int FKs; backfill; migrate queries.
**Est. effort:** M
**Risk if untouched:** Rename migrations leave silent data drift.
**Status:** Completed (maintenance-28, migration 139) — added nullable `winner_pid`/`loser_pid int(11)` surrogate FKs → `ibl_plr.pid` (`ON DELETE SET NULL`), indexed, backfilled from `ibl_plr` by name (only names resolving to exactly one pid; ambiguous names left NULL by design). Display strings `winner`/`loser` retained for backward compatibility.

### 15.15 `ibl_votes_ASG` / `ibl_votes_EOY` — `varchar(255)` for Player Names
**Location:** `ibl_votes_ASG` lines 2510-2525, `ibl_votes_EOY` lines 2537-2548
**Problem:** Each ballot-slot column is `varchar(255)` vs the schema-wide `varchar(32)` for player names; 16 ballot columns per table.
**Suggested direction:** Resize to `varchar(32)`. Longer: child table with `ibl_plr.pid` FK.
**Est. effort:** S (resize) / M (normalize)
**Risk if untouched:** Type mismatch on joins; row size inflated.
**Status:** Completed (maintenance-44, migration 145) — resized all 16 `ibl_votes_ASG` + 12 `ibl_votes_EOY` ballot columns from `varchar(255)` to `varchar(128)` under `STRICT_ALL_TABLES` (snake_case names per migration 120: `east_f1..west_b4`, `mvp_1..gm_3`). The deferred `varchar(32)` target was correctly unsound: these columns store a `"PlayerName, TeamName"` **composite** (`VotingBallotView.php:170` — `$safeValue = $safeName . ', ' . $safeTeamName`; `VotingResultsService::extractPlayerName` strips the trailing `", TeamName"`), and the stored value is raw (prepared-statement `'s'` binds, no entity-expansion). The code-provable maximum composite length is **75 chars** — player categories = `ibl_plr.name`(32) + `", "` + `ibl_team_info.team_name`(16) = 50; GM category = `owner_name`(32) + `", "` + `trim(team_city`(24)` + ' ' + team_name`(16)`)`(≤41) = 75. `varchar(128)` carries 53 chars headroom; `varchar(64)` was **rejected** (75 > 64 would truncate GM composites under strict mode). The sole non-NULL writer is `VotingRepository::saveAsgVote`/`saveEoyVote`; `LeagueControlPanelRepository` only NULLs these columns (clear). This is the **composite-bounded** counterpart to the still-deferred 15.23 (`ibl_gm_history.name` free-text, no code-derivable bound). **Out of scope / still open:** precondition (2) — the DatabaseIntegration seed stores **bare** GM ballot values while the live render path appends the team (a data-consistency question, not a width one; bare values are shorter and don't affect the 75-char bound). Any future shrink below `varchar(128)` requires the prod `MAX(CHAR_LENGTH)` audit query documented in `ibl5/migrations/145_resize_votes_ballot_columns.sql`.

### 15.16 `ibl_draft.team` and `ibl_fa_offers.team` — Denormalized Name Columns
**Location:** `ibl_draft.team varchar(255)` line 442; `ibl_fa_offers.team varchar(32)` line 534
**Problem:** Both carry both `tid` FK and a `team` varchar; `ibl_draft.team` is 8x larger than the schema standard.
**Suggested direction:** Downsize `ibl_draft.team` to `varchar(35)`; longer-term mark as snapshot or deprecate.
**Est. effort:** S (resize) / M (deprecate)
**Risk if untouched:** Row width inflated; rename sensitivity.
**Status:** Completed (#1157, maintenance-41c) — `ibl_draft.team` downsized varchar(255)→varchar(35); `ibl_fa_offers.team` already at the varchar(32) standard (verified 2026-06-20).

### 15.18 `ibl5/migrations/README.md` — Documents Migration 009 as "PENDING" Feb 2026
**Location:** `ibl5/migrations/README.md` line 238
**Problem:** Today is May 2026; migration list runs to 126. Roadmap content mixed with operational. Dead references to `DATABASE_SCHEMA_IMPROVEMENTS.md` and `SCHEMA_IMPLEMENTATION_REVIEW.md`.
**Suggested direction:** Strip roadmap to archive; keep only runner / naming / bypass docs.
**Est. effort:** S
**Risk if untouched:** doc-freshness CI flags dead refs; stale "pending" sections misread.

### 15.19 `ibl_league_config.team_name` — Denormalized String With No FK
**Location:** `ibl_league_config.team_name varchar(32)` line 863
**Problem:** No FK to `ibl_team_info`. `trg_team_identity_sync` trigger updates `ibl_franchise_seasons` but not this.
**Suggested direction:** Add `franchise_id int` FK; backfill; deprecate or comment `team_name` as point-in-time snapshot.
**Est. effort:** M
**Risk if untouched:** Renames silently leave stale entries; cross-team joins return zero rows.
**Status:** Completed (maintenance-28, migration 140) — added nullable `teamid int(11)` surrogate FK → `ibl_team_info.teamid` (`ON DELETE SET NULL`), indexed, backfilled by `team_name`; `trg_team_identity_sync` extended to also rewrite `ibl_league_config.team_name` for that teamid on rename (recreated `PRECEDES trg_gm_tenure_track` to preserve the original activation order). `team_name` kept as a point-in-time snapshot string. (Surrogate key is `teamid`, not the `franchise_id` the suggestion named — `teamid` is the live PK on `ibl_team_info`.)

### 15.20 `ibl_box_scores.pos` Not Constrained to Valid Positions
**Location:** `ibl_box_scores.pos varchar(2)` line 291
**Problem:** `ibl_plr.pos` is ENUM; `ibl_box_scores.pos` is unrestrained varchar. JSB import can write `'X'` without constraint.
**Suggested direction:** Convert to matching ENUM or add CHECK constraint.
**Est. effort:** S
**Risk if untouched:** Position data diverges from canonical player record; position breakdowns include garbage.
**Status:** Completed (maintenance-27, migration 135) — `pos` on `ibl_box_scores` and `ibl_olympics_box_scores` (Olympics parity pair) is now `enum('PG','SG','SF','PF','C','G','F','GF','')`, matching `ibl_plr.pos`. Out-of-range values error under `STRICT_ALL_TABLES`.

### 15.21 `ibl_box_scores` Missing `(pid, game_type)` Composite Index
**Location:** Indexes lines 325-342
**Problem:** Has `(game_type, pid)` and `(pid)` but not `(pid, game_type)` — the dominant career-stat pattern. Migration 091 adds covering index `(pid, game_type, season_year)` — verify pending status.
**Suggested direction:** Confirm 091 is in queue; if missing, add `KEY idx_pid_gt (pid, game_type)`.
**Est. effort:** S
**Risk if untouched:** Full-table scans on multi-million-row table; 10-100x slower career aggregates.
**Status:** Completed (verified 2026-05-29 audit) — migration 091 added the covering index; migration 121 recreates `idx_gt_pid (game_type, pid)` + `idx_gt_pid_season (game_type, pid, season_year)`, which the optimizer uses for pid+game_type filters.

### 15.22 Non-Idempotent Recent Migrations (113, 117, 122, 125)
**Location:** Migrations 113, 117, 122, 125
**Problem:** Bare `ALTER TABLE ... CHANGE COLUMN` / `DELETE FROM` without `IF EXISTS`. Fresh install from production dump partial-state fails.
**Suggested direction:** Use `RENAME COLUMN IF EXISTS` (MariaDB 10.5.2+); conditional DELETEs; `ADD KEY IF NOT EXISTS`.
**Est. effort:** S
**Risk if untouched:** Re-seed scenarios fail fatally.
**Status:** Completed (maintenance-27) — 113 & 117 rewritten to `RENAME COLUMN IF EXISTS` + guarded `MODIFY COLUMN IF EXISTS` (reproducing each original `CHANGE COLUMN` target); 125 to `ADD INDEX IF NOT EXISTS`. 122 was already idempotent (bare `DELETE` of absent rows). Equivalence to the originals proven by an information_schema diff of fresh-built DBs (only the 15.4/15.5/15.20 type deltas differ); double-apply confirmed error-free.

### 15.23 `ibl_gm_history.name` — Ambiguous (Username vs Display Name)
**Location:** `ibl_gm_history.name varchar(50)` line 597 with comment "GM username"
**Problem:** Migration 099 introduced `gm_username` distinction on `ibl_team_info`; this table still uses generic `name`. Elsewhere `name` means display/player name.
**Suggested direction:** Rename to `gm_username`; add FK to `auth_users.username` or `ibl_gm_tenures.gm_username`.
**Est. effort:** S
**Risk if untouched:** Joins to `ibl_gm_tenures.gm_username` require manually-noted alias translation.
**Status:** Completed (maintenance-28, migration 137) — disambiguated as a GM **username**, comment set to `GM username (ref ibl_team_info.gm_username; no enforced FK)`. Deliberately NOT shrunk from `varchar(50)`: historical usernames cannot be proven ≤25 (CI seed has zero rows so the length audit is uninformative, and `ibl_gm_tenures.gm_username` is `varchar(50)`), so a blind shrink risks truncating production history. No enforced FK because `gm_username` is not unique. A future shrink can land once a production-data length audit confirms the max.

