---
description: Read-on-demand only (no auto-attach trigger). Golden snapshot rules (GoldenSnapshotTrait, UPDATE_SNAPSHOTS=1, the five golden helpers that skip the trait) plus the long-form sections moved from phpunit-tests.md: WideUnit + MockDatabase, module entry point tests, PHPStan test-neon suppressions, and the Season class alias.
last_verified: 2026-11-11
paths: "~/.claude/hooks/plan-gate-edit.sh"
---

# PHPUnit Tests Detail

Companion to `phpunit-tests.md`. Read it on demand; it attaches on no path.

## Golden snapshots

Golden snapshot tests compare rendered output to a committed file under a `__snapshots__` directory beside the test. Use the shared trait `Tests\Support\GoldenSnapshotTrait` (`ibl5/tests/Support/GoldenSnapshotTrait.php`): add `use Tests\Support\GoldenSnapshotTrait;` and `use GoldenSnapshotTrait;` in the class, then call `$this->assertSnapshotMatches($actual, 'name.html')`.

- **A missing snapshot fails the test.** The message says to run with `UPDATE_SNAPSHOTS=1`. A helper that writes the file when it is absent lets a deleted or misnamed snapshot pass green, so never write one.
- **Create or regenerate with the switch.** From `ibl5/`, run `UPDATE_SNAPSHOTS=1 vendor/bin/phpunit <test path>`. Only the literal value `1` writes; unset, empty, and `0` do not. With the switch on, the helper creates the directory, overwrites the file whether or not it exists, and skips the comparison. Review `git diff` on the `__snapshots__` files, then commit them with the test change.
- **Switch placement.** Keep the switch out of `ibl5/phpunit.xml`, CI, and shell profiles. Without it the comparison is an exact `assertSame` string match.
- **Default directory.** It is `__snapshots__` next to the test file. Override `protected function snapshotDirectory(): string` only when snapshots live elsewhere.

Five other golden helpers do not use the trait. Each already fails on a missing file and has its own switch:

| Test file | Switch |
|-----------|--------|
| `ibl5/tests/Module/ThemeIblOutputTest.php` | `THEME_GOLDEN_WRITE=1` |
| `ibl5/tests/FreeAgency/FreeAgencyViewTest.php` | `IBL_GOLDEN_CAPTURE=1` |
| `ibl5/tests/Trading/TradingViewGoldenMasterTest.php` | `IBL_GOLDEN_CAPTURE=1` |
| `ibl5/tests/Trading/TradeOfferFormViewTest.php` | `IBL_GOLDEN_CAPTURE=1` |
| `ibl5/tests/DatabaseIntegration/TeamServicePageDataCharacterizationTest.php` | `CAPTURE_GOLDENS=1` |

## Mutation Testing (Infection)

1. **Per-PR diff.** Every PR touching `classes/**/*.php`; scopes to changed lines (`--git-diff-filter=AM --git-diff-lines`), ~minutes, posts a PR comment.
2. **Weekly full.** Mon 03:00 UTC.
3. **On-demand full.** Apply the `mutation-test` label.

Thresholds: **100% MSI / 100% Covered MSI** (`ibl5/infection.json5`, `.github/workflows/mutation-pr.yml`). Config: `phpunit-mutation.xml`; per-file deferrals live in `infection.json5`.

**Local run.** `bin/mutation-diff <base-ref>`; details in `.claude/rules/phpstan-invocation.md`.

## WideUnit + MockDatabase

```php
class MyTest extends WideUnitTestCase {
    protected function setUp(): void { parent::setUp(); }  // sets up $this->mockDb
}
$player = TestDataFactory::createPlayer(['pid' => 1, 'name' => 'Test']);
$this->assertQueryExecuted('UPDATE ibl_plr');
$this->assertQueryNotExecuted('DELETE');
```

**Query routing — prefer `onQuery()`** over `setMockData()` when the code-under-test runs multiple different queries (e.g. a paginated controller calling both `countX()` and `getX()`): <!-- slop-ok -->
```php
$this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);   // regex, case-insensitive, checked FIRST
$this->mockDb->setMockData([['pid' => 1, 'name' => 'Player']]);
```
- `setMockData()` (legacy) = single shared pool for unmatched SELECTs; old rows with `'total' => N` still work, no migration needed.
- **`MockPreparedStatement` interpolates bound params back into SQL** (`replacePlaceholders()` substitutes `?` before `MockDatabase::sql_query()`), so `onQuery()` CAN distinguish two calls to the same SQL with different bound values — `onQuery('Player One', [$p1])` vs `onQuery('Player Two', [$p2])`. <!-- slop-ok -->
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
- The class handles double output buffering for `PageLayout::footer()`'s `ob_end_flush()` — do NOT wrap `runModule()` in your own `ob_start()`. <!-- slop-ok -->
- **Use the HTML form field name, not the validator output key.** If `AwardHistoryValidator` reads `$params['aw_name']`, POST must use `['aw_name' => ...]`, not `['name' => ...]`. Wrong key → the `??null` fallback fires, the query runs unfiltered, and `assertQueryExecuted()` still passes — a false positive hiding the bug. <!-- slop-ok -->

## PHPStan test-neon suppressions

