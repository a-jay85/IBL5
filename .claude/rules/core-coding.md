# Core Coding Reference

Quick reference for frequently-used patterns not covered in CLAUDE.md.

## Key Constants

```php
// Salary Cap (in thousands)
\League::HARD_CAP_MAX  // 7000

// Contract Rules
\ContractRules::BIRD_RIGHTS_THRESHOLD        // 3 years
\ContractRules::STANDARD_RAISE_PERCENTAGE    // 0.10 (10%)
\ContractRules::BIRD_RIGHTS_RAISE_PERCENTAGE // 0.125 (12.5%)
\ContractRules::getVeteranMinimumSalary($experience)
\ContractRules::getMaxContractSalary($experience)
\ContractRules::hasBirdRights($birdYears)
```

## Common Repository Helpers

`CommonMysqliRepository` provides these frequently-used queries:

```php
$repo->getUserByUsername(string $username): ?array
$repo->getTeamByName(string $teamName): ?array
$repo->getPlayerByID(int $playerID): ?array
$repo->getTeamnameFromUsername(?string $username): ?string  // Returns "Free Agents" if null/empty; null if not found
$repo->getTidFromTeamname(string $teamName): ?int
$repo->getTeamTotalSalary(string $teamName): int
$repo->getTeamDiscordID(string $teamName): ?int
```

## Validation Return Patterns

**Standard pattern** (CommonValidator, TradeValidator):
```php
['valid' => true, 'error' => null]        // Success
['valid' => false, 'error' => 'Message']  // Failure
```

**Validator class pattern** (DraftValidator, WaiversValidator):
```php
if (!$validator->validateX(...)) {
    $errors = $validator->getErrors();  // Returns array of error strings
}
```

## Common Gotchas

| Issue | Correct Approach |
|-------|------------------|
| Contract year salary | If `cy=2`, read `cy2` field (not `cy1`) |
| Native types enabled | `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` is on — INT columns return PHP `int`, VARCHAR columns return PHP `string`. Compare accordingly: `=== 0` for INT, `=== '0'` for VARCHAR |
| Retired players | `retired` is TINYINT — check `retired === 0` (int) |
| Free agents | `tid` is INT — check `tid === 0` or empty username |
| Team lookup | Some methods use `tid` (int), others use team name (string) |
| Null in queries | Build conditional SQL; `bind_param` has no NULL type |
| Database booleans (INT cols) | `hasMLE === 1`, `hasLLE === 1` (native int) |
| Database booleans (VARCHAR cols) | Check schema first — use `=== '1'` only for VARCHAR boolean columns |
| Trade itemtype | `itemtype` is VARCHAR — compare with `=== '0'`, `=== '1'`, `=== 'cash'` |
| Division guards | Use `=== 0` or `=== 0.0`, not `== 0` |
| Sticky columns + overflow | Never set `overflow: hidden` on a table that uses `position: sticky` cells — it breaks sticky. Use `.ibl-data-table:not(.responsive-table)` for overflow clipping so `.responsive-table` tables (which have sticky columns) are excluded |
| PHP-Nuke functions & PHPStan | Functions like `is_user()`, `getusrinfo()`, `cookiedecode()` are defined in `mainfile.php`. Before using one in a class, check `phpstan-stubs/nuke-globals.stub.php` — if the function isn't stubbed, add it or PHPStan will report `function.notFound` |
| `safeHtmlOutput()` returns `mixed` | `HtmlSanitizer::safeHtmlOutput()` has return type `mixed`. When concatenating its result into a string, add `/** @var string */` on the variable or PHPStan reports `binaryOp.invalid` |

## Testing Quick Reference

```php
// Integration test setup
class MyTest extends IntegrationTestCase {
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

### MockDatabase returns the SAME data for ALL queries

`MockDatabase::setMockData()` sets one shared data pool. Every `SELECT` query (via `sql_query()` → `MockPreparedStatement::execute()`) returns the same `mockData` rows. This means:

**Problem:** When a controller calls both `countX()` (runs `SELECT COUNT(*) AS total`) and `getX()` (runs `SELECT * FROM ...`), both queries get the same mock rows. The COUNT query's `fetchOne()` returns the first data row (not a `{total: N}` row), and `$row['total']` fails with "Undefined array key" → returns null → `TypeError` on the `: int` return type.

**Fix:** Include `'total' => N` in mock data rows so the COUNT query finds it:
```php
$this->mockDb->setMockData([
    [
        'uuid' => 'test-uuid',
        'name' => 'Test',
        // ... domain data ...
        'total' => 1, // Mock COUNT(*) result reuses same data
    ],
]);
```

**When this matters:** Any controller test where the controller calls both a `count*()` method and a `get*()` / `fetch*()` method on the same repository (i.e., paginated list controllers like `PlayerListController`, `GameListController`, `LeadersController`). Unpaginated controllers (e.g., `StandingsController`, `InjuriesController`) don't call count methods and aren't affected.

## Database Tables

| Purpose | Table | Key Fields |
|---------|-------|------------|
| Players | `ibl_plr` | `pid`, `tid`, `name`, `cy`, `cy1-cy6` |
| Teams | `ibl_team_info` | `teamid`, `team_name` |
| Users | `nuke_users` | `username`, `user_ibl_team` |
| History | `ibl_hist` | Historical player stats |
| Schedule | `ibl_schedule` | Game schedule |

## Environment Commands

**Bun:** The PATH for bun (`~/.bun/bin`) may not be loaded in the shell. Before running `bun` commands, source the shell config first.

**CSS Development (Tailwind 4):**
```bash
# DEVELOPMENT: Auto-rebuilds on save
source ~/.zshrc && bun run css:watch

# LOCAL BUILDS: Rebuild CSS without minification (for commits)
source ~/.zshrc && bunx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css
```

- **NEVER use `--minify` locally.** Minification is handled by GitHub Actions on merge/push.
- **NEVER commit `themes/IBL/style/style.css`.** It is gitignored and built on production. Only commit the source CSS files in `design/`.

**IBLbot (Discord Bot):**
```bash
# Build the TypeScript bot — must run from IBLbot directory, NOT from ibl5/
cd /Users/ajaynicolas/Documents/GitHub/IBL5/ibl5/IBLbot && npm run build
```
- CWD is usually `ibl5/` so bare `npm run build` will fail ("Missing script: build"). Always `cd` to the IBLbot directory first.
