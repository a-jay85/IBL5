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
