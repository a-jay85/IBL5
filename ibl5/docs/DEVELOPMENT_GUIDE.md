# Development Guide

**Status:** 30/30 IBL modules refactored (100% complete) ✅ • 2892 tests • ~80% coverage • Goal: 80%

> 📘 **Progressive Loading:** Detailed workflows are in `.claude/rules/` and `.github/skills/`. See [SKILLS_GUIDE.md](.github/SKILLS_GUIDE.md).

---

## Current Priorities

### 🎯 All Modules Refactored ✅

### 🚀 Post-Refactoring Phase

1. **Test Coverage → 80%** - ✅ Goal achieved with 2892 tests (~80% coverage). Comprehensive edge case testing complete.

   **Priority Integration Tests:** ✅ All Complete
   - ~~Waivers, DepthChart, RookieOption, Schedule, Standings, Voting~~

   **Unit Test Gaps:** ✅ All Closed (Jan 26, 2026)
   - ~~Discord, Shared, League, Injuries, Standings~~ - Added 133 tests

   **Edge Case Testing:** ✅ All Complete (Jan 26, 2026)
   - ~~TradeValidator, FreeAgencyOfferValidator, WaiversValidator, DraftValidator, CommonContractValidator~~ - Added 183 tests
   - ~~PlayerInjuryCalculator, PlayerContractCalculator, NegotiationDemandCalculator, TeamStatsCalculator~~ - Added 127 tests

2. **API Development** - REST API with JWT, rate limiting, OpenAPI docs
3. **Security Hardening** - XSS audit, CSRF, security headers

---

## Recent Updates

### StandingsUpdater: Database-Driven Computation (Feb 14, 2026)

**Impact:** Replaced HTML file parsing with database-driven standings computation, bringing total from 2164 to 2892 tests

**Changes:**
- `StandingsUpdater` now computes standings from `ibl_schedule` game results instead of parsing `Standings.htm`
- Conference/division mappings read from `ibl_league_config` (per-season)
- Replaced `CommonMysqliRepository` dependency with `Season` injection
- Removed 7 HTML parsing methods, added 6 DB computation methods
- Magic number / clinch logic unchanged

**Test Coverage:**
- StandingsUpdaterTest: 18 tests (total W/L, home/away splits, conf/div records, pct, GB, edge cases)

**Status:** All 2892 tests passing ✅

---

### Calculator Edge Case Tests Added (Jan 26, 2026)

**Impact:** Added 127 edge case tests for 4 calculator classes, bringing total from 1935 to 2164 tests (~80% coverage)

**New Test Files:**
- **PlayerInjuryCalculatorEdgeCaseTest** (26 tests) - Negative days, year/month boundaries, leap years
- **PlayerContractCalculatorEdgeCaseTest** (37 tests) - Contract year boundaries, buyout rounding, null handling
- **NegotiationDemandCalculatorEdgeCaseTest** (25 tests) - Zero/near-zero modifiers, extreme preferences, division safety
- **TeamStatsCalculatorEdgeCaseTest** (39 tests) - Ranking score precision, games back, streak tracking

**Edge Cases Covered:**
- Date calculations: leap years, month/year boundaries, multi-year injuries
- Division safety: zero modifiers, zero total games, missing team factors
- Boundary values: contract years 0-7+, minimum/maximum preferences
- Floating-point precision: ranking score rounding, games back calculations
- Null/missing data: null preferences, missing game fields

**Status:** All 2164 tests passing ✅ • 80% coverage goal achieved

---

### Validator Edge Case Tests Added (Jan 26, 2026)

**Impact:** Added 183 edge case tests for 5 high-priority validators, bringing total from 1754 to 1935 tests (~79% coverage)

