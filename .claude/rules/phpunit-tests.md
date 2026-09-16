---
description: PHPUnit testing rules: output parsing, behavior-focused patterns, PHPStan test-neon suppressions, integration test seeding.
paths: ibl5/tests/**/*.php
last_verified: 2026-09-16
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
- **NEVER** `setAccessible(true)` on a `ReflectionProperty`/`ReflectionMethod` — a no-op since PHP 8.1 and **deprecated since 8.5** (fatal in PHP 9). `getValue()`/`invoke()` already reach private members; just delete the call. `bin/check-phpunit-hygiene` enforces this.
- **NEVER** assert full SQL structure (columns, WHERE, bind strings) except in security tests. For void writes, `assertQueryExecuted('table_name')` verifies the target table was hit — don't match beyond the table name.
- **NEVER** wrap the system under test in a broad empty catch (`catch (\Throwable) {}`, `\Exception`, `\Error`) — it swallows every failure including a fatal on the first line, leaving a test that cannot fail. Narrow the caught type, use `expectException()`, or assert on post-state inside the catch. A comment-only body counts as empty. `RequireMeaningfulAssertionsRule` (in `composer run analyse:tests`) enforces this.

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

## PHPStan test-neon suppressions

- **Always `self::createStub()`, never `$this->createStub()`.** `phpstan-tests.neon` flags `$this->createStub(Foo::class)` as a dynamic call to a static method. Use the static call form for all `TestCase` helpers. When agents generate test classes, audit ALL `$this->createStub(` occurrences and replace with `self::createStub(`. Same applies to `self::createMock()`.
- **`assertInstanceOf(Iface::class, $result)` on an already-typed return value → "always true".** PHPStan flags this when the declared return type already IS the interface. Suppress with `// @phpstan-ignore-next-line (asserting return type as intentional contract test)` — do NOT remove the assertion (it pins the architecture) or baseline it.
- **Anonymous-class test doubles must be inline locals, never a typed helper's return.** PHPStan keeps the anon subclass type on a local (`$repo = new class extends EngineShadowRepository { public int $playerInserts = 0; };`) but the wider declared return type of `private function recordingRepo(): EngineShadowRepository` erases it, so `$repo->playerInserts` becomes `Access to undefined property`. Hit three times (PR #914 masked, #935 and #943 CI-red); a sibling test using the inline form was immune. Any recording/spy double that tracks calls via extra public properties gets declared inline.
- **`assertSame($literal, ClassName::CONST)` fires `method.alreadyNarrowedType`.** PHPStan resolves both arguments to the same compile-time literal and treats it as `assertSame($x, $x)`. The test is still valid as a documentation/mutation anchor — it fails if someone edits the const. Suppress in place, immediately above the assertion: `// @phpstan-ignore method.alreadyNarrowedType (const value statically known; assertion guards future edits)`. Do NOT baseline it; the suppression is scoped to the one assertion.
- **`cast.useless` on a DB row value is almost always a real cast — do not remove it.** mysqli (without `MYSQLI_OPT_INT_AND_FLOAT_NATIVE`) returns every column as a string, especially `SUM()`/`COALESCE()` aggregates. PHPStan only flags `(int)$row['x']` because a lying `@var list<array{x: int}>` on the query result claims int. A baseline-burndown sweep that stripped these broke two `#[Group('database')]` tests (`assertSame(1500, '1500')`, `assertSame(205.0, '205')`). Fix in this order: (1) correct the root `@var`/PHPDoc `int`→`string` and KEEP the cast — it is now meaningful, so the error goes away; (2) if the production PHPDoc is correct for production, use `assertEquals` (loose) instead of `assertSame` + cast; (3) `// @phpstan-ignore cast.useless` when the production signature is right at the type level (`COUNT(*)` → int in prod) but the test DB path returns a string. These failures land only in the `#[Group('database')]` job, which default `composer test` skips — verify with `bin/db-test-up <slug>` + `--group database`.

## Integration test FK constraints

- **`ibl_box_scores` has a FK on `pid` → `ibl_plr(pid)`.** Any integration test that inserts box-score rows (directly or via `processAllStarGamesData`) must insert matching `ibl_plr` rows first. Call `$this->insertTestPlayer($pid, $name)` for every PID in the test dataset in `setUp`/`setUpGameState`.
- **Tests checking a CI-seed-gated branch (e.g., all-star cutoff) must mock the gating query.** `Season::getLastBoxScoreDate()` reads `ibl_box_scores` with no year filter; CI seed has modern dates, so the gate never fires. Inject a mock `Season` stub (`createMock` not `createStub` — the latter triggers `staticMethod.dynamicCall` in PHPStan for Season's method). `BoxscoreProcessor` accepts `?Season $season = null` as a named constructor arg.

## `Season\Season` is class-aliased to the mock in every PHPUnit run

`classes/Bootstrap/TestAliasesBootstrap.php` (run by `TestApplicationFactory::build()->boot()` in `tests/bootstrap.php`) does `class_alias('Tests\WideUnit\Mocks\Season', 'Season\Season')`. So in **every** PHPUnit run `new Season\Season($db)` returns the **mock**, which ignores the DB and hardcodes `phase = 'Regular Season'` in its constructor. PHPStan unifies the two via `phpstan-stubs/mock-aliases.stub.php`, so passing a `new \Tests\WideUnit\Mocks\Season(...)` where `Season\Season` is expected is type-clean.

- A class that builds `$this->season = new Season($db)` internally (`Trading\TradeValidator`, `TradeProcessor`, `TradeOffer`) gets the mock with phase pinned to "Regular Season". You **cannot** vary its phase from a test via `onQuery('ibl_settings', ...)` — that routes the real query, but the alias means the real `Season` never runs. Reaching the protected `$season` would need `ReflectionClass`, which this rule bans.
- The **real** `Season` methods (`isOffseasonPhase`, `areTradesAllowed`, …) never execute under PHPUnit; only the mock's mirror runs. Add any new phase predicate to **both** the real class and the mock, keep them byte-identical, and test the predicate through the mock — `new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class))` then set `$season->phase`. Same pattern as `SeasonTest::testAreTradesAllowed`.
- To inject a phase into a method that **takes** a `Season` param, use the mock instance and assign `$season->phase` (its phase-derived methods read `$this->phase`) — not `createStub(Season::class)`, which replaces all methods so every phase predicate returns the `false` default. Precedent: `CleanupPreseasonDataStepTest::buildSeason`.
- **Mutation-gate blind spot:** edits to the real `Season.php` phase logic are alias-shadowed, so they are uncovered yet still merge green (proven by `areTradesAllowed` edits in b90bb00c0 / a4060016e / 6060e5c8a). The per-PR Infection run uses `--git-diff-lines --only-covering-test-cases --ignore-msi-with-no-mutations`, and replacing operator/literal-rich lines with bare method calls drops covered-line mutants to ~zero (`@default` has no `MethodCallRemoval`).
