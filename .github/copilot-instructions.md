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
- **Criticize design and technology decisions constructively** - When prompted, evaluate proposed approaches, identify trade-offs, and recommend modern or superior alternatives with clear justification

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

### ⚠️ CRITICAL: Interface-Driven Architecture Pattern

**Established Pattern (Implemented in PlayerSearch, FreeAgency, Player modules)**

The codebase uses **interface contracts** as the single source of truth for class responsibilities. This pattern maximizes LLM readability and maintainability.

#### Architecture Overview

**For each refactored module, create interfaces in a `Contracts/` subdirectory:**

```
Module/
├── Contracts/
│   ├── ModuleInterface.php                    # Facade contract (if applicable)
│   ├── ModuleRepositoryInterface.php          # Data access contract
│   ├── ModuleValidatorInterface.php           # Validation contract
│   ├── ModuleProcessorInterface.php           # Business logic contract
│   ├── ModuleServiceInterface.php             # Service layer contract
│   └── ModuleViewInterface.php                # View rendering contract
├── Module.php                   # Facade (implements ModuleInterface)
├── ModuleRepository.php         # Data access (implements ModuleRepositoryInterface)
├── ModuleValidator.php          # Validation (implements ModuleValidatorInterface)
├── ModuleProcessor.php          # Business logic (implements ModuleProcessorInterface)
├── ModuleService.php            # Services (implements ModuleServiceInterface)
└── ModuleView.php               # Views (implements ModuleViewInterface)
```

#### Interface Documentation Standards

**Each interface MUST contain comprehensive PHPDoc documenting:**

1. **Method Signatures** - All parameter types and return types
2. **Behavioral Documentation** - What the method does and why
3. **Parameter Constraints** - Valid ranges, required formats, constraints
4. **Return Value Structure** - Describe arrays, objects, edge cases
5. **Important Behaviors** - Edge cases, error conditions, side effects
6. **Usage Examples** (optional) - For complex methods

**Example Interface:**

```php
<?php

namespace PlayerSearch\Contracts;

/**
 * PlayerSearchValidatorInterface - Validates player search input
 * 
 * Enforces whitelist validation and input sanitization for player search operations.
 * All methods return true/false to indicate validation success/failure.
 */
interface PlayerSearchValidatorInterface
{
    /**
     * Validate and sanitize player name search input
     *
     * @param string $playerName Raw player name from user input (max 64 characters)
     * @return string Sanitized player name (whitespace trimmed, safe for queries)
     * @throws InvalidArgumentException If playerName exceeds 64 characters
     * 
     * **Behaviors:**
     * - Trims leading/trailing whitespace
     * - Returns empty string if input is null or empty
     * - Throws exception if name exceeds maximum length
     * - Does NOT escape for SQL (use prepared statements instead)
     */
    public function validatePlayerName(string $playerName): string;

    /**
     * Validate player position against whitelist
     *
     * @param string $position Player position (e.g., "PG", "SG", "SF", "PF", "C")
     * @return bool True if position is valid (in whitelist), false otherwise
     * 
     * **Valid Positions:** PG, SG, SF, PF, C
     * **Behaviors:**
     * - Case-insensitive validation (converts to uppercase)
     * - Returns false for unknown positions
     * - Returns false for null/empty position
     */
    public function validatePosition(string $position): bool;
}
```

#### Implementation Pattern

**All implementations MUST:**

1. **Use `implements InterfaceType` clause** - Explicit contract declaration
2. **Add `@see InterfaceNamespace\InterfaceName` docblock** - Point to contract
3. **Replace redundant method docblocks with `@see` references** - Avoid duplication
4. **Maintain type hints** - Match interface signatures exactly
5. **Support both legacy and modern database implementations** - Use `method_exists()` to detect capability

**Example Implementation:**

```php
<?php

namespace PlayerSearch;

use PlayerSearch\Contracts\PlayerSearchValidatorInterface;

/**
 * @see PlayerSearchValidatorInterface
 */
class PlayerSearchValidator implements PlayerSearchValidatorInterface
{
    private const VALID_POSITIONS = ['PG', 'SG', 'SF', 'PF', 'C'];
    private const MAX_NAME_LENGTH = 64;

    /**
     * @see PlayerSearchValidatorInterface::validatePlayerName()
     */
    public function validatePlayerName(string $playerName): string
    {
        $sanitized = trim($playerName);
        if (strlen($sanitized) > self::MAX_NAME_LENGTH) {
            throw new InvalidArgumentException('Player name exceeds maximum length');
        }
        return $sanitized;
    }

    /**
     * @see PlayerSearchValidatorInterface::validatePosition()
     */
    public function validatePosition(string $position): bool
    {
        if (empty($position)) {
            return false;
        }
        return in_array(strtoupper($position), self::VALID_POSITIONS, true);
    }
}
```

