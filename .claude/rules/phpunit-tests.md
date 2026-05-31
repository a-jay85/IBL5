---
description: PHPUnit testing rules: output parsing, behavior-focused patterns.
paths: ibl5/tests/**/*.php
last_verified: 2026-05-31
---

# PHPUnit Testing Rules

## PHPUnit 13+ Syntax
```bash
# CORRECT commands (run from ibl5/ directory)
vendor/bin/phpunit                                   # Full suite
vendor/bin/phpunit --filter testMethodName            # Single test
vendor/bin/phpunit --display-all-issues               # Show ALL issues (deprecations, warnings, etc.)

# Token-saving: When just checking if tests pass (not debugging)
vendor/bin/phpunit | tail -n 3                        # Show only final summary lines

# WRONG - These options don't exist in PHPUnit 13.x
vendor/bin/phpunit -v
vendor/bin/phpunit --verbose
```

## Display Issue Details
PHPUnit 13.x only shows summary counts by default. To see full details:
- `--display-all-issues` - **Recommended:** shows everything
- `--display-deprecations`, `--display-warnings`, `--display-notices` - specific types

## Test File Structure
```php
<?php

declare(strict_types=1);

namespace Tests\ModuleName;

use PHPUnit\Framework\TestCase;

class ModuleServiceTest extends TestCase
{
    /** @var InterfaceName&\PHPUnit\Framework\MockObject\MockObject */
    private InterfaceName $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(InterfaceName::class);
    }

    public function testDescriptiveBehaviorName(): void
    {
        // Arrange
        $input = ['key' => 'value'];

        // Act
        $result = $this->service->publicMethod($input);

        // Assert
        $this->assertTrue($result->isValid());
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testRejectsInvalidInput(mixed $input, string $expectedError): void
    {
        $result = $this->service->validate($input);
        $this->assertStringContainsString($expectedError, $result->getError());
    }

    public static function invalidInputProvider(): array
    {
        return [
            'empty string' => ['', 'cannot be empty'],
            'negative number' => [-1, 'must be positive'],
        ];
    }
}
```

## DO:
- Test behaviors through public APIs only
- Use descriptive test names
- Test one behavior per test
- Use data providers for similar cases
- Use `@see` instead of `{@inheritdoc}`

## Mock vs Stub

Use `createStub()` when a test double only provides canned return values (no `expects()` calls). Use `createMock()` only when you need to verify interactions with `expects()`. PHPUnit emits a notice when a mock object has no configured expectations.

```php
// No expectations — use createStub()
$repo = $this->createStub(RepositoryInterface::class);
$repo->method('findById')->willReturn($entity);

// Has expectations — use createMock()
$repo = $this->createMock(RepositoryInterface::class);
$repo->expects($this->once())->method('save')->with($entity);
```

### Shared setUp() pattern — use stubs + buildService helper

When `setUp()` creates mocks shared across all tests, **every** test that doesn't call `expects()` on **every** mock generates a PHPUnit notice. For services with 3+ dependencies where different tests exercise different subsets, create all stubs in `setUp()` and use a `buildService()` helper with nullable overrides:

```php
protected function setUp(): void
{
    $this->stubRepo = $this->createStub(RepoInterface::class);
    $this->stubAuth = $this->createStub(AuthInterface::class);
    $this->service = $this->buildService();
}

private function buildService(
    RepoInterface|null $repo = null,
    AuthInterface|null $auth = null,
): MyService {
    return new MyService($repo ?? $this->stubRepo, $auth ?? $this->stubAuth);
}

public function testSaveDelegates(): void
{
    $mockRepo = $this->createMock(RepoInterface::class);
    $mockRepo->expects($this->once())->method('save');
    $this->service = $this->buildService(repo: $mockRepo);
    // ...
}
```

## Repository Write Methods

`BaseMysqliRepository::getAffectedRows()` is a protected method that can be overridden in test subclasses to control the return value of `execute()`. This enables direct unit testing of repository write methods without needing a real database.

## DON'T:
- **NEVER** use `createMock()` when no `expects()` calls are configured — use `createStub()` instead
- **NEVER** use `ReflectionClass` for private methods — test behavior through public APIs; if a private method needs direct testing, it should be extracted to a separate class with a public interface
- **NEVER** use `markTestSkipped()` to silently disable a test — delete instead. The only exception is an integration-availability skip (e.g., a service unreachable), which must carry an inline `// phpunit-hygiene-allow: <reason ≥20 chars>` marker; `bin/check-phpunit-hygiene` enforces this.
- **NEVER** check full SQL query structure (column names, WHERE clauses, bind strings) — except security tests. For void write methods with no return value to assert on, you may use `assertQueryExecuted('table_name')` to verify the target table was hit, but don't match beyond the table name.

## Test Registration
Register in `ibl5/phpunit.xml`:
```xml
<testsuite name="ModuleName Tests">
    <directory>tests/ModuleName</directory>
</testsuite>
```

## WideUnit Test Setup

