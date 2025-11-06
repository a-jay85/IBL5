# IBL5 Development Guide

**Last Updated:** November 6, 2025

## Quick Start

### Current State
- **Modules:** 63 total (12 fully refactored, 51 awaiting)
- **Tests:** ~350 tests, 30% coverage
- **Goal:** 80%+ coverage of critical features

### Top Priorities
1. **Free Agency Module** (1,648 lines) - Contract signing, salary cap
2. **Player Display Module** (749 lines) - Most viewed page
3. **Statistics Module** (513 lines) - Core feature

## Development Workflow

### Before Starting
1. Review existing refactored modules (Waivers, Draft, Team, Player)
2. Check `schema.sql` for database structure
3. Run existing tests to understand current state
4. Read `COPILOT_AGENT.md` for coding standards

### Refactoring Process
1. **Analyze** (1-2 days): Read code, identify responsibilities
2. **Design** (1-2 days): Sketch class structure, plan tests
3. **Extract Classes** (1-2 weeks): Repository → Validator → Processor → View → Controller
4. **Write Tests** (1 week): Unit + integration tests
5. **Security Audit** (2-3 days): SQL injection, XSS prevention
6. **Review** (2-3 days): Code review, performance testing

### Class Structure Pattern
```
Module/
├── Repository.php      - Database operations
├── Validator.php       - Business rule validation
├── Processor.php       - Core business logic
├── View.php           - UI rendering
└── Controller.php     - Request orchestration
```

## Module Status Matrix

### ✅ Completed Modules
- Waivers (50 tests, 93% reduction)
- Draft (25 tests, 78% reduction)
- Team (22 tests, 91% reduction)
- Player (30 tests, facade pattern)
- Extension (50+ tests)
- Trading (44 tests)
- Depth Chart (13 tests, 85% reduction)
- Voting (7 tests)
- Rookie Option (13 tests, 82% reduction)

### 🔴⚠️ High Priority (Not Started)
- Free Agency (1,648 lines) - #1 Priority
- Player Display (749 lines) - #2 Priority
- Statistics (513 lines) - #3 Priority
- Chunk Stats (462 lines) - #4 Priority
- Player Search (461 lines) - #4 Priority
- Compare Players (408 lines) - #5 Priority

## Testing Standards

### Coverage Goals
- **Current:** 30% coverage
- **Phase 1 Target:** 60% (Top 3 modules done)
- **Phase 2 Target:** 75% (Top 5 modules done)
- **Long-term:** 80%+ coverage

### Test Pyramid
```
     /E2E\        ← Few (critical flows)
    /------\
   /Integr \     ← Some (database ops)
  /----------\
 /   Unit     \  ← Many (business logic)
```

### What to Test
- All public methods
- Edge cases and error conditions
- Business rule validation
- Database operations (with real schema)
- Security (SQL injection, XSS)

## Code Quality Standards

### Required Type Hints
All functions must have complete type hints:
```php
// ✅ Good
public function getPlayer(int $playerId): ?Player
public function calculateAverage(array $values): float
public function logEvent(string $message, ?string $userId = null): void

// ❌ Bad
public function getPlayer($playerId)
```

### Class Autoloader
- Place all classes in `ibl5/classes/` directory
- Class filename must match class name
- Never use `require()` for classes - autoloader handles it
- Just reference: `$player = new Player($db);`

### Security Checklist
- [ ] SQL injection prevention (prepared statements)
- [ ] XSS prevention (HTML escaping)
- [ ] Input validation (type checking)
- [ ] Authorization checks
- [ ] CSRF protection where applicable

## Performance Best Practices

### Database Queries
- Use prepared statements
- Leverage existing indexes
- Use database views for complex queries
- Batch operations when possible
- Reference `DATABASE_GUIDE.md` for schema

### Code Optimization
- Extract reusable logic to repositories
- Use existing formatters (StatsFormatter, StatsSanitizer)
- Avoid N+1 queries
- Cache expensive operations

## Architecture Patterns

### Repository Pattern (Database)
```php
class PlayerRepository {
    public function findById(int $id): ?array
    public function findByTeam(int $teamId): array
    public function save(array $data): int
}
```

### Validator Pattern (Business Rules)
```php
class ContractValidator {
    public function validateSalaryCap(array $contract): array
    public function validateRosterSpace(int $teamId): array
}
```