#### When to Add `@see` Instead of Full Docblock

**Replace method docblocks with `@see InterfaceName::methodName()` when:**
- The method is public and part of the interface contract
- The interface provides complete documentation of behavior
- The implementation is straightforward and self-explanatory (code is obvious-correct)

**Keep full docblocks when:**
- Implementation details differ from interface (rarely)
- There are implementation-specific optimizations (rarely)
- Complex internal logic needs explanation (keep minimal; prefer refactoring)

#### Database Implementation Flexibility

**When a method uses the database, support both implementations:**

```php
public function getFreeAgencyDemands(string $playerName): array
{
    // Detect which database implementation is available
    if (method_exists($this->db, 'sql_escape_string')) {
        // Using MySQL abstraction layer (legacy)
        $escapedName = $this->db->sql_escape_string($playerName);
        $query = "SELECT * FROM ibl_demands WHERE name = '$escapedName'";
        $result = $this->db->sql_query($query);
        $row = $this->db->sql_fetch_assoc($result);
    } else {
        // Direct mysqli connection (modern)
        $query = "SELECT * FROM ibl_demands WHERE name = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('s', $playerName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
    }
    
    // Standard return format
    if ($row) {
        return [
            'dem1' => (int) ($row['dem1'] ?? 0),
            'dem2' => (int) ($row['dem2'] ?? 0),
            // ... more fields
        ];
    }
    
    return ['dem1' => 0, 'dem2' => 0, /* ... */];
}
```

#### Benefits of Interface-Driven Architecture

1. **LLM Readability** - Interfaces are scannable, contracts are obvious
2. **Self-Documenting** - No need for verbose comments
3. **Type Safety** - Enforces contracts at runtime and compile-time
4. **Refactoring Safety** - Changes to signatures caught immediately
5. **Testing** - Mock interfaces easily, test contracts
6. **Onboarding** - New developers understand responsibilities instantly
7. **Maintenance** - Single source of truth (interface) for all implementations

#### Current Implementation Status

**Modules with Complete Interface Architecture:**
- ✅ **PlayerSearch** (4 interfaces, 4 implementations, 54 tests)
- ✅ **FreeAgency** (7 interfaces, 6 implementations, 11 tests)
- ✅ **Player** (9 interfaces, 8 implementations, 84 tests)

**Pattern to Apply to Remaining Modules:**
- Compare_Players, Leaderboards, Stats modules (Searchable_Stats, League_Stats, Chunk_Stats)
- All new modules going forward

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

**Reference**: See `ibl5/docs/TEST_REFACTORING_SUMMARY.md` for complete refactoring history and additional examples.

#### PHPUnit Test Suite Registration (CRITICAL)

**Every new test directory and test class MUST be registered in `ibl5/phpunit.xml`.**

**After writing test files, the Copilot agent MUST:**

1. **Verify test directory structure** - Tests should be in `ibl5/tests/ModuleName/`
2. **Add test suite to phpunit.xml** - Register the directory or individual test files
3. **Update testsuite name** - Use descriptive names (e.g., "Player Module Tests", "FreeAgency Module Tests")
4. **Verify tests are discoverable** - Run `vendor/bin/phpunit --list-suites` to confirm registration

**Example:** Adding a new module's tests:

```xml
<!-- ✅ CORRECT - Add test suite to phpunit.xml -->
<testsuites>
    <!-- ... existing suites ... -->
    <testsuite name="Compare Players Module Tests">
        <directory>tests/ComparePlayers</directory>
    </testsuite>
</testsuites>
```

**For individual files when directory registration is not appropriate:**

```xml
<testsuite name="FreeAgency Module Tests">
    <file>tests/FreeAgency/FreeAgencyDemandCalculatorTest.php</file>
    <file>tests/FreeAgency/FreeAgencyNegotiationHelperTest.php</file>
</testsuite>
```

**DO NOT:**
- Leave test files unregistered in phpunit.xml
- Create tests without verifying they run via `vendor/bin/phpunit`
- Assume tests will be discovered automatically - they must be explicitly registered

### Documentation Updates During Refactoring (CRITICAL)

**Documentation MUST be updated incrementally during the refactoring PR, not after merge.**

**After completing each component (Repository/Service/View/etc.), the Copilot agent MUST:**