```php
// WideUnit test setup (mock-based multi-class workflow tests)
class MyTest extends WideUnitTestCase {
    protected function setUp(): void {
        parent::setUp();  // Sets up $this->mockDb
    }
}

// Test data factory
$player = TestDataFactory::createPlayer(['pid' => 1, 'name' => 'Test']);
$team = TestDataFactory::createTeam(['team_name' => 'Miami']);
$season = TestDataFactory::createSeason(['Phase' => 'Regular Season']);

// Assert queries
$this->assertQueryExecuted('UPDATE ibl_plr');
$this->assertQueryNotExecuted('DELETE');
```

### MockDatabase Query Routing

**Preferred approach:** Use `onQuery()` to route different SQL patterns to different result sets:
```php
// Route COUNT queries to a count result, data queries to player rows
$this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);
$this->mockDb->setMockData([['pid' => 1, 'name' => 'Player']]);
```

`onQuery(string $pattern, array $rows)` registers a regex pattern (case-insensitive) that is checked **before** all other MockDatabase routing. Use it whenever a test's code-under-test runs multiple different queries (e.g., paginated controllers that call both `countX()` and `getX()`).

**Legacy approach (still works):** `setMockData()` sets a single shared data pool for all unmatched SELECT queries. Old tests that include `'total' => N` in every row still function correctly — no migration needed.

**`MockPreparedStatement` interpolates bound params back into SQL.** `BaseMysqliRepository::fetchOne/fetchAll` uses `prepare()` → `bind_param()` → `execute()`. `MockPreparedStatement::execute()` calls `replacePlaceholders()`, which substitutes `?` with the bound values before passing the final SQL to `MockDatabase::sql_query()`. This means `onQuery('Player One', ...)` CAN distinguish two calls to the same SQL with different bound names — e.g., `WHERE p.name = 'Player One' LIMIT 1` vs `WHERE p.name = 'Player Two' LIMIT 1`:
```php
$this->mockDb->onQuery('Player One', [$player1]);
$this->mockDb->onQuery('Player Two', [$player2]);
```

### MockDatabase `insert_id` Limitation

`MockDatabase` extends `\mysqli` without a real connection. Accessing `$db->insert_id` (used by `BaseMysqliRepository::getLastInsertId()`) throws "object is already closed". Tests for code paths that INSERT and read `insert_id` (e.g., `createSavedDepthChart()`) cannot use MockDatabase — use DB integration tests instead.

## Module Entry Point Tests

For testing module `index.php` files end-to-end in PHPUnit, extend `ModuleEntryPointTestCase`:

```php
class ScheduleEntryPointTest extends ModuleEntryPointTestCase
{
    public function testHandlesInvalidTeamID(): void
    {
        $output = $this->runModule('Schedule', get: ['teamID' => 'abc']);
        $this->assertStringContainsString('Schedule', $output);
    }
}
```

- Extend `Tests\Module\EntryPoints\ModuleEntryPointTestCase` (which extends `WideUnitTestCase`)
- Use `$this->runModule('ModuleName', get: [...], post: [...])` to include the module's `index.php` and capture output
- Use `$this->authenticateAs('username')` to simulate an authenticated user
- Lives in `tests/Module/EntryPoints/`, registered under the "Module Tests" testsuite
- The class handles double output buffering for `PageLayout::footer()`'s `ob_end_flush()` — do not wrap `runModule()` in your own `ob_start()`
- **Use the HTML form field name, not the validator output key.** If `AwardHistoryValidator` reads `$params['aw_name']`, the POST array must use `['aw_name' => ...]` — not `['name' => ...]` (the validator's output key). Using the wrong key silently drops the filter (the `??null` fallback fires), the query runs unfiltered, and `assertQueryExecuted()` still passes — a false-positive that hides the bug.

## Mutation Testing

Mutation testing (Infection PHP) runs in three modes:

1. **Per-PR diff** — on every PR that touches `classes/**/*.php`. Scopes mutations to changed lines only via `--git-diff-filter=AM --git-diff-lines`. Fast (~minutes). Posts a PR comment with the summary.
2. **Weekly full suite** — Monday 03:00 UTC (`schedule`). Runs the full Infection suite.
3. **On-demand full suite** — apply the `mutation-test` label to a PR. Runs the full Infection suite.

Current thresholds: **100% MSI / 100% Covered MSI**. See `memory/ci-quality-gates.md` for full details.

## Completion Criteria

**IMPORTANT:** Before considering ANY task involving PHP code complete:

1. **Run the FULL test suite**: `composer test` (from `ibl5/`) — never use `--testsuite` or `--filter` as the final verification. Changes in one module frequently break tests in other modules (shared mocks, interfaces, base classes).
2. **When DB-touching code changed**: Run `composer test:all` instead (requires Docker MariaDB with env vars `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
3. **Verify clean output**: The final line must show `OK (X tests, Y assertions)` with NO warnings, failures, or errors
4. **Check for warnings**: If output shows `OK, but there were issues!`, run `--display-all-issues` and FIX the warnings
5. **Don't silence warnings**: Resolve root causes instead of suppressing warnings (unless truly necessary)

**When to use targeted suites:** Only use `--testsuite` or `--filter` when actively debugging a specific failing test to get faster feedback. Once the targeted test passes, immediately re-run the full suite.

Requirements:
- Zero warnings, zero failures, zero errors
- No skipped tests
- All public methods tested

