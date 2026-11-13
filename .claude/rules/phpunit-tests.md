---
description: PHPUnit testing rules: output parsing, behavior-focused patterns, PHPStan test-neon suppressions, integration test seeding. Long-form sections live in phpunit-tests-detail.md (Read on demand).
paths: ibl5/tests/**/*.php
last_verified: 2026-11-12
---

# PHPUnit Testing Rules

## PHPUnit 13+ Syntax (run from `ibl5/`)

```bash
vendor/bin/phpunit # full suite
vendor/bin/phpunit --filter testMethodName # single test
vendor/bin/phpunit --display-all-issues # show ALL issues
vendor/bin/phpunit | tail -n 3 # just the pass/fail summary
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

## Source-text reads

Prefer executing code over reading its text. A test that calls `file_get_contents` on a production `.php`, `.js`, `.css`, or `.htaccess` file breaks on harmless refactors and passes when behavior regresses along a path its string match misses. Call the class, run the script through `proc_open`, or assert on what a mock recorded. A source guard is justified only when no seam exists (a script that exits at top level) or the property is the file's shape (a first-statement CLI guard, an `.htaccess` deny block). `bin/check-phpunit-hygiene` blocks new source-text reads (`source-text-read`). To keep a justified guard, list its path in `.phpunit-source-read-allowlist` with a `#` comment naming the property, or put `// phpunit-hygiene-allow: <reason>` on the read or the line above.

## DON'T

- **NEVER** `createMock()` with no `expects()` — use `createStub()` (mocks without expectations emit a notice).
- **NEVER** `ReflectionClass` for private methods — test via public APIs; if a private method needs direct testing, extract it to a class with a public interface.
- **NEVER** `markTestSkipped()` to silently disable — delete instead. Sole exception: integration-availability skip (service unreachable) with an inline `// phpunit-hygiene-allow: <reason ≥20 chars>` marker; `bin/check-phpunit-hygiene` enforces this.
- **NEVER** `setAccessible(true)` on a `ReflectionProperty`/`ReflectionMethod` — a no-op since PHP 8.1 and **deprecated since 8.5** (fatal in PHP 9). `getValue()`/`invoke()` already reach private members; just delete the call. `bin/check-phpunit-hygiene` enforces this.
- **NEVER** assert that a class constant equals its own declared value, and never add an `alreadyNarrowedType` ignore over a `ClassName::CONST`. See the constant-restatement bullet under the PHPStan notes. `bin/check-phpunit-hygiene` enforces this.
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

Moved to `.claude/rules/phpunit-tests-detail.md` § WideUnit + MockDatabase. Extend `WideUnitTestCase` for tests that need `$this->mockDb`.

## Module Entry Point Tests

Moved to `.claude/rules/phpunit-tests-detail.md` § Module Entry Point Tests. Extend `Tests\Module\EntryPoints\ModuleEntryPointTestCase`, in `tests/Module/EntryPoints/`.

## Mutation Testing (Infection)

Mutation testing (Infection): see `phpunit-tests-detail.md`.

## Completion Criteria

Before considering ANY PHP task complete:

1. **Run the tests mapped to your changed files.** `bin/lib/prefire-verify --stage phpunit` picks them, and `bin/post-plan-now` runs the same stage before it ships (ADR-0241). Do not run the full suite locally. It takes about 3 minutes, and CI's `PHPUnit Tests` job runs it on every PR. A change to a shared base class, helper or PHPUnit config has no mapped tests, so it defers to CI. Set `PREFIRE_PHPUNIT_FULL=1` only when you need the full result before you push.
2. **DB-touching code.** `phpunit.xml` excludes the `database` group, so the stage above skips integration tests. Run the ones you touched with `vendor/bin/phpunit --group database <test file>` (needs Docker MariaDB and `DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME`). CI's `Database Integration Tests` job runs the whole group.
3. Final line must read `OK (X tests, Y assertions)`, with zero warnings, failures, errors, or skips. One skip is expected on the macOS host, so `OK, but some tests were skipped!` with `Skipped: 1` passes there. That test is `ShadowProcessLauncherTest::launchReturnsImmediatelyWhileChildRunsDetached`.
   It needs Linux `setsid`, which macOS lacks. It runs for real in CI and in the php container, and it fails in CI if `setsid` is missing. Don't investigate it. A second skip, or a skip anywhere in CI, is real and needs fixing.
4. If `OK, but there were issues!`, run `--display-all-issues` and FIX root causes (don't suppress).

Use `--testsuite`/`--filter` for fast feedback while debugging a specific failure. Re-run the mapped tests once it passes.

## PHPStan test-neon suppressions

Moved to `.claude/rules/phpunit-tests-detail.md` § PHPStan test-neon suppressions. Always `self::createStub()`, never `$this->createStub()`, because `phpstan-tests.neon` flags the dynamic call.

## Integration test FK constraints

- **`ibl_box_scores` has a FK on `pid` → `ibl_plr(pid)`.** Any integration test that inserts box-score rows (directly or via `processAllStarGamesData`) must insert matching `ibl_plr` rows first. Call `$this->insertTestPlayer($pid, $name)` for every PID in the test dataset in `setUp`/`setUpGameState`.
- **Tests checking a CI-seed-gated branch (e.g., all-star cutoff) must mock the gating query.** `Season::getLastBoxScoreDate()` reads `ibl_box_scores` with no year filter; CI seed has modern dates, so the gate never fires. Inject a mock `Season` stub (`createMock` not `createStub` — the latter triggers `staticMethod.dynamicCall` in PHPStan for Season's method). `BoxscoreProcessor` accepts `?Season $season = null` as a named constructor arg.

## `Season\Season` is class-aliased to the mock in every PHPUnit run

Moved to `.claude/rules/phpunit-tests-detail.md` § `Season\Season` is class-aliased to the mock in every PHPUnit run. In every PHPUnit run `new Season\Season($db)` returns the mock, so the real `Season` methods never execute.

## Golden snapshots

Create or regenerate a golden snapshot with `GoldenSnapshotTrait` and `UPDATE_SNAPSHOTS=1`. Five golden helpers skip the trait and have their own switches. Read `.claude/rules/phpunit-tests-detail.md` before you touch a golden test.