1. **Update STRATEGIC_PRIORITIES.md** - Mark module as complete with brief summary
2. **Update REFACTORING_HISTORY.md** - Add entry to "Completed Refactorings" section
3. **Create component README.md** - In `ibl5/classes/ModuleName/README.md` if not already created
4. **Update documentation cross-references** - Fix any links in related docs
5. **Verify all links work** - Test internal documentation links before finalizing

**Documentation Update Workflow (During PR):**

**Step 1: Update STRATEGIC_PRIORITIES.md**
```markdown
### Priority X: Module_Name ✅ (Completed)

**Achievements:**
- N classes created with separation of concerns
- Reduced module code: X → Y lines (Z% reduction)
- N comprehensive tests
- Security improvements: [list any security fixes]

**Classes Created:**
1. ModuleNameValidator - [purpose]
2. ModuleNameRepository - [purpose]
3. ModuleNameService - [purpose]
4. ModuleNameView - [purpose]

**Files Refactored:**
- `modules/Module_Name/index.php`: X → Y lines (-Z%)
```

**Step 2: Update REFACTORING_HISTORY.md**
Add new section in "Completed Refactorings" with same details as STRATEGIC_PRIORITIES entry.

**Step 3: Create Component README.md**
- Location: `ibl5/classes/ModuleName/README.md`
- Document architecture, class responsibilities, usage patterns
- Include usage examples and key design decisions
- Link to test files if applicable

**Step 4: Update DEVELOPMENT_GUIDE.md**
- Move module from "Remaining IBL Modules" to "✅ Completed IBL Modules"
- Update refactoring status count (e.g., "15/23" → "16/23")
- Update test count if applicable

**Step 5: Update ibl5/docs/README.md**
- Update documentation index if new component README created
- Verify all links in index are current and working

**Important Timing:**
- ✅ **DO** - Update docs as you complete each refactor in the PR
- ✅ **DO** - Verify docs are current before requesting PR review
- ✅ **DO** - Ensure all links work and references are accurate
- ❌ **DON'T** - Wait until after merge to update documentation
- ❌ **DON'T** - Leave "TODO" comments about updating docs
- ❌ **DON'T** - Create separate documentation PRs for refactoring work

**Example PR Progress (During Implementation):**

```
1. Complete PlayerSearch module refactoring ✅
   - Created 4 classes, 54 tests
   - Updated STRATEGIC_PRIORITIES.md ✅
   - Updated REFACTORING_HISTORY.md ✅
   - Created ibl5/classes/PlayerSearch/README.md ✅
   - Updated DEVELOPMENT_GUIDE.md ✅
   - Verified all documentation links ✅

2. Ready for review - all tests passing, docs current ✅
```

**Verification Checklist Before PR Review:**

- [ ] `STRATEGIC_PRIORITIES.md` updated with module completion summary
- [ ] `REFACTORING_HISTORY.md` updated with detailed refactoring section
- [ ] Component README.md created in `ibl5/classes/ModuleName/`
- [ ] `DEVELOPMENT_GUIDE.md` updated (refactoring count, status)
- [ ] `ibl5/docs/README.md` updated if new docs created
- [ ] All internal documentation links verified and working
- [ ] No "TODO" comments about documentation left in code or docs
- [ ] Test suite registered in `ibl5/phpunit.xml`
- [ ] All tests passing without warnings or errors

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
- [ ] **When refactoring modules, create comprehensive interfaces** in `Module/Contracts/` directory
- [ ] **All public methods documented in interfaces with complete PHPDoc** (behavior, parameters, return values)
- [ ] **Classes implement interfaces with `implements InterfaceType` and add `@see` docblock**
- [ ] **Method docblocks replaced with `@see InterfaceName::methodName()`** (avoid redundancy)
- [ ] **Database methods support both legacy and modern implementations** using `method_exists()` detection
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
4. **Fix all errors and warnings** - zero tolerance

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

**Linter standards:** PHP_CodeSniffer (PSR-12). All warnings/errors must be resolved before PR completion.

#### Constructor Argument Validation (CRITICAL - ZERO TOLERANCE)

**This is a frequent source of runtime errors. EVERY instantiation must be verified.**

**Before any class instantiation in refactored code, the Copilot agent MUST:**

1. **Find the class constructor** - Locate the `__construct()` method signature
2. **Count required parameters** - Identify which parameters are mandatory vs optional
3. **Verify argument count** - Ensure every instantiation passes the correct number of arguments
4. **Check argument types** - Ensure each argument matches the declared type
5. **Update all instantiations** - If a constructor changes, update EVERY call site
6. **Document changes** - If modifying a constructor signature, search for all usages