**New Test Files:**
- **TradeValidatorEdgeCaseTest** (32 tests) - Salary cap boundaries, cash minimums, player tradability
- **FreeAgencyOfferValidatorEdgeCaseTest** (31 tests) - Bird rights, hard/soft cap, MLE/LLE type coercion
- **WaiversValidatorEdgeCaseTest** (36 tests) - Roster slot boundaries, salary limits, player ID handling
- **DraftValidatorEdgeCaseTest** (40 tests) - Whitespace, special characters, unicode, length limits
- **CommonContractValidatorEdgeCaseTest** (44 tests) - Raise percentages, bird years boundaries, gaps

**Edge Cases Covered:**
- Boundary conditions: exactly at cap, one over cap, exactly at raise limits
- Null/empty handling: missing keys, empty arrays, null values
- Type coercion: string vs int for MLE/LLE flags ("0"/"1" vs 0/1)
- Special characters: apostrophes, hyphens, unicode (Chinese, Cyrillic, emoji)
- Large values: very long names, large salary amounts

**Brittle Tests Fixed:**
- Refactored 5 CSS implementation-detail tests to check behavior (design system classes) instead
- Removed inline CSS checks that were invalidated by design system refactoring

**Status:** All 1935 tests passing ✅

---

### Unit Test Gaps Closed (Jan 26, 2026)

**Impact:** Added 133 tests across 5 modules that had testing gaps, bringing total from 1621 to 1754 tests (~78% coverage)

**New Test Files:**
- **SalaryConverterTest** (14 tests) - Full coverage for previously untested utility class
- **DiscordIntegrationTest** (29 tests) - Config loading, database queries, message formatting
- **InjuriesIntegrationTest** (28 tests) - XSS protection, HTML structure, rendering edge cases
- **StandingsIntegrationTest** (26 tests) - Region validation, clinching indicators, streak display
- **LeagueContextIntegrationTest** (36 tests) - Constants, config structure, module lists

**Coverage Added:**
- SalaryConverter: `convertToMillions()` with edge cases (zero, vet min, max contracts)
- Discord: `getDiscordIDFromTeamname()` queries, `postToChannel()` message processing
- Injuries: XSS protection for all fields, HTML structure validation
- Standings: Clinching indicator priority (Z > Y > X), Pythagorean stats
- League: IBL-only modules list verification, setLeague/getCurrentLeague edge cases

**Status:** Unit test gaps closed for all 5 priority modules

---

### Schedule Integration Tests Added (Jan 25, 2026)

**Impact:** Added 30 integration test methods for complete team schedule display workflow coverage

**Integration Test Coverage:**
- **Schedule Integration:** ScheduleIntegrationTest (30 test methods)
  - Repository tests: Query generation, result iteration, date ordering, empty result handling
  - Win/loss tracking: Cumulative wins/losses, mixed results across season
  - Streak calculation: Win streaks, loss streaks, streak reset on opposite result
  - Unplayed game detection: Identifies games with equal scores as unplayed
  - Next-sim highlighting: Games within projected next sim period highlighted
  - Month grouping: Games grouped by month with proper headers
  - Opponent identification: Home vs away game prefix (@ vs vs)
  - Color coding: Wins green, losses red
  - View rendering: HTML output with team colors, XSS protection, result formatting

**Test Categories:**
- Repository tests (5 tests)
- Win/loss tracking (3 tests)
- Streak calculation (3 tests)
- Unplayed game handling (2 tests)
- Next-sim highlighting (3 tests)
- Month and opponent identification (3 tests)
- Color coding (2 tests)
- View rendering (7 tests)
- Complete workflow (2 tests)

**Status:** All 1591 tests passing ✅ Schedule module now has comprehensive integration test coverage

---

### Standings Integration Tests Added (Jan 25, 2026)

**Impact:** Added 35 integration test methods for complete standings display workflow coverage

