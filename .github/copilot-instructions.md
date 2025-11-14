# Copilot Coding Agent Instructions for IBL5

## Overview
This repository uses the Copilot coding agent to automate code changes, improvements, and maintenance. Please follow these best practices to ensure smooth collaboration and effective automation.

## Engineering Philosophy

### Operate as an Architect-Level Engineer
- Approach every change with architectural thinking and long-term maintainability in mind
- Consider the broader system impact of all changes
- Write code that is clear, readable, maintainable, and extensible
- Prioritize code quality over speed of delivery
- Think about future developers who will work with this code

### Pull Request Standards
- **Clarity**: PRs must be easy to understand at a glance
- **Readability**: Code changes should follow consistent patterns and naming conventions
- **Maintainability**: Avoid clever code; prefer explicit, self-documenting approaches
- **Extensibility**: Design changes to accommodate future requirements
- **Completeness**: All tests must pass without warnings or failures before a PR is considered complete

## Codebase Architecture

### Technology Stack & Migration Goals
- **Current Foundation**: Built on PHP-Nuke legacy framework
- **Migration Target**: Gradually migrate to Laravel and/or TypeScript/Svelte/Vite stack
- **Database Current**: MySQL
- **Database Future**: Support PostgreSQL and implement ORM layer
- When refactoring, consider compatibility with future Laravel migration
- Write new code with modern PHP practices (namespaces, type hints, etc.)

### Key Directory Structure
```
ibl5/
├── classes/          # All class files (PSR-4 autoloaded)
├── modules/          # Feature modules (PHP-Nuke style)
├── tests/            # PHPUnit test suite (v12.4+)
└── mainfile.php      # Bootstrap file with class autoloader
```

### ⚠️ CRITICAL: Class Autoloading (READ THIS FIRST)

**The codebase has a functional class autoloader - DO NOT write `require()` or `require_once()` statements for classes!**

#### How the Autoloader Works
- **Location**: The class autoloader is defined in `mainfile.php:216-248`
- **Automatic Loading**: Classes are automatically loaded when referenced by name
- **Entry Points**: `mainfile.php` is included in all PHP files that need class access
- **No Manual Requires**: You should NEVER write `require()` or `require_once()` for class files

#### Autoloader Rules - MUST FOLLOW
1. **All classes MUST be placed in `ibl5/classes/` directory**
2. **Class filenames MUST match the class name** (e.g., `Player.php` for `Player` class)
3. **Simply reference classes by name** - the autoloader handles the rest
4. **Use proper namespacing** for new classes to facilitate future Laravel migration
5. **Follow PSR-4 conventions** when creating new classes

#### Correct Usage Examples
```php
// ✅ CORRECT - Just use the class name
$player = new Player($db);
$team = Team::findById($teamId);
$draft = new Draft($db);

// ❌ WRONG - Do not write require statements
require_once 'classes/Player.php';  // NEVER DO THIS
$player = new Player($db);
```

#### Module Entry Points
- Modules have access to the autoloader via `require_once('mainfile.php')` in their entry point files
- Once `mainfile.php` is included, all classes in `ibl5/classes/` are available
- No additional require statements needed for class files

### Testing Requirements
- **Framework**: PHPUnit 12.4+ compatibility required
- **Test Location**: All tests in `ibl5/tests/` directory
- **PR Completion Criteria**: No warnings or failures allowed
- Always run the full test suite before stopping work on a PR
- Add tests for new functionality
- Update tests when refactoring existing code
- Static production data (when available) is preferred over mock data
- Mock functionality should not be used unless absolutely necessary
- Instantiation of classes should be done via the class autoloader
- Do not write tests that only test mocks or instantiation
- **Schema Reference**: Use `ibl5/schema.sql` to understand table structures when creating test data

#### ⚠️ CRITICAL: Never Skip Tests - Remove Them Instead
**DO NOT use `$this->markTestSkipped()` to document removed tests.** Skipped tests:
- Create technical debt and confusion about what's actually being tested
- Clutter the test suite and make it harder to understand coverage
- May accidentally be re-enabled by future developers without understanding the reason