**Common Patterns to Check:**

```php
// ❌ WRONG - Missing required $season parameter
$helper = new FreeAgencyNegotiationHelper($this->db);
// Constructor expects: __construct($db, \Season $season, $mysqli_db = null)

// ✅ CORRECT - All required parameters provided
$helper = new FreeAgencyNegotiationHelper($this->db, $this->season);

// ❌ WRONG - Wrong number of arguments
$processor = new FreeAgencyProcessor($db, $mysqli_db, $extraParam);
// Constructor expects: __construct($db, $mysqli_db = null)

// ✅ CORRECT - Optional parameter handling
$processor = new FreeAgencyProcessor($db);              // OK - $mysqli_db is optional
$processor = new FreeAgencyProcessor($db, $mysqli_db);  // OK - provide it if needed
```

**Verification Checklist:**

Before finalizing ANY refactoring:
- [ ] Search for every instantiation of modified classes: `grep_search "new ClassName"`
- [ ] For each match, verify the constructor signature at the class definition
- [ ] Count required parameters vs arguments provided in each instantiation
- [ ] Check that optional parameters (with defaults) are truly optional
- [ ] Verify argument types match parameter types in PHPDoc and type hints
- [ ] Run full test suite to catch runtime errors early
- [ ] Check error logs for `ArgumentCountError` or `TypeError` exceptions

**Error Pattern Recognition:**

Watch for these exact error messages that indicate argument count mismatches:

```
ArgumentCountError: Too few arguments to function ClassName::__construct()
ArgumentCountError: Too many arguments to function ClassName::__construct()
TypeError: Argument X must be of type Y, Z given
```

**Search Strategy for Refactored Classes:**

When modifying a class constructor, always follow this search pattern:
```bash
grep_search "new ClassName"  # Find all instantiations
grep_search "ClassName::"    # Find all static method calls
grep_search "ClassName->"    # Find all property accesses (less critical but verify types)
```

**Real-World Example - Free Agency Refactoring:**

The error that was fixed:
```
// FreeAgencyNegotiationHelper::__construct($db, \Season $season, $mysqli_db = null)
// ❌ WRONG - Called with 1 argument
new FreeAgencyNegotiationHelper($this->db);

// ✅ CORRECT - Called with 2 required arguments
new FreeAgencyNegotiationHelper($this->db, $this->season);
```

This was caught because:
1. Constructor signature requires `$season` parameter
2. All instantiations must provide it
3. The fix was applied to line 110 of FreeAgencyProcessor.php

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

### 10. Refactoring Cleanup Checklist

**After completing any refactoring, perform these cleanup tasks before finalizing the PR:**

#### Unused Arguments
1. **Identify unused method parameters** - Methods may no longer need arguments after refactoring
2. **Remove unused parameters** from method signatures
3. **Update ALL call sites** of the modified methods using `grep_search` or `semantic_search`
4. **Update PHPDoc comments** to reflect removed parameters
5. **Run tests** to verify no breakage

**Example:**
```php
// Before refactoring
public function renderDemandDisplay(array $demands, int $playerExperience): string
// After - if $playerExperience is unused
public function renderDemandDisplay(array $demands): string
// Then update all calls: renderDemandDisplay($demands, $player->years) → renderDemandDisplay($demands)
```

#### Dead Code & Redundant Logic
1. **Remove unused local variables** - Variables set but never read
2. **Eliminate redundant parameters** - Arguments passed but never used
3. **Delete unreachable code** - Code after `return`, `throw`, or impossible conditions
4. **Remove duplicate logic** - Consolidate repeated code into helper methods
5. **Clean up commented-out code** - Delete, don't leave commented blocks

#### Method Signature Hygiene
1. **Verify parameter order matches usage patterns** - Group related parameters together
2. **Check for optional parameters that should be required** - Or vice versa
3. **Ensure consistent parameter naming** across similar methods
4. **Review parameter types** - Use most specific types, not `mixed`

#### Documentation Accuracy
1. **Update all PHPDoc comments** to match actual method signatures
2. **Verify `@param` and `@return` type hints** are accurate and complete
3. **Remove documentation for deleted parameters** or methods
4. **Add documentation for new parameters** introduced during refactoring

#### Run Comprehensive Validation
1. **Run full test suite** - `phpunit` (all tests must pass)
2. **Check code style** - `phpcs --standard=PSR12 ibl5/classes/`
3. **Verify no new linter warnings** were introduced