### Processor Pattern (Business Logic)
```php
class FreeAgencyProcessor {
    public function processOffer(array $offer): bool
    public function calculateCapHit(array $contract): float
}
```

## Migration Path

### Phase 1: Foundation (Months 1-3)
- Free Agency, Player Display, Statistics
- Target: 60% test coverage
- Focus: Business-critical features

### Phase 2: Expansion (Months 4-6)
- Data operations (Chunk Stats, Player Search, Compare Players)
- Developer tools (Docker, PHPStan, CodeSniffer)
- Target: 75% test coverage

### Phase 3: Advanced (Months 7-9)
- RESTful API development
- Event system for decoupling
- Configuration management
- Target: 80%+ test coverage

## Success Metrics

### Code Quality
- Average entry point < 100 lines
- Test coverage > 60% (Phase 1)
- All critical modules have 30+ tests
- Zero high-severity security issues

### Developer Productivity
- Add new feature in < 1 day
- Fix bug in < 1 hour
- New developer productive in < 1 week
- Test suite runs in < 5 minutes

### Business Impact
- 70% reduction in production bugs
- 2x feature velocity increase
- Weekly deployment capability

## Common Patterns to Follow

### Free Agency Example (Priority #1)
Expected structure after refactoring:
```
modules/Free_Agency/
├── index.php (150 lines, down from 1,648)
└── classes/FreeAgency/
    ├── FreeAgencyRepository.php
    ├── FreeAgencyValidator.php
    ├── FreeAgencyProcessor.php
    ├── FreeAgencyView.php
    └── FreeAgencyController.php
tests/FreeAgency/
├── FreeAgencyRepositoryTest.php
├── FreeAgencyValidatorTest.php
├── FreeAgencyProcessorTest.php
└── FreeAgencyIntegrationTest.php
```

### Statistics Formatting
Use existing formatters instead of inline calculations:
```php
// ✅ Good
$fgPct = StatsFormatter::formatPercentage($fgm, $fga);
$ppg = StatsFormatter::formatPerGameAverage($points, $games);

// ❌ Bad
$fgPct = ($fga) ? number_format($fgm / $fga, 3) : "0.000";
```

## Developer Experience Tools

### Recommended Setup
```bash
# Install quality tools
composer require --dev phpstan/phpstan
composer require --dev squizlabs/php_codesniffer

# Run tests
cd ibl5 && vendor/bin/phpunit tests/

# Check code style
vendor/bin/phpcs --standard=PSR12 ibl5/classes/
```

### Pre-commit Checklist
- [ ] All tests pass
- [ ] No linter warnings
- [ ] Type hints complete
- [ ] Security reviewed
- [ ] Documentation updated

## Resources

### Documentation
- Database: `DATABASE_GUIDE.md`
- API Development: `API_GUIDE.md`
- Copilot Instructions: `COPILOT_AGENT.md`
- Statistics Formatting: `STATISTICS_FORMATTING_GUIDE.md`
- Production Deployment: `PRODUCTION_DEPLOYMENT_GUIDE.md`

### Code Examples
- Best refactoring: Waivers module (`tests/Waivers/`)
- Facade pattern: Player class (`classes/Player/`)
- MVC pattern: Team module (`classes/Team/`)
- Security: Depth Chart (`classes/DepthChart/SECURITY.md`)

### Testing Examples
- Unit tests: `tests/Waivers/WaiversValidatorTest.php`
- Integration tests: `tests/Trading/TradingIntegrationTest.php`
- Mock database: `tests/MockDatabase.php`

## FAQs

**Q: Should I refactor everything at once?**  
A: No! Focus on priorities 1-3 first for maximum business value.

**Q: What if I find bugs during refactoring?**  
A: Fix them! Write tests first to ensure they stay fixed.

**Q: How much should I test?**  
A: Aim for 80%+ coverage per module. Test all public methods and edge cases.

**Q: Can I change existing APIs?**  
A: Only if necessary. Maintain backward compatibility when possible.

**Q: How do I handle database changes?**  
A: Use migrations in `ibl5/migrations/`. Never modify schema directly.

## Getting Started

1. Read this guide and `COPILOT_AGENT.md`
2. Review Waivers module as example
3. Set up development environment
4. Choose Free Agency module (Priority #1)
5. Create feature branch
6. Follow refactoring process above
7. Write tests as you go
8. Get code review before merging

**Remember:** Focus on testable, maintainable, extensible code. Business value and developer experience matter most.