**Integration Test Coverage:**
- **Standings Integration:** StandingsIntegrationTest (35 test methods)
  - Repository tests: Conference/division standings queries, region validation
  - Streak data: Power table queries, last 10 record, streak type/count
  - Pythagorean stats: Points scored/allowed calculation from offense/defense stats
  - Clinched indicators: Z (conference), Y (division), X (playoffs) with priority logic
  - View rendering: Region titles, table headers, team data display
  - XSS protection: Team name escaping, streak type sanitization
  - Team links: Correct URL generation, team logo display
  - Full page render: All regions included, styles output once, responsive classes

**Test Categories:**
- Repository conference standings (3 tests)
- Repository division standings (3 tests)
- Streak data handling (3 tests)
- Pythagorean stats calculation (3 tests)
- View region rendering (4 tests)
- Clinched indicator display (5 tests)
- Pythagorean column display (2 tests)
- Streak/rating display (3 tests)
- XSS security (2 tests)
- Team link/logo tests (2 tests)
- Full render tests (3 tests)
- Complete workflow tests (2 tests)

**Status:** All 1621 tests passing ✅ Standings module now has comprehensive integration test coverage

---

### Voting Integration Tests Added (Jan 25, 2026)

**Impact:** Added 30 integration test methods for complete voting results display workflow coverage

**Integration Test Coverage:**
- **Voting Integration:** VotingIntegrationTest (30 test methods)
  - Service tests: ASG/EOY table queries, vote aggregation, weighted scoring
  - All-Star voting: Eastern/Western Conference Frontcourt/Backcourt categories
  - End-of-Year voting: MVP, Sixth Man, Rookie of Year, GM of Year with weighted points
  - Blank ballot handling: Empty names get special label, whitespace trimmed
  - Renderer tests: Table structure, player/votes display, alternating row styles
  - XSS protection: Player names, titles, and quotes escaped
  - Controller routing: Phase-based routing (Regular Season vs Playoffs/Free Agency)
  - Explicit view methods: Bypass phase routing for direct category access
  - Complete workflows: Full voting display with multiple categories

**Test Categories:**
- Service All-Star tests (4 tests)
- Service End-of-Year tests (4 tests)
- Blank ballot handling (2 tests)
- Renderer output tests (6 tests)
- XSS security tests (3 tests)
- Controller routing tests (5 tests)
- Complete workflow tests (4 tests)
- Table styling tests (2 tests)

**Status:** All 1621 tests passing ✅ Voting module now has comprehensive integration test coverage

---

### RookieOption Integration Tests Added (Jan 25, 2026)

**Impact:** Added 20 integration test methods for complete rookie option exercise workflow coverage

**Integration Test Coverage:**
- **RookieOption Integration:** RookieOptionIntegrationTest (20 test methods)
  - First round pick workflows: Regular Season and Free Agency phases
  - Second round pick workflows: Regular Season and Free Agency phases
  - Ownership validation: Player must be on requesting team
  - Eligibility validation: canRookieOption checks, final year salary requirements
  - Database operations: cy4 updates for round 1, cy3 updates for round 2
  - Complete workflow tests: Ownership → Eligibility → Database update
  - Edge cases: Minimum/maximum extension amounts, different season phases

**Test Categories:**
- First round pick success scenarios (2 tests)
- Second round pick success scenarios (2 tests)
- Ownership validation failures (2 tests)
- Eligibility validation failures (3 tests)
- Database operations (3 tests)
- Complete workflow tests (4 tests)
- Edge cases (4 tests)

**Status:** RookieOption module now has comprehensive integration test coverage

---

### DepthChart Integration Tests Added (Jan 25, 2026)

**Impact:** Added 22 integration test methods for complete depth chart submission workflow coverage

**Integration Test Coverage:**
- **DepthChart Integration:** DepthChartIntegrationTest (22 test methods)
  - Complete submission workflow: Processing → Validation → Database updates → CSV export
  - Validation failures: Insufficient active players, position depth requirements, multiple starting positions
  - Season phase rules: Regular Season (12 active, 3 per position) vs Playoffs (10-12 active, 2 per position)
  - Database operations: Player updates, team history timestamps, query verification
  - Input sanitization: HTML stripping, value boundary clamping, negative intensity handling
  - Error handling: Error accumulation, cross-validation clearing, HTML error formatting