**DO NOT:**
- Merge PRs with unused parameters in methods
- Leave dead code commented out
- Skip test validation after cleanup
- Create technical debt with TODO comments instead of fixing issues

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

## Local Database Connection (For Copilot Agent)

### MAMP MySQL Database Access

The Copilot agent can connect to your local MAMP MySQL database for test data and development purposes.

#### Connection Details
- **Host:** `localhost`
- **Port:** `3306` (MAMP default)
- **Database Name:** `iblhoops_ibl5`
- **Socket:** `/Applications/MAMP/tmp/mysql/mysql.sock`
- **Credentials Location:** See `ibl5/config.php` for actual database credentials (stored in `.gitignore`)

#### Setting Up DatabaseConnection for Tests

The `DatabaseConnection` class in `ibl5/classes/DatabaseConnection.php` provides unified database access for tests. To set it up:

1. **Copy the template file:**
   ```bash
   cd ibl5/classes
   cp DatabaseConnection.php.template DatabaseConnection.php
   ```

2. **Add your credentials:**
   - Find your database credentials in `ibl5/config.php`
   - Look for variables: `$dbuname`, `$dbpass`, `$dbname`
   - Open `DatabaseConnection.php` and replace the `REPLACE_ME_*` placeholders

3. **Verify .gitignore:**
   - `DatabaseConnection.php` is in `.gitignore` and will never be committed
   - Only the template file (`DatabaseConnection.php.template`) is version controlled

#### PHP Connection Using DatabaseConnection Helper Class (For Tests)

Once set up, use the `DatabaseConnection` class for easier database access:

```php
<?php
// Use the helper class for database access in tests
$player = DatabaseConnection::fetchRow("SELECT * FROM ibl_plr WHERE pid = ?", [123]);

// Fetch multiple rows
$players = DatabaseConnection::fetchAll("SELECT * FROM ibl_plr LIMIT 10");

// Fetch a single value
$playerCount = DatabaseConnection::fetchValue("SELECT COUNT(*) FROM ibl_plr");

// Test connection
if (DatabaseConnection::testConnection()) {
    echo "Connected to database successfully";
}

// Get connection status
$status = DatabaseConnection::getStatus();
var_dump($status);
?>
```

**Key Features:**
- Automatically handles MAMP socket path: `/Applications/MAMP/tmp/mysql/mysql.sock`
- Static methods for simple queries without managing connection state
- Supports prepared statements with parameter binding for security
- Includes error handling and connection validation
- UTF-8 charset automatically set

**Location:** `ibl5/classes/DatabaseConnection.php` (autoloaded, not committed)  
**Template:** `ibl5/classes/DatabaseConnection.php.template` (for reference)

#### Connecting via Command Line (Manual Verification)

To manually verify MAMP database connection:

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  -h localhost \
  -u <username_from_config.php> \
  -p'<password_from_config.php>' \
  -D iblhoops_ibl5 \
  -e "SELECT COUNT(*) as table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'iblhoops_ibl5';"
