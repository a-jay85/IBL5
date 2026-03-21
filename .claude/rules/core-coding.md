# Core Coding Reference

Quick reference for frequently-used patterns not covered in CLAUDE.md.

## Common Repository Helpers

`CommonMysqliRepository` provides these frequently-used queries:

```php
$repo->getUserByUsername(string $username): ?array
$repo->getTeamByName(string $teamName): ?array
$repo->getPlayerByID(int $playerID): ?array
$repo->getTeamnameFromUsername(?string $username): ?string  // Returns League::FREE_AGENTS_TEAM_NAME if null/empty; null if not found
$repo->getTidFromTeamname(string $teamName): ?int
$repo->getTeamTotalSalary(string $teamName): int
$repo->getTeamDiscordID(string $teamName): ?int
```

## Common Gotchas

| Issue | Correct Approach |
|-------|------------------|
| Contract year salary | Use `PlayerContractCalculator::getCurrentSeasonSalary()` for typed access. For raw arrays: if `cy=2`, read `cy2` field (not `cy1`) |
| Native types | INT columns return PHP `int`, VARCHAR returns `string`. Compare accordingly: `=== 0` for INT, `=== '0'` for VARCHAR |
| Retired players | `retired` is TINYINT — check `retired === 0` (int) |
| Free agents | `tid` is INT — check `tid === 0` or empty username |
| Team lookup | Some methods use `tid` (int), others use team name (string) |
| Querying "all teams" | Use `League::isRealFranchise($id)` in PHP or `WHERE teamid BETWEEN 1 AND League::MAX_REAL_TEAMID` in SQL. Special team constants: `FREE_AGENTS_TEAMID(0)`, `ROOKIES_TEAMID(40)`, `SOPHOMORES_TEAMID(41)`, `ALL_STAR_AWAY_TEAMID(50)`, `ALL_STAR_HOME_TEAMID(51)`. |
| Null in queries | Build conditional SQL; `bind_param` has no NULL type |
| Database booleans (INT cols) | `hasMLE === 1`, `hasLLE === 1` (native int) |
| Division guards | Use `=== 0` or `=== 0.0`, not `== 0` |
