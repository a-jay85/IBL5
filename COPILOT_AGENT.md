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

### Database Schema & Considerations

#### Schema Reference
- **Schema Location**: `ibl5/schema.sql` (MariaDB 10.6.20 export)
- The complete database schema is available in the repository for reference
- Use the schema to understand table structures, relationships, and constraints

#### Database Architecture
- **Current Engine**: MySQL 5.5.5-10.6.20-MariaDB-cll-lve
- **Mixed Storage Engines**: MyISAM (legacy tables) and InnoDB (newer tables)
- **Character Sets**: Mixed latin1 (legacy) and utf8mb4 (modern Laravel tables)

#### Key Table Categories
1. **IBL Core Tables** (prefix: `ibl_`)
   - Player data: `ibl_plr`, `ibl_plr_chunk`, `ibl_hist`
   - Statistics: `ibl_*_stats`, `ibl_*_career_avgs`, `ibl_*_career_totals`
   - Game data: `ibl_box_scores`, `ibl_box_scores_teams`, `ibl_schedule`
   - Team management: `ibl_team_info`, `ibl_team_history`, `ibl_standings`
   - League operations: `ibl_draft`, `ibl_fa_offers`, `ibl_trade_*`
   - Awards/voting: `ibl_awards`, `ibl_votes_ASG`, `ibl_votes_EOY`

2. **PHP-Nuke Legacy Tables** (prefix: `nuke_`)
   - Forum system: `nuke_bb*` (phpBB integration)
   - User management: `nuke_users`, `nuke_authors`
   - CMS: `nuke_stories`, `nuke_modules`, `nuke_blocks`

3. **Laravel Migration Tables** (no prefix)
   - Modern Laravel tables: `cache`, `jobs`, `migrations`, `sessions`, `users`
   - Indicates gradual migration to Laravel framework

#### Migration Considerations
- **Storage Engine Migration**: Convert MyISAM tables to InnoDB for:
  - Better transaction support
  - Foreign key constraints
  - Improved concurrency and crash recovery
  - Required for modern ORM functionality

- **Character Set Standardization**: 
  - Legacy tables use `latin1_swedish_ci`
  - Modern tables use `utf8mb4_unicode_ci`
  - Consider charset migration for international character support

- **PostgreSQL Compatibility**:
  - Avoid MySQL-specific features (e.g., `MEDIUMINT`, `TINYINT`)
  - Use standard SQL types where possible
  - Be mindful of AUTO_INCREMENT vs SERIAL
  - Watch for DATE/DATETIME format differences

- **ORM Preparation**:
  - Many tables lack proper PRIMARY KEYs (e.g., `ibl_playoff_stats`)
  - Missing foreign key relationships despite logical connections
  - Consider adding indexes for common query patterns
  - Prepare for Eloquent model relationships

#### Schema Best Practices
- **Reference First**: Check `ibl5/schema.sql` before writing queries
- **Index Usage**: Verify existing indexes before adding new ones
- **Naming Conventions**: Follow existing patterns (`ibl_` prefix for league tables)
- **Data Integrity**: Be aware that MyISAM tables lack foreign key constraints
- **Future-Proof Queries**: Write SQL that can be easily converted to Eloquent
- **Testing Data**: Production schema provides real-world structure for test data

## Best Practices

### 1. Use Clear, Actionable Pull Request Titles
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

### 7. Type Hinting & Error Handling Standards

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

## Copilot Coding Agent Configuration

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
  - **Zero tolerance for warnings or errors**: PRs must pass all quality checks
- The agent will **not** merge PRs automatically; human review is required

## Working with the Database Schema

### Schema File Usage
- **Location**: `ibl5/schema.sql`
- **Purpose**: Complete reference for all database tables, columns, and constraints
- **Generated**: October 29, 2025 via Sequel Ace

### When to Reference the Schema
- Before creating database queries or classes that interact with tables
- When adding new database-related tests
- When planning refactoring that touches data layer
- When writing migration scripts or database documentation

### Understanding Table Relationships
The schema reveals:
- **Player Identity**: `ibl_plr.pid` is the primary player identifier
- **Team Identity**: `ibl_team_info.teamid` is the primary team identifier
- **Player-Team Link**: `ibl_plr.tid` and `ibl_plr.teamname` connect players to teams
- **Historical Tracking**: `ibl_hist` maintains year-by-year player statistics
- **Salary Cap**: Contract years stored as `cy1`-`cy6` in `ibl_plr` and `ibl_trade_cash`
- **Depth Charts**: Multiple depth fields in `ibl_plr` (`PGDepth`, `SGDepth`, etc.)

### Database Evolution Strategy
1. **Phase 1 (Current)**: PHP-Nuke with direct SQL queries
2. **Phase 2 (In Progress)**: Laravel coexistence (evidence: modern tables exist)
3. **Phase 3 (Target)**: Full Laravel with Eloquent ORM
4. **Phase 4 (Future)**: PostgreSQL compatibility layer

## Additional Resources
- [Copilot Coding Agent Best Practices](https://gh.io/copilot-coding-agent-tips)
- [Conventional Commits](https://www.conventionalcommits.org/)
- Database Schema Reference: `ibl5/schema.sql`