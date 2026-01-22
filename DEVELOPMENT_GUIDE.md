# Development Guide

**Status:** 30/30 IBL modules refactored (100% complete) ✅ • 1444 tests • ~68% coverage • Goal: 80%

> 📘 **Progressive Loading:** Detailed workflows are in `.claude/rules/` and `.github/skills/`. See [SKILLS_GUIDE.md](.github/SKILLS_GUIDE.md).

---

## Current Priorities

### 🎯 All Modules Refactored ✅

### 🚀 Post-Refactoring Phase

1. **Test Coverage → 80%** - Strong progress with 1444 tests (~68% coverage). PR #158 added 365 unit tests, PR #159 added 38 integration test methods across 5 critical workflows. **Next Steps:** Add 15-20 integration tests for high-priority user workflows (Waivers, DepthChart, RookieOption) and expand edge case coverage in existing modules to reach 80% goal.

   **Priority Integration Tests Needed:**
   - **HIGH**: Waivers (add/drop workflow with cap validation, waiver wire timing)
   - **MEDIUM**: DepthChart (submission with position validation, injured player handling)
   - **MEDIUM**: RookieOption (option exercise with eligibility checks, contract updates)
   - **MEDIUM**: Standings/Schedule (calculation accuracy, tie-breaking logic)
   - **LOW**: Voting (All-star/awards voting submission)

   **Unit Test Gaps:** Discord (1 test), Shared (1 test), League (1 test), Injuries (2 tests), Standings (2 tests)

2. **API Development** - REST API with JWT, rate limiting, OpenAPI docs
3. **Security Hardening** - XSS audit, CSRF, security headers

---

## Laravel Auth Migration (Jan 2026)

### Overview
PHP-Nuke authentication is being replaced with Laravel Auth + bcrypt passwords. The migration uses a bridge pattern allowing both systems to operate during transition.

### Key Components

| Component | Location | Purpose |
|-----------|----------|---------|
| LaravelAuthBridge | `classes/Auth/LaravelAuthBridge.php` | Main auth facade |
| User Model | `classes/Auth/User.php` | User entity with roles |
| ModuleMiddleware | `classes/Middleware/ModuleMiddleware.php` | Module access control |
| UserMigrationService | `classes/Auth/UserMigrationService.php` | Nuke→Laravel sync |
| NukeAuthCompat | `classes/Auth/NukeAuthCompat.php` | Legacy function wrapper |

### User Roles
- `spectator` - Read-only access
- `owner` - Team owner (can manage assigned teams)
- `commissioner` - Full admin access (manages all teams)

### Password Migration
- Existing MD5 passwords in `legacy_password` column
- On first login: verify MD5 → rehash to bcrypt → clear legacy_password
- `migrated_at` timestamp tracks migration completion

### Migration Commands
```bash
# Run database migration
php ibl5/migrations/run.php

# Sync users from nuke_users (dry run)
# Add UserMigrationService call to scripts as needed

# Cutover (forces all users to re-login)
php ibl5/scripts/migration-cutover.php --dry-run
php ibl5/scripts/migration-cutover.php --force
```

### Mobile-First Theme
- Tailwind CSS in `themes/IBL/input.css`
- Build: `cd ibl5 && npm run build:css`
- Alpine.js for mobile menu interactivity
- Responsive grid: mobile → tablet (2-col) → desktop (3-col)

---

## Recent Updates

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

**Status:** All 1444 tests passing ✅ Integration infrastructure complete with TestDataFactory pattern enabling consistent fixture creation across all integration test suites

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