- **Always `self::createStub()`, never `$this->createStub()`.** `phpstan-tests.neon` flags `$this->createStub(Foo::class)` as a dynamic call to a static method. Use the static call form for all `TestCase` helpers. When agents generate test classes, audit ALL `$this->createStub(` occurrences and replace with `self::createStub(`. Same applies to `self::createMock()`.
- **`assertInstanceOf(Iface::class, $result)` on an already-typed return value → "always true".** PHPStan flags this when the declared return type already IS the interface. Suppress with `// @phpstan-ignore-next-line (asserting return type as intentional contract test)` — do NOT remove the assertion (it pins the architecture) or baseline it. <!-- slop-ok -->
- **Anonymous-class test doubles must be inline locals, never a typed helper's return.** PHPStan keeps the anon subclass type on a local (`$repo = new class extends EngineShadowRepository { public int $playerInserts = 0; };`) but the wider declared return type of `private function recordingRepo(): EngineShadowRepository` erases it, so `$repo->playerInserts` becomes `Access to undefined property`. Hit three times (PR #914 masked, #935 and #943 CI-red); a sibling test using the inline form was immune. Any recording/spy double that tracks calls via extra public properties gets declared inline. <!-- slop-ok -->
- **Constant restatement.** `assertSame($literal, ClassName::CONST)` restates the declaration, so PHPStan reports `method.alreadyNarrowedType` (or `staticMethod.alreadyNarrowedType`). That report is correct. Delete the assertion and never suppress the identifier. The assertion fails only when someone edits the constant, and that edit updates the test in the same diff. Assert the behavior that reads the constant instead. A default argument shows up in a mock's `with()` matcher. A SQL fragment runs against the real schema in a `#[Group('database')]` test. Mutation score gives no reason to keep a restatement: a 2026-10-11 Infection 0.35.6 run over `DataRetentionService` and `RecordStatDefinitions` produced zero mutants on class-constant declaration lines. `bin/check-phpunit-hygiene` (`const-restatement`) blocks the suppression and the single-line literal form.
- **`cast.useless` on a DB row value is almost always a real cast — do not remove it.** mysqli (without `MYSQLI_OPT_INT_AND_FLOAT_NATIVE`) returns every column as a string, especially `SUM()`/`COALESCE()` aggregates. PHPStan only flags `(int)$row['x']` because a lying `@var list<array{x: int}>` on the query result claims int. A baseline-burndown sweep that stripped these broke two `#[Group('database')]` tests (`assertSame(1500, '1500')`, `assertSame(205.0, '205')`). Fix in this order: (1) correct the root `@var`/PHPDoc `int`→`string` and KEEP the cast — it is now meaningful, so the error goes away; (2) if the production PHPDoc is correct for production, use `assertEquals` (loose) instead of `assertSame` + cast; (3) `// @phpstan-ignore cast.useless` when the production signature is right at the type level (`COUNT(*)` → int in prod) but the test DB path returns a string. These failures land only in the `#[Group('database')]` job, which default `composer test` skips — verify with `bin/db-test-up <slug>` + `--group database`. <!-- slop-ok -->

## `Season\Season` is class-aliased to the mock in every PHPUnit run

`classes/Bootstrap/TestAliasesBootstrap.php` (run by `TestApplicationFactory::build()->boot()` in `tests/bootstrap.php`) does `class_alias('Tests\WideUnit\Mocks\Season', 'Season\Season')`. So in **every** PHPUnit run `new Season\Season($db)` returns the **mock**, which ignores the DB and hardcodes `phase = 'Regular Season'` in its constructor. PHPStan unifies the two via `phpstan-stubs/mock-aliases.stub.php`, so passing a `new \Tests\WideUnit\Mocks\Season(...)` where `Season\Season` is expected is type-clean.

- A class that builds `$this->season = new Season($db)` internally (`Trading\TradeValidator`, `TradeProcessor`, `TradeOffer`) gets the mock with phase pinned to "Regular Season". You **cannot** vary its phase from a test via `onQuery('ibl_settings', ...)` — that routes the real query, but the alias means the real `Season` never runs. Reaching the protected `$season` would need `ReflectionClass`, which this rule bans. <!-- slop-ok -->
- The **real** `Season` methods (`isOffseasonPhase`, `areTradesAllowed`, …) never execute under PHPUnit; only the mock's mirror runs. Add any new phase predicate to **both** the real class and the mock, keep them byte-identical, and test the predicate through the mock — `new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class))` then set `$season->phase`. Same pattern as `SeasonTest::testAreTradesAllowed`. <!-- slop-ok -->
- To inject a phase into a method that **takes** a `Season` param, use the mock instance and assign `$season->phase` (its phase-derived methods read `$this->phase`) — not `createStub(Season::class)`, which replaces all methods so every phase predicate returns the `false` default. Precedent: `CleanupPreseasonDataStepTest::buildSeason`. <!-- slop-ok -->
- **Mutation-gate blind spot:** edits to the real `Season.php` phase logic are alias-shadowed, so they are uncovered yet still merge green (proven by `areTradesAllowed` edits in b90bb00c0 / a4060016e / 6060e5c8a). The per-PR Infection run uses `--git-diff-lines --only-covering-test-cases --ignore-msi-with-no-mutations`, and replacing operator/literal-rich lines with bare method calls drops covered-line mutants to ~zero (`@default` has no `MethodCallRemoval`).
