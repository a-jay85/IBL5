# Development Guide

**Status:** 30/30 IBL modules refactored (100% complete) ✅ • 1591 tests • ~75% coverage • Goal: 80%

> 📘 **Progressive Loading:** Detailed workflows are in `.claude/rules/` and `.github/skills/`. See [SKILLS_GUIDE.md](.github/SKILLS_GUIDE.md).

---

## Current Priorities

### 🎯 All Modules Refactored ✅

### 🚀 Post-Refactoring Phase

1. **Test Coverage → 80%** - Strong progress with 1591 tests (~75% coverage). PR #158 added 365 unit tests, PR #159 added 38 integration test methods across 5 critical workflows. Waivers, DepthChart, RookieOption, Schedule, and Standings integration tests completed. **Next Steps:** Expand edge case coverage in existing modules to reach 80% goal.

   **Priority Integration Tests Needed:**
   - ~~**HIGH**: Waivers (add/drop workflow with cap validation, waiver wire timing)~~ ✅ Complete
   - ~~**MEDIUM**: DepthChart (submission with position validation, injured player handling)~~ ✅ Complete
   - ~~**MEDIUM**: RookieOption (option exercise with eligibility checks, contract updates)~~ ✅ Complete
   - ~~**MEDIUM**: Schedule (win/loss tracking, streak calculation, next-sim highlighting)~~ ✅ Complete
   - ~~**MEDIUM**: Standings (calculation accuracy, tie-breaking logic)~~ ✅ Complete
   - **LOW**: Voting (All-star/awards voting submission)

   **Unit Test Gaps:** Discord (1 test), Shared (1 test), League (1 test), Injuries (2 tests), Standings (2 tests)

2. **API Development** - REST API with JWT, rate limiting, OpenAPI docs
3. **Security Hardening** - XSS audit, CSRF, security headers

---

## Recent Updates

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

**Status:** All 1591 tests passing ✅ Standings module now has comprehensive integration test coverage

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
- Repositories: Trading (10), FreeAgency (7), Negotiation (7), SeasonLeaders (4), SeriesRecords (4), OneOnOne (4)
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

**Core Modules (22):** Player • Statistics • Team • Draft • Waivers • Extension • RookieOption • Trading • Negotiation • DepthChart • Voting • Schedule • Season Leaders • Free Agency • Player_Search • Compare_Players • Leaderboards • Standings • League_Stats • Player_Awards • Series_Records • One-on-One

**Display Modules (8):** Cap_Info • Draft_Pick_Locator • Franchise_History • Injuries • League_Starters • Next_Sim • Power_Rankings • Team_Schedule

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
