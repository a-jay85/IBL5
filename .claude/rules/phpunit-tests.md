---
description: PHPUnit testing rules: output parsing, behavior-focused patterns.
paths: ibl5/tests/**/*.php
last_verified: 2026-06-11
---

# PHPUnit Testing Rules

## PHPUnit 13+ Syntax (run from `ibl5/`)

```bash
vendor/bin/phpunit                          # full suite
vendor/bin/phpunit --filter testMethodName  # single test
vendor/bin/phpunit --display-all-issues     # show ALL issues (deprecations/warnings/notices)
vendor/bin/phpunit | tail -n 3              # token-saving: just the pass/fail summary
```

`-v` / `--verbose` do NOT exist in 13.x. By default only summary counts show — use `--display-all-issues` (or `--display-deprecations`/`-warnings`/`-notices`) for details.

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
        $result = $this->service->publicMethod(['key' => 'value']);   // Arrange + Act
        $this->assertTrue($result->isValid());                        // Assert
    }

    /** @dataProvider invalidInputProvider */
    public function testRejectsInvalidInput(mixed $input, string $expectedError): void
    {
        $result = $this->service->validate($input);
        $this->assertStringContainsString($expectedError, $result->getError());
    }

    public static function invalidInputProvider(): array
    {
        return ['empty string' => ['', 'cannot be empty'], 'negative' => [-1, 'must be positive']];
    }
}
```

## DO

- Test behaviors through public APIs only; one behavior per test; descriptive names.
- Use data providers for similar cases; `@see` not `{@inheritdoc}`.

## DON'T

- **NEVER** `createMock()` with no `expects()` — use `createStub()` (mocks without expectations emit a notice).
- **NEVER** `ReflectionClass` for private methods — test via public APIs; if a private method needs direct testing, extract it to a class with a public interface.
- **NEVER** `markTestSkipped()` to silently disable — delete instead. Sole exception: integration-availability skip (service unreachable) with an inline `// phpunit-hygiene-allow: <reason ≥20 chars>` marker; `bin/check-phpunit-hygiene` enforces this.
- **NEVER** assert full SQL structure (columns, WHERE, bind strings) except in security tests. For void writes, `assertQueryExecuted('table_name')` verifies the target table was hit — don't match beyond the table name.

## Mock vs Stub

`createStub()` for canned return values (no `expects()`); `createMock()` only to verify interactions with `expects()`.

```php
$repo = $this->createStub(RepositoryInterface::class);
$repo->method('findById')->willReturn($entity);

$repo = $this->createMock(RepositoryInterface::class);
$repo->expects($this->once())->method('save')->with($entity);
```

**Shared `setUp()` with 3+ deps:** every test not calling `expects()` on every shared mock emits a notice. Create all **stubs** in `setUp()` and use a `buildService()` helper with nullable overrides; a test needing an expectation passes a mock in:

```php
protected function setUp(): void
{
    $this->stubRepo = $this->createStub(RepoInterface::class);
    $this->service = $this->buildService();
}
private function buildService(RepoInterface|null $repo = null): MyService
{
    return new MyService($repo ?? $this->stubRepo);
}
public function testSaveDelegates(): void
{
    $mockRepo = $this->createMock(RepoInterface::class);
    $mockRepo->expects($this->once())->method('save');
    $this->service = $this->buildService(repo: $mockRepo);
}
```

## Repository Write Methods

`BaseMysqliRepository::getAffectedRows()` is protected — override it in a test subclass to control `execute()`'s return, enabling direct unit tests of write methods without a real DB.

## Test Registration

Register in `ibl5/phpunit.xml`:
```xml
<testsuite name="ModuleName Tests"><directory>tests/ModuleName</directory></testsuite>
```

## WideUnit + MockDatabase

```php
class MyTest extends WideUnitTestCase {
    protected function setUp(): void { parent::setUp(); }  // sets up $this->mockDb
}
$player = TestDataFactory::createPlayer(['pid' => 1, 'name' => 'Test']);
$this->assertQueryExecuted('UPDATE ibl_plr');
$this->assertQueryNotExecuted('DELETE');
```

**Query routing — prefer `onQuery()`** over `setMockData()` when the code-under-test runs multiple different queries (e.g. a paginated controller calling both `countX()` and `getX()`):
```php
$this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);   // regex, case-insensitive, checked FIRST
$this->mockDb->setMockData([['pid' => 1, 'name' => 'Player']]);
```
- `setMockData()` (legacy) = single shared pool for unmatched SELECTs; old rows with `'total' => N` still work, no migration needed.
- **`MockPreparedStatement` interpolates bound params back into SQL** (`replacePlaceholders()` substitutes `?` before `MockDatabase::sql_query()`), so `onQuery()` CAN distinguish two calls to the same SQL with different bound values — `onQuery('Player One', [$p1])` vs `onQuery('Player Two', [$p2])`.
- **`insert_id` limitation:** `MockDatabase` has no real connection; reading `$db->insert_id` (via `getLastInsertId()`) throws "object is already closed". Code that INSERTs and reads `insert_id` (e.g. `createSavedDepthChart()`) needs DB integration tests, not MockDatabase.

## Module Entry Point Tests

Extend `Tests\Module\EntryPoints\ModuleEntryPointTestCase` (extends `WideUnitTestCase`), in `tests/Module/EntryPoints/` under the "Module Tests" suite:
```php
class ScheduleEntryPointTest extends ModuleEntryPointTestCase {
    public function testHandlesInvalidTeamID(): void {
        $output = $this->runModule('Schedule', get: ['teamID' => 'abc']);
        $this->assertStringContainsString('Schedule', $output);
    }
}
```
- `runModule('ModuleName', get: [...], post: [...])` includes the module's `index.php` and captures output; `authenticateAs('username')` simulates auth.
- The class handles double output buffering for `PageLayout::footer()`'s `ob_end_flush()` — do NOT wrap `runModule()` in your own `ob_start()`.
- **Use the HTML form field name, not the validator output key.** If `AwardHistoryValidator` reads `$params['aw_name']`, POST must use `['aw_name' => ...]`, not `['name' => ...]`. Wrong key → the `??null` fallback fires, the query runs unfiltered, and `assertQueryExecuted()` still passes — a false positive hiding the bug.

## Mutation Testing (Infection)

1. **Per-PR diff** — every PR touching `classes/**/*.php`; scopes to changed lines (`--git-diff-filter=AM --git-diff-lines`), ~minutes, posts a PR comment.
2. **Weekly full** — Mon 03:00 UTC.
3. **On-demand full** — apply the `mutation-test` label.

Thresholds: **100% MSI / 100% Covered MSI**. Details in `memory/ci-quality-gates.md`.

## Completion Criteria

Before considering ANY PHP task complete:

1. **Run the FULL suite**: `composer test` (from `ibl5/`) — never `--testsuite`/`--filter` as final verification; changes in one module frequently break others (shared mocks/interfaces/base classes).
2. **DB-touching code** → `composer test:all` instead (needs Docker MariaDB + `DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME`).
3. Final line must read `OK (X tests, Y assertions)` — zero warnings, failures, errors, skips.
4. If `OK, but there were issues!`, run `--display-all-issues` and FIX root causes (don't suppress).

Use `--testsuite`/`--filter` only for fast feedback while debugging a specific failure — re-run the full suite once it passes.