```

#### Important Security Notes
- **Credentials in Code:** The `DatabaseConnection.php` file contains hardcoded credentials for development use only
- **Git Protection:** `DatabaseConnection.php` is in `.gitignore` - it will NEVER be committed to the repository
- **Never Share:** Do not copy this file or its credentials outside of your local development environment
- **Template Only:** Only `DatabaseConnection.php.template` is version controlled; it contains placeholder values
- **Production Data:** The local database contains production IBL data - be careful when running destructive queries

#### For Test Development
When writing tests that need database data:

1. **Query actual data:** Use DatabaseConnection to fetch real player/team/game data
2. **Use transactions:** Wrap test operations in transactions that rollback
3. **Static data preferred:** Cache frequently-used test data rather than querying repeatedly
4. **Example:**
```php
public function testPlayerFetch()
{
    global $mysqli_db;
    
    // Get a real player from the database
    $result = $mysqli_db->query("SELECT * FROM ibl_plr LIMIT 1");
    $player = $result->fetch_assoc();
    
    $this->assertIsArray($player);
    $this->assertNotEmpty($player['pid']);
}
```

---

## Development Environment Setup (For Copilot Agent)

### Problem Statement
The Copilot agent cannot install dependencies from scratch on every run due to private repository access restrictions. Instead, it relies on pre-cached dependencies through GitHub Actions.

### ⚠️ CRITICAL: ALWAYS Check for Cached Dependencies FIRST

**Before running `composer install` or any PHPUnit commands, the Copilot Agent MUST:**

1. **Check if vendor directory exists:**
   ```bash
   ls -la ibl5/vendor/bin/phpunit 2>/dev/null && echo "✅ PHPUnit cached - use directly"
   ```

2. **If vendor exists**, use PHPUnit directly WITHOUT running composer install:
   ```bash
   cd ibl5 && vendor/bin/phpunit
   ```

3. **If vendor does NOT exist**, run the bootstrap script which will:
   - Try to restore from GitHub Actions cache first
   - Fall back to composer install only if cache is unavailable
   ```bash
   bash bootstrap-phpunit.sh
   ```

**DO NOT** run `composer install` directly - always use the bootstrap script or check for existing dependencies first.

### Solution Architecture

#### **GitHub Actions Dependency Caching** (Active Solution)
The `.github/workflows/cache-dependencies.yml` and `.github/workflows/tests.yml` workflows implement intelligent dependency caching with cache-first priority:

**Cache Dependencies Workflow** (`.github/workflows/cache-dependencies.yml`):
- Runs daily to keep cache fresh
- Runs when `composer.json` or `composer.lock` changes
- Can be triggered manually
- Pre-caches all PHP dependencies (PHPUnit, etc.)
- **Cache-first strategy**: Checks GitHub cache BEFORE attempting network downloads

**How it works:**
1. GitHub Actions cache is checked first using `composer.lock` hash as key
2. If vendor cache exists, it's restored immediately (no network calls)
3. If cache miss, Composer checks its own cache (`~/.composer/cache`) before downloading
4. Only if both caches miss does Composer download from package repositories
5. Downloaded packages are cached for future runs
6. PHPUnit and all dev tools available immediately from cache

**Benefits:**
- ✅ **Cache-first priority** avoids network timeouts and authentication issues
- ✅ No network calls needed when cache is available
- ✅ Fast test execution (dependencies restored from cache)
- ✅ Consistent PHP version and extensions (8.3)
- ✅ Automatic cache refresh on dependency changes
- ✅ Manual cache refresh available via workflow dispatch

**Test Workflow Integration** (`.github/workflows/tests.yml`):
- **Two-level caching**:
  1. **Vendor Cache**: Restores `ibl5/vendor/` from GitHub Actions cache (checked FIRST)
  2. **Composer Cache**: Restores `~/.composer/cache` for package downloads (fallback)
- **Cache Key**: Uses `composer.lock` hash to invalidate cache when dependencies change
- **Optimized install**: Uses plain `composer install` which checks cache before network
- **Fallback**: If both caches miss, downloads from repositories and caches result

### Testing from Command Line

**ALWAYS check for cached dependencies first:**

```bash
# Step 1: Check if dependencies are already cached
if [ -f "ibl5/vendor/bin/phpunit" ]; then
    echo "✅ Dependencies cached, running tests directly"
    cd ibl5 && vendor/bin/phpunit
else
    echo "⚠️ No cached dependencies, running bootstrap script"
    bash bootstrap-phpunit.sh
    cd ibl5 && vendor/bin/phpunit
fi
```

**Quick commands when dependencies are cached:**

```bash
cd ibl5
vendor/bin/phpunit                                    # Run all tests
vendor/bin/phpunit tests/Player/                      # Run specific test suite
vendor/bin/phpunit --filter testRenderPlayerHeader    # Run specific test
```

#### PHPUnit Command Syntax (CRITICAL)

**The version of PHPUnit in this project (12.4.3) has DIFFERENT command-line options than older versions.**

**Common Command Syntax Errors to AVOID:**

```bash
# ❌ WRONG - These options don't exist in PHPUnit 12.4.3
vendor/bin/phpunit -v                      # ❌ Unknown option "-v"
vendor/bin/phpunit --verbose               # ❌ Unknown option "--verbose"
vendor/bin/phpunit -c phpunit.xml          # ❌ Unknown option "-c"
vendor/bin/phpunit --configuration file    # ❌ Unknown option "--configuration"
vendor/bin/phpunit --coverage-html dir     # ❌ Unknown option "--coverage-html"
vendor/bin/phpunit tests/ -v               # ❌ Combines invalid option with path

# ✅ CORRECT - PHPUnit 12.4.3 compatible syntax
vendor/bin/phpunit tests/Player/           # Run specific test suite
vendor/bin/phpunit --filter testName       # Run tests matching filter
vendor/bin/phpunit --help                  # Show available options
```

**Valid PHPUnit 12.4.3 Options:**

```bash
# Test selection
vendor/bin/phpunit                         # Run all tests (default)
vendor/bin/phpunit tests/Player/           # Run specific directory
vendor/bin/phpunit tests/Player/PlayerTest.php  # Run specific file

