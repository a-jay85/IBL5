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
$repo->getPlayerByID(int $pid): ?array
$repo->getTeamnameFromUsername(string $username): string  // Returns "Free Agents" if none
$repo->getTidFromTeamname(string $teamName): int
$repo->getTeamTotalSalary(string $teamName): int
$repo->getTeamDiscordID(string $teamName): ?string
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
| Retired players | Check `retired = '0'` (string, not int) |
| Free agents | `tid = 0` or empty username |
| Team lookup | Some methods use `tid`, others use team name string |
| Null in queries | Build conditional SQL; `bind_param` has no NULL type |

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

## Database Tables

| Purpose | Table | Key Fields |
|---------|-------|------------|
| Players | `ibl_plr` | `pid`, `tid`, `name`, `cy`, `cy1-cy6` |
| Teams | `ibl_team_info` | `teamid`, `team_name` |
| Users | `nuke_users` | `username`, `user_ibl_team` |
| History | `ibl_hist` | Historical player stats |
| Schedule | `ibl_schedule` | Game schedule |

## Environment Commands

**Bun:** The PATH for bun (`~/.bun/bin`) may not be loaded in the shell. Before running `bun` commands, source the shell config first:

```bash
source ~/.zshrc && bun <command>
```