**Test Categories:**
- Submission workflow success (3 tests)
- Validation failure scenarios (5 tests)
- Playoffs vs Regular Season rules (4 tests)
- Database operations (4 tests)
- Input sanitization (3 tests)
- Error handling (3 tests)

**Status:** DepthChart module now has comprehensive integration test coverage following IntegrationTestCase pattern

---

### Waivers Integration Tests Added (Jan 25, 2026)

**Impact:** Added 25 integration test methods for complete waiver wire workflow coverage

**Integration Test Coverage:**
- **Waivers Integration:** WaiversIntegrationTest (25 test methods)
  - Drop to waivers: Success scenarios, validation failures, database error handling
  - Add from waivers: Existing contract preservation, veteran minimum assignment
  - Validation: Cap violations (hard cap limits), roster slot constraints, player ID validation
  - Contract determination: Existing contract detection, Free Agency phase handling
  - Waiver timing: 24-hour wait period calculations, countdown formatting
  - Veteran minimum: Salary calculation based on experience

**Test Categories:**
- Drop success scenarios (3 tests)
- Drop failure scenarios (2 tests)
- Add success scenarios (4 tests)
- Add failure scenarios (5 tests)
- Contract determination (3 tests)
- Waiver wait time (4 tests)
- Veteran minimum calculation (2 tests)
- Validator error handling (2 tests)

**Status:** All tests passing ✅ Waivers module now has comprehensive integration test coverage following TestDataFactory pattern

---

### Integration Tests Added (Jan 12, 2026 - PR #159)

**Impact:** Added 38 integration test methods across 5 critical workflow suites, with refactored test infrastructure using TestDataFactory pattern

**Integration Test Coverage:**
- **Draft Integration:** DraftIntegrationTest (6 test methods) - Player creation, pick ownership, validation failures
- **Extension Integration:** ExtensionIntegrationTest (12 test methods) - Extension offers, CBA validation, player preferences, Bird rights
- **FreeAgency Integration:** FreeAgencyIntegrationTest (7 test methods) - Custom/MLE/LLE/VetMin offers, cap space validation, offer deletion
- **Negotiation Integration:** NegotiationIntegrationTest (4 test methods) - Demand calculation, cap space, eligibility checks
- **Trading Integration:** TradeIntegrationTest (9 test methods) - Player/pick/cash trades, news stories, cleanup workflows

**Test Infrastructure Improvements:**
- Created standalone TestDataFactory class in Tests\Integration\Mocks\ namespace for centralized fixture creation
- IntegrationTestCase base class provides transaction rollback, mock database, and helper assertions
- All integration tests use TestDataFactory::createPlayer/createTeam/createSeason static methods
- Refactored mock classes from inline definitions to proper namespaced classes in tests/Integration/Mocks/
- Enhanced autoloader.php to support Tests\ namespace
- All tests use @covers annotations for accurate coverage measurement

**Status:** Integration infrastructure complete with TestDataFactory pattern enabling consistent fixture creation across all integration test suites

---

### PR #158 - Comprehensive Test Coverage Expansion (Jan 10, 2026)

**Impact:** Added 365 new tests across 40+ test files, bringing total from 1060→1425 tests (34% increase)

