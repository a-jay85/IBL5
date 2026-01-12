# Development Guide

**Status:** 30/30 IBL modules refactored (100% complete) ✅ • 1444+ tests • ~68% coverage • Goal: 80%

> 📘 **Progressive Loading:** Detailed workflows are in `.claude/rules/` and `.github/skills/`. See [SKILLS_GUIDE.md](.github/SKILLS_GUIDE.md).

---

## Current Priorities

### 🎯 All Modules Refactored ✅

### 🚀 Post-Refactoring Phase

1. **Test Coverage → 80%** - Progressing well with PR #158 (+365 unit tests) + integration tests (52+ integration tests). Continue expanding edge case coverage and achieving 80% threshold. **Next Steps:** Add 50+ more integration tests focusing on edge cases, error conditions, and multi-module workflows to reach 80% coverage goal.
2. **API Development** - REST API with JWT, rate limiting, OpenAPI docs
3. **Security Hardening** - XSS audit, CSRF, security headers

---

## Recent Updates

### Integration Tests Added (Jan 12, 2026)

**Impact:** Added 52 integration tests across 5 critical workflow directories, with refactored test infrastructure using TestDataFactory pattern

**Integration Test Coverage:**
- Draft Integration: DraftIntegrationTest (6 tests)
- Extension Integration: ExtensionIntegrationTest (12 tests) ✅ All passing
- FreeAgency Integration: FreeAgencyIntegrationTest (7 tests) ✅ All passing
- Negotiation Integration: NegotiationIntegrationTest (4 tests)
- Trading Integration: TradeIntegrationTest (9 tests)

**Test Infrastructure Improvements:**
- Created TestDataFactory pattern in IntegrationTestCase for reusable mock data setup
- Added 7 new fixture helper methods (setupMockFreeAgentOffer, setupMockTradeScenario, etc.)
- Refactored mock classes from inline definitions to proper namespaced classes in tests/Integration/Mocks/
- Enhanced autoloader.php to support Tests\ namespace
- All tests use @covers annotations for accurate coverage measurement

**Total Test Count:** 1425 → 1477 (Jan 12 master), currently 1444 after TestDataFactory refactoring on current branch

**Status:** 12+ tests passing, integration infrastructure in place, remaining tests require edge case mock data refinement

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