# Filtering and selection
vendor/bin/phpunit --filter testMethodName # Run tests matching pattern
vendor/bin/phpunit --testsuite suiteName   # Run specific test suite

# Output control
vendor/bin/phpunit --quiet                 # Minimal output
vendor/bin/phpunit --debug                 # Debug output
vendor/bin/phpunit --help                  # Show all available options
```

**Reference:** Check available options with `vendor/bin/phpunit --help` before using unknown flags.

**DO NOT:**
- Use short flags like `-v`, `-c`, `-d` without checking `--help`
- Assume PHPUnit 12.4.3 accepts the same options as PHPUnit 9.x or 10.x
- Combine invalid options with valid commands
- Skip running `--help` when unsure about option syntax

### Verifying Setup

```bash
cd ibl5
vendor/bin/phpunit --version               # Should show PHPUnit 12.4.3+
vendor/bin/phpcs --version                 # Should show PHP_CodeSniffer version
```

### Troubleshooting

| Issue | Solution |
|-------|----------|
| `Command 'phpunit' not found` | Dependencies not cached - run "Cache PHP Dependencies" workflow manually |
| `Composer install fails` | Check `.github/workflows/cache-dependencies.yml` workflow logs |
| `Tests fail to run` | Verify cache-dependencies workflow completed successfully |
| `Cache outdated` | Manually trigger "Cache PHP Dependencies" workflow |

### Key Files

- `.github/workflows/cache-dependencies.yml` - Pre-cache workflow (runs daily)
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

**Reference**: See `ibl5/docs/STATISTICS_FORMATTING_GUIDE.md` for complete method signatures, examples, and testing details.

## Documentation Structure & Standards

### Documentation Organization (CRITICAL - MUST FOLLOW)

The repository uses a structured documentation hierarchy to reduce context window overhead for Copilot Agent and improve human navigation. **ALWAYS** follow this structure when creating or updating documentation.

#### Documentation Locations

**1. Root Directory (Essential Technical Guides Only)**
- Place ONLY core technical guides that developers need frequently
- Maximum of 6-8 files to keep root uncluttered
- Examples: README.md, DEVELOPMENT_GUIDE.md, DATABASE_GUIDE.md, API_GUIDE.md
- **DO NOT** place completion summaries, strategic planning docs, or historical reports here

**2. `ibl5/docs/` (Project Documentation)**
- **Purpose**: Strategic planning, historical tracking, testing guides
- **Place here**:
  - Strategic analysis documents (STRATEGIC_PRIORITIES.md)
  - Consolidated refactoring history (REFACTORING_HISTORY.md)
  - Testing best practices (TEST_REFACTORING_SUMMARY.md)
  - Process documentation
  - Planning documents
- **DO NOT** place component-specific or module-specific docs here

**3. Component READMEs (With Code)**
- **Purpose**: Document specific classes, modules, or features
- **Location**: Next to the code they document
- Examples:
  - `ibl5/classes/Player/README.md` - Player module architecture
  - `ibl5/classes/Statistics/README.md` - StatsFormatter usage
  - `ibl5/classes/DepthChart/SECURITY.md` - Security patterns
  - `ibl5/tests/Trading/README.md` - Trading test documentation
- **When to create**: When refactoring a module or creating a new class
- **Keep updated**: Update when module architecture changes

**4. `.archive/` (Historical Documents)**
- **Purpose**: Preserve completed work and superseded documentation
- **Place here**:
  - Completed refactoring summaries (after consolidating into REFACTORING_HISTORY.md)
  - Superseded guides or plans
  - Historical completion reports
- **DO NOT** delete historical docs - archive them for reference

#### Documentation Lifecycle

**When Creating New Documentation:**

1. **Refactoring a Module:**
   - Create detailed completion summary initially
   - After review, consolidate key points into `ibl5/docs/REFACTORING_HISTORY.md`
   - Move detailed summary to `.archive/`
   - Create component README in module directory (`ibl5/classes/Module/README.md`)

2. **Strategic Planning:**
   - Create in `ibl5/docs/` directory
   - Link from main README.md or DEVELOPMENT_GUIDE.md
   - Update `ibl5/docs/README.md` index

3. **Technical Guides:**
   - Create in root directory only if essential and frequently referenced
   - Consider if content belongs in existing guide instead
   - Update main README.md navigation section

4. **Component Documentation:**
   - Create README.md in the class/module directory
   - Keep focused on that specific component
   - Link from root documentation if important

**When Updating Documentation:**

1. **Check all cross-references** - Update links in related documents
2. **Update the index** - Modify `ibl5/docs/README.md` if adding/moving docs
3. **Verify links** - Test all internal links work correctly
4. **Update timestamps** - Add "Last Updated" date if present

**When Archiving Documentation:**

1. **Consolidate first** - Extract key information into permanent docs
2. **Move to `.archive/`** - Don't delete, preserve for reference
3. **Update references** - Remove links from active docs, note archive location
4. **Add to archive index** - Document what was archived and why

#### Documentation Standards

**File Naming:**
- Use SCREAMING_SNAKE_CASE for guide documents (DEVELOPMENT_GUIDE.md)
- Use README.md for directory/component documentation
- Use descriptive names (REFACTORING_HISTORY.md, not HISTORY.md)

**File Location Rules:**
- ✅ **DO**: Create consolidated history in `ibl5/docs/`
- ✅ **DO**: Keep component docs with their code
- ✅ **DO**: Archive completed summaries
- ❌ **DON'T**: Scatter completion summaries in root
- ❌ **DON'T**: Create redundant or overlapping docs
- ❌ **DON'T**: Delete historical documentation

**Content Structure:**
- Start with purpose/overview
- Include "Last Updated" date for living documents
- Use consistent markdown formatting
- Include navigation links to related docs
- Add examples where helpful

**Cross-References:**
- Use relative paths: `../DEVELOPMENT_GUIDE.md` or `ibl5/docs/REFACTORING_HISTORY.md`
- Test all links before committing
- Update all references when moving files

#### Quick Decision Tree

**Creating new documentation? Ask:**

1. **Is this a completion summary for a refactored module?**
   - Initial: Create detailed summary
   - After review: Consolidate into `ibl5/docs/REFACTORING_HISTORY.md`
   - Move detailed version to `.archive/`

2. **Is this strategic planning or process documentation?**
   - Place in `ibl5/docs/`
   - Update `ibl5/docs/README.md` index

3. **Is this about a specific class or module?**
   - Create README.md in that module's directory
   - Example: `ibl5/classes/YourModule/README.md`

4. **Is this an essential technical guide?**
   - Only add to root if truly essential
   - Otherwise, add to `ibl5/docs/` or expand existing guide

5. **Is this superseded or historical?**
   - Move to `.archive/`
   - Update any references

#### Documentation Index

**Always maintain** `ibl5/docs/README.md` as the comprehensive documentation index. When adding documentation:
1. Add entry to appropriate section
2. Include brief description
3. Ensure link works
4. Commit index update with the new doc

### Example Documentation Workflow

**Scenario: Just finished refactoring the "FreeAgency" module**

1. **Initial Summary** (during/after refactoring):
   - Create `FREE_AGENCY_REFACTORING_SUMMARY.md` in root (temporary)
   - Document all changes, architecture, improvements
   - Include in PR for review

2. **After PR Merge**:
   - Add key points to `ibl5/docs/REFACTORING_HISTORY.md` under "Completed Refactorings"
   - Create `ibl5/classes/FreeAgency/README.md` for component architecture
   - Move `FREE_AGENCY_REFACTORING_SUMMARY.md` to `.archive/`
   - Update `ibl5/docs/README.md` index
   - Update `DEVELOPMENT_GUIDE.md` to mark FreeAgency as complete

3. **Update References**:
   - Verify links in README.md
   - Check copilot-instructions.md examples if relevant
   - Ensure `ibl5/docs/README.md` is current

**DO NOT deviate from this structure** - consistency is critical for Copilot Agent effectiveness.

## Additional Resources
- **[Development Guide](../DEVELOPMENT_GUIDE.md)** - Refactoring priorities, module status, workflow
- **[Database Guide](../DATABASE_GUIDE.md)** - Schema reference, migrations, best practices
- **[API Guide](../API_GUIDE.md)** - API development with UUIDs, views, caching
- **[Strategic Priorities](../ibl5/docs/STRATEGIC_PRIORITIES.md)** - Strategic analysis & next priorities
- **[Refactoring History](../ibl5/docs/REFACTORING_HISTORY.md)** - Complete refactoring timeline
- **[Statistics Formatting Guide](../ibl5/docs/STATISTICS_FORMATTING_GUIDE.md)** - StatsFormatter and StatsSanitizer usage
- [Copilot Coding Agent Best Practices](https://gh.io/copilot-coding-agent-tips)
- [Conventional Commits](https://www.conventionalcommits.org/)