**Instead:**
1. **COMPLETELY DELETE the entire test method** if it no longer serves a purpose
2. **DOCUMENT the reason in related tests or code comments** if there's valuable context to preserve
3. **Update integration tests** if the behavior now requires end-to-end testing instead
4. **Never create placeholder tests** with `markTestSkipped()` - if a test doesn't run, it shouldn't exist

Example:
```php
// ❌ WRONG - Don't do this
public function testRemovedTest()
{
    $this->markTestSkipped('Removed following best practices');
}

// ✅ CORRECT - Either add the test back with proper implementation, or delete it entirely
// If deleting, consider documenting the reason in related tests or commit messages
```

#### Unit Test Quality Principles

**ALL tests MUST follow these principles from ["Stop Vibe Coding Your Unit Tests"](https://www.andy-gallagher.com/blog/stop-vibe-coding-your-unit-tests/):**

**✅ DO:**
- **Test behaviors through public APIs only** - Focus on observable outcomes
- **Use descriptive test names** that explain the behavior being tested
- **Keep assertions focused on "what" not "how"** - Test outcomes, not implementation
- **Test one behavior per test** - Each test should have a single, clear purpose
- **Use data providers** for similar test cases with different inputs
- **Verify success/failure of operations** - Not the internal mechanics
- **Test edge cases and error conditions** through public method returns

**❌ DON'T:**
- **NEVER use `ReflectionClass` to test private methods** - Private methods are implementation details
- **NEVER check SQL query structure** unless it's the actual behavior being tested (e.g., SQL injection prevention)
- **NEVER depend on internal implementation details** - Tests should survive refactoring
- **NEVER write redundant tests** that add no value beyond existing coverage
- **NEVER test multiple unrelated behaviors** in a single test
- **NEVER assert on method call counts** unless testing caching/memoization behavior

**Examples:**

```php
// ❌ BAD - Testing private method with reflection
public function testPrivateMethodLogic()
{
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('privateHelper');
    $method->setAccessible(true);
    $result = $method->invoke($this->service, $input);
    $this->assertEquals($expected, $result);
}

// ✅ GOOD - Testing behavior through public API
public function testServiceProcessesDataCorrectly()
{
    $result = $this->service->processData($input);
    $this->assertTrue($result->isValid());
    $this->assertEquals($expectedOutput, $result->getOutput());
}

// ❌ BAD - Checking SQL query structure
public function testUpdatePlayerContract()
{
    $this->repository->updateContract($playerId, $salary);
    $queries = $this->mockDb->getExecutedQueries();
    $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
    $this->assertStringContainsString('SET salary = 1000', $queries[0]);
    $this->assertStringContainsString('WHERE pid = 123', $queries[0]);
}

// ✅ GOOD - Testing operation success
public function testUpdatePlayerContractSucceeds()
{
    $result = $this->repository->updateContract($playerId, $salary);
    $this->assertTrue($result, 'Contract update should succeed');
    $this->assertEquals($salary, $this->repository->getPlayerSalary($playerId));
}
```

**Security Testing Exception:**
SQL query checking IS appropriate when testing security features:
```php
// ✅ GOOD - Testing SQL injection prevention
public function testEscapesUserInput()
{
    $maliciousInput = "'; DROP TABLE ibl_plr; --";
    $this->repository->findByName($maliciousInput);
    $queries = $this->mockDb->getExecutedQueries();
    $this->assertStringContainsString("\\'; DROP", $queries[0]);
}
```

**Reference:** See `ibl5/TEST_REFACTORING_SUMMARY.md` for complete refactoring history and additional examples.

### Database Schema & Considerations

#### Quick Reference
- **Schema Location**: `ibl5/schema.sql` (MariaDB 10.6.20 export)
- **Database Guide**: See `DATABASE_GUIDE.md` for complete schema documentation
- **Status**: InnoDB conversion complete (52 tables), foreign keys added (24 constraints), API-ready ✅

#### Essential Practices
- Use `ibl5/schema.sql` to understand table structures before writing queries
- Use prepared statements for all queries (security)
- Leverage existing indexes for WHERE/JOIN clauses
- Avoid MySQL-specific features for PostgreSQL compatibility (e.g., use INT not MEDIUMINT)
- Write SQL that can be converted to Eloquent ORM
- Place migrations in `ibl5/migrations/` directory

## Best Practices

### 1. View Rendering Pattern (Output Buffering)

**All view/presentation classes MUST use PHP output buffering instead of string concatenation.**

Output buffering provides better readability, maintainability, and makes the HTML structure explicit and visible:

#### Pattern
```php
public function renderExample(string $title): string
{
    ob_start();
    ?>
<div class="container">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Content here</p>
</div>
    <?php
    return ob_get_clean();
}
```

#### Key Rules
- **Always start with `ob_start()`** - Begin output buffering
- **Switch to template mode** - Use `?>` to exit PHP and write HTML directly
- **Use short echo tags** - `<?= ... ?>` for variables (replaces `<?php echo ... ?>`)
- **Escape output** - Use `htmlspecialchars()` on all user/dynamic data for XSS prevention
- **End with `<?php return ob_get_clean(); ?>`** - Capture and return buffered content

#### Benefits
- **Cleaner, more readable code** - HTML structure is immediately visible
- **Better maintainability** - No escaped quotes or `\n` escape sequences
- **Security** - Encourages proper output escaping with `htmlspecialchars()`
- **Semantic HTML** - Easy to write proper semantic elements
- **No performance impact** - Output buffering is efficient for this use case

#### ❌ DON'T - String Concatenation
```php
public function renderExample(string $title): string
{
    $html = "<div class=\"container\">";
    $html .= "<h1>$title</h1>";
    $html .= "<p>Content here</p>";
    $html .= "</div>";
    return $html;
}
```

#### ✅ DO - Output Buffering
```php
public function renderExample(string $title): string
{
    ob_start();
    ?>
<div class="container">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Content here</p>
</div>
    <?php
    return ob_get_clean();
}
```

**Exception:** Very simple single-line returns can use concatenation if readability is maintained.

### 2. Use Clear, Actionable Pull Request Titles
- Start PR titles with a verb (e.g., "Add", "Fix", "Refactor", "Update")
- Be concise but descriptive (e.g., "Fix player stats calculation bug")
- Reference issue numbers when applicable

### 2. Write Descriptive Pull Request Descriptions
- Clearly explain the purpose and context of the change
- List any related issues or tickets
- Include testing or validation steps if relevant
- Document any breaking changes or migration steps
- Highlight architectural decisions made

### 3. Keep Pull Requests Focused
- Each PR should address a single logical change or feature
- Avoid mixing unrelated changes in one PR
- Break large refactors into smaller, reviewable chunks
- Consider the reviewer's experience when sizing PRs

### 4. Use Conventional Commits (Optional)
- If possible, use [Conventional Commits](https://www.conventionalcommits.org/) for commit messages
- Types: feat, fix, refactor, test, docs, chore, style

### 5. Review and Approve PRs Promptly
- Review Copilot-generated PRs for correctness and style
- Leave feedback or request changes as needed
- Verify all tests pass
- Approve and merge when ready

### 6. Code Quality Checklist
- [ ] Code follows existing patterns and conventions
- [ ] **No `require()` or `require_once()` statements for classes - use the autoloader**
- [ ] All classes are placed in `ibl5/classes/` directory
- [ ] All tests pass without warnings or failures
- [ ] New functionality includes appropriate tests
- [ ] Code is self-documenting with clear variable/function names
- [ ] Complex logic includes explanatory comments
- [ ] No debugging code or commented-out blocks left behind
- [ ] Database queries consider PostgreSQL compatibility
- [ ] Changes support eventual Laravel migration where applicable
- [ ] **All functions and methods have complete type hints** (parameters and return types)
- [ ] Existing function calls verified for correctness and argument compatibility
- [ ] All linter warnings and errors addressed and fixed
- [ ] Strict types enabled where applicable (`declare(strict_types=1);`)
- [ ] **Deprecated functions removed and all call sites updated** to use new implementations
- [ ] **Domain-specific constants are class constants, not function arguments**

### 7. Constants and Magic Numbers

#### Use Class Constants for Domain Values
**NEVER pass domain-specific constants as function arguments.** Instead, define them as class constants and reference them directly within methods.

**❌ DON'T - Pass constants as arguments:**
```php
// Bad: Passing constant values as arguments
private function checkEligibility($player, int $minExperience, int $maxExperience): bool {
    return $player->experience >= $minExperience && $player->experience <= $maxExperience;
}

// Called with magic numbers
$result = $this->checkEligibility($player, 2, 5);
```

**✅ DO - Define and use class constants:**
```php
// Good: Define constants at class level
class PlayerValidator {
    private const MIN_ELIGIBLE_EXPERIENCE = 2;
    private const MAX_ELIGIBLE_EXPERIENCE = 5;
    
    private function checkEligibility(PlayerData $player): bool {
        return $player->experience >= self::MIN_ELIGIBLE_EXPERIENCE 
            && $player->experience <= self::MAX_ELIGIBLE_EXPERIENCE;
    }
}
```

**When to Use Class Constants:**
- Business rules (e.g., `MAX_CONTRACT_YEARS = 6`)
- Thresholds (e.g., `ROOKIE_OPTION_ROUND1_EXPERIENCE = 2`)
- Status codes (e.g., `STATUS_ACTIVE = 1`)
- Configuration values (e.g., `DEFAULT_SALARY_CAP = 50000`)

**Benefits:**
- Self-documenting code with descriptive constant names
- Single source of truth for domain values
- Easier refactoring and maintenance
- Better IDE support and autocomplete
- Type safety and immutability

### 8. Type Hinting & Error Handling Standards

#### Mandatory Type Hints
**ALL new functions and methods MUST include complete type hints:**
- **Parameter types**: Every parameter must have a type declaration
- **Return types**: Every function/method must declare its return type (use `void` for non-returning functions)
- **Union types** (PHP 8+): `string|int|null`
- **Nullable types**: `?Type` when parameter/return can be null
- **Avoid `mixed`** unless truly necessary; prefer specific types
- **When refactoring legacy code**: Add type hints as part of refactoring and update all call sites

Common patterns in IBL5:
```php
// ✅ Database, players, arrays, calculations, booleans
public function query(mysqli $db, string $sql): mixed
public function getPlayer(int $playerId): ?Player
public function getTeamRoster(int $teamId): array
public function calculateAverage(array $values): float
public function isPlayerActive(int $playerId): bool
public function logEvent(string $message, ?string $userId = null): void
```

#### Error Detection & Fixing Workflow

**Before each commit, the Copilot agent MUST:**
1. **Identify all function/method calls** in changed code
2. **Verify argument count and types match** function parameters
3. **Check return value usage matches** declared return type
4. **Run static analysis** (PHPStan level 5+, Psalm strict mode)
5. **Fix all errors and warnings** - zero tolerance

**Common error patterns to check:**
- Argument count mismatches or type mismatches (e.g., `string` where `int` expected)
- Null values passed to non-nullable parameters
- Return values used from `void` functions
- Array functions called on non-arrays
- Missing methods or incorrect call signatures

**Example fixes:**
```php
// ❌ WRONG → ✅ FIXED
$team = Team::findById();              → $team = Team::findById($teamId);
$player = Player::getById("12345");    → $player = Player::getById((int)"12345");
$result = logEvent("Injured");         → logEvent("Injured");  // void return
if ($result) { ... }
```

**Linter standards:** PHP_CodeSniffer (PSR-12), PHPStan, Psalm. All warnings/errors must be resolved before PR completion.

#### Laravel Migration Compatibility
- Use PHP 8 union types (compatible with Laravel 10+)
- Prefer PHP 8+ features over PHP 7.4 syntax
- Type hints enable easier future Eloquent ORM migration
- Document complex array types: `@return array<string, mixed>`

### 9. Deprecated Function Handling

**When encountering deprecated functions, the agent MUST:**

1. **Identify all call sites** using `grep_search` or `semantic_search`
2. **Update every call site** to use the new implementation
3. **Verify the updates** by checking function signatures match
4. **Delete the deprecated function** completely once all call sites are updated
5. **Run tests** to ensure no breakage from the migration

**Search patterns for deprecated code:**
- Functions marked with `@deprecated` in docblocks
- Comments like `// Deprecated`, `// TODO: Remove`, `// Legacy`
- Old naming patterns (e.g., `snake_case` in classes using `camelCase`)

**Migration workflow:**
```php
// 1. Find deprecated function
/** @deprecated Use NewClass::newMethod() instead */
function oldFunction($param) { ... }

// 2. Search all usages: grep_search "oldFunction"

// 3. Replace each call site
oldFunction($value);  →  NewClass::newMethod($value);

// 4. Delete deprecated function entirely

// 5. Run full test suite to verify
```

**DO NOT:**
- Leave deprecated functions "just in case"
- Update some call sites but not others
- Create new code that uses deprecated functions
- Skip testing after deprecation cleanup

## Copilot Coding Agent Configuration

### Environment Setup (CRITICAL)
The Copilot agent requires a pre-configured development environment to run tests efficiently. See the **Development Environment Setup** section below for details on how the agent initializes its environment.

- The Copilot agent will:
  - Open PRs for code changes
  - Provide detailed PR descriptions and context
  - Respond to feedback and update PRs as needed
  - Run tests and ensure they pass before completing PRs
  - Consider architectural implications of all changes
  - **Use the class autoloader and avoid manual `require()` statements for classes**
  - Place all new classes in `ibl5/classes/` directory
  - **Add complete type hints to ALL functions and methods** (parameter types and return types)
  - **Verify all existing function calls** for correctness against the function signatures
  - **Fix all type mismatches, argument count errors, and linter warnings** before PR completion
  - **Run static analysis** to detect and fix type errors and inconsistencies
  - **Update all call sites of deprecated functions** and delete the deprecated code
  - **Zero tolerance for warnings or errors**: PRs must pass all quality checks
- The agent will **not** merge PRs automatically; human review is required

## Development Environment Setup (For Copilot Agent)

### Problem Statement
The Copilot agent cannot install dependencies from scratch on every run due to private repository access restrictions. Instead, it relies on a properly configured development environment that has dependencies pre-installed.

### Solution Architecture

#### 1. **Use Dev Container Configuration** (Recommended for Copilot Agent)
The `.devcontainer/` directory contains configuration for a standardized PHP development environment:

- **Location**: `.devcontainer/devcontainer.json` - VS Code Dev Container configuration
- **Post-Create Hook**: `.devcontainer/post-create.sh` - Automatically installs dependencies

**How it works:**
1. Copilot agent initializes the dev container
2. Dev container runs `post-create.sh` automatically
3. All Composer dependencies are installed within the container
4. PHPUnit and all dev tools are available immediately
5. Tests can run without delays or network issues

**Benefits:**
- ✅ Reproducible environment across all agent runs
- ✅ No network calls to private repositories
- ✅ Fast test execution (dependencies cached)
- ✅ Consistent PHP version and extensions (8.3)
- ✅ Automatically handles Composer installation if missing

#### 2. **Manual Setup for Local Development**
Run the setup script before working:

```bash
# From repository root
bash setup-dev.sh
```

This script:
- Checks for PHP 8.3+
- Installs Composer if needed
- Runs `composer install` in the `ibl5/` directory
- Verifies PHPUnit and all tools are available

#### 3. **GitHub Actions CI/CD Caching**
The `.github/workflows/tests.yml` workflow implements intelligent caching:

- **Composer Cache**: Caches `~/.composer/cache` across runs
- **Vendor Cache**: Caches `ibl5/vendor/` directory across runs
- **Cache Key**: Uses `composer.lock` hash to invalidate cache when dependencies change
- **No Network Calls**: Subsequent runs use cached dependencies

**How it helps Copilot:**
- Pulls from GitHub's cache instead of downloading from internet
- Significantly faster dependency resolution
- Reduces network errors from package downloads

### Testing from Command Line

Once setup is complete, tests run via:

```bash
cd ibl5
phpunit                                    # Run all tests
phpunit tests/Player/                      # Run specific test suite
phpunit --filter testRenderPlayerHeader    # Run specific test
```

### Verifying Setup

```bash
cd ibl5
vendor/bin/phpunit --version               # Should show PHPUnit 12.4.3+
vendor/bin/phpstan --version               # Should show PHPStan version
vendor/bin/phpcs --version                 # Should show PHP_CodeSniffer version
```

### Troubleshooting

| Issue | Solution |
|-------|----------|
| `Command 'phpunit' not found` | Run `cd ibl5 && composer install` |
| `Composer not found` | Run setup script: `bash setup-dev.sh` |
| `Permission denied` on setup script | Run: `chmod +x setup-dev.sh` |
| `PHP version too old` | Install PHP 8.3+: Use `setup-dev.sh` for guidance |
| Slow composer install | Clear cache: `composer clearcache` |

### Key Files

- `.devcontainer/devcontainer.json` - VS Code dev container config
- `.devcontainer/post-create.sh` - Auto-setup hook for containers
- `setup-dev.sh` - Manual setup script (repository root)
- `.github/workflows/tests.yml` - CI/CD with dependency caching
- `ibl5/composer.json` - Project dependencies (dev tools)
- `ibl5/composer.lock` - Locked dependency versions

## Working with the Database Schema

### Quick Reference
- **Location**: `ibl5/schema.sql` - Complete reference for all tables, columns, constraints
- **Comprehensive Guide**: See `DATABASE_GUIDE.md` for detailed schema documentation

### When to Reference the Schema
- Before creating database queries or classes that interact with tables
- When adding new database-related tests
- When writing migration scripts

### Key Relationships (Quick Reference)
- **Player ID**: `ibl_plr.pid` (primary), `ibl_plr.uuid` (public API)
- **Team ID**: `ibl_team_info.teamid` (primary), `ibl_team_info.uuid` (public API)
- **Player-Team**: `ibl_plr.tid` links to `ibl_team_info.teamid`
- **History**: `ibl_hist` tracks year-by-year player statistics
- **Contracts**: `cy1`-`cy6` fields in `ibl_plr` and `ibl_trade_cash`

For complete table documentation, relationships, indexes, and migration history, see `DATABASE_GUIDE.md`.

## Statistics Formatting & Sanitization

### Using StatsFormatter and StatsSanitizer Classes

**Location**: `ibl5/classes/Statistics/`

When refactoring or adding statistics display code, use the unified `Statistics\StatsFormatter` class to replace repeated formatting patterns:

#### Common Patterns to Replace
- **Percentage formatting**: `($attempted) ? number_format($made / $attempted, 3) : "0.000"` → `StatsFormatter::formatPercentage($made, $attempted)`
- **Per-game averages**: `($games) ? number_format($total / $games, 1) : "0"` → `StatsFormatter::formatPerGameAverage($total, $games)`
- **Per-36 minutes**: `StatsFormatter::formatPer36Stat($total, $minutes)`
- **Totals**: `number_format($value)` → `StatsFormatter::formatTotal($value)`
- **Points calculation**: `(2 * $fgm + $ftm + $tgm)` → `StatsFormatter::calculatePoints($fgm, $ftm, $tgm)`

All methods handle zero-division safely and return appropriate defaults.

#### Already Refactored Files
- `TeamStats.php` - Offense/defense averages and percentages
- `PlayerStats.php` - Both `fill()` and `fillHistorical()` methods
- `UI.php` - Per-36 minute calculations
- `modules/Leaderboards/index.php` - Totals and averages displays

**Reference**: See `STATISTICS_FORMATTING_GUIDE.md` for complete method signatures, examples, and testing details.

## Additional Resources
- **[Development Guide](DEVELOPMENT_GUIDE.md)** - Refactoring priorities, module status, workflow
- **[Database Guide](DATABASE_GUIDE.md)** - Schema reference, migrations, best practices
- **[API Guide](API_GUIDE.md)** - API development with UUIDs, views, caching
- **[Statistics Formatting Guide](STATISTICS_FORMATTING_GUIDE.md)** - StatsFormatter and StatsSanitizer usage
- **[Copilot Agent Instructions](COPILOT_AGENT.md)** - Coding standards and practices
- [Copilot Coding Agent Best Practices](https://gh.io/copilot-coding-agent-tips)
- [Conventional Commits](https://www.conventionalcommits.org/)