**Test Coverage Additions:**
- Core models: BoxscoreTest (28), DraftPickTest (9), GameTest (11), LeagueTest (15), SeasonTest (14), TeamTest (16)
- Contract rules: ContractRulesTest (42 CBA salary cap tests)
- Module processors: FreeAgency (5), Negotiation (5), Extension (4), Trading (4)
- Controllers: Waivers (4), DepthChart (3+5), RookieOption (3), Team (3), SeriesRecords (4)
- Repositories: Trading (10), FreeAgency (7), Negotiation (7), SeasonLeaderboards (4), SeriesRecords (4), OneOnOne (4)
- Services: CommonValidator (6), PlayerDataConverter (13), ExtensionOfferEvaluator (17), CashTransactionHandler (11)
- Display modules: DraftPickLocator (112), Injuries (111), LeagueStarters (129), NextSim (96), TeamSchedule (96+9+186)
- Utilities: DateParser (15), UuidGenerator (11), RecordParser, ScheduleParser, SeasonPhaseHelper, StandingsGrouper
- Views: Draft (12), Waivers (9), Statistics (6), TeamSchedule (9)

**New Utility Classes:**
- TeamStatsCalculator (181 lines) - Centralized team statistics calculations
- DateParser (79 lines) - Date/time parsing utilities
- RecordParser (44 lines) - Win/loss record parsing
- ScheduleParser, SeasonPhaseHelper, StandingsGrouper

**Infrastructure Improvements:**
- Added `phpunit.ci.xml` for CI/CD optimization
- Enhanced `phpunit.xml` with comprehensive test suite definitions
- Created IntegrationTestCase base class (208 lines) for database testing with transaction rollback
- Refactored PowerRankingsUpdater, ScheduleUpdater, StandingsUpdater to use new utilities

**Impact:** Net +7,627 insertions, -476 deletions across 65 files

---

## Quick Reference

| Action | Command/Location |
|--------|------------------|
| Run tests | `cd ibl5 && vendor/bin/phpunit tests/` |
| Schema reference | `ibl5/schema.sql` |
| CI/CD | `.github/workflows/tests.yml` |
| Interface examples | `classes/Player/Contracts/`, `classes/FreeAgency/Contracts/` |
| Stats formatting | `BasketballStats\StatsFormatter` |
| XSS protection | `Utilities\HtmlSanitizer::safeHtmlOutput()` |

---

## Completed Modules (30/30) ✅

**Core Modules (22):** Player • Statistics • Team • Draft • Waivers • Extension • RookieOption • Trading • Negotiation • DepthChart • Voting • Schedule • Season Leaders • Free Agency • PlayerDatabase • Compare_Players • Leaderboards • Standings • League_Stats • Player_Awards • Series_Records • One-on-One

**Display Modules (8):** CapSpace • Draft_Pick_Locator • Franchise_History • Injuries • League_Starters • Next_Sim • Power_Rankings • Team_Schedule

---

## Skills Architecture

**Path-Conditional** (`.claude/rules/`): Auto-load when editing matching files
- `php-classes.md` → `ibl5/classes/**/*.php`
- `phpunit-tests.md` → `ibl5/tests/**/*.php`
- `view-rendering.md` → `**/*View.php`

**Task-Discovery** (`.github/skills/`): Auto-load when task matches
- `refactoring-workflow/` - Module refactoring with templates
- `security-audit/` - XSS/SQL injection patterns
- `phpunit-testing/` - Test patterns and mocking
- `basketball-stats/` - StatsFormatter usage
- `code-review/` - PR validation checklist

---

## Resources

| Document | Purpose |
|----------|---------|
| [DATABASE_GUIDE.md](DATABASE_GUIDE.md) | Schema, indexes, views |
| [API_GUIDE.md](API_GUIDE.md) | REST API development |
| [SKILLS_GUIDE.md](.github/SKILLS_GUIDE.md) | Skills creation guide |
| [ibl5/docs/archive/](ibl5/docs/archive/) | Archived detailed workflows |

---

## FAQs

**Run tests?** `cd ibl5 && vendor/bin/phpunit tests/`  
**Database changes?** Use `ibl5/migrations/` - never modify schema directly  
**XSS protection?** `Utilities\HtmlSanitizer::safeHtmlOutput()` on all output  
**Stats formatting?** `BasketballStats\StatsFormatter` - never `number_format()`
