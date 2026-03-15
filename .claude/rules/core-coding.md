# Core Coding Reference

Quick reference for frequently-used patterns not covered in CLAUDE.md.

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

## Common Gotchas

| Issue | Correct Approach |
|-------|------------------|
| Contract year salary | Use `PlayerContractCalculator::getCurrentSeasonSalary()` for typed access. For raw arrays: if `cy=2`, read `cy2` field (not `cy1`) |
| Native types enabled | `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` is on — INT columns return PHP `int`, VARCHAR columns return PHP `string`. Compare accordingly: `=== 0` for INT, `=== '0'` for VARCHAR |
| Retired players | `retired` is TINYINT — check `retired === 0` (int) |
| Free agents | `tid` is INT — check `tid === 0` or empty username |
| Team lookup | Some methods use `tid` (int), others use team name (string) |
| Querying "all teams" | `ibl_team_info` contains special teams beyond the 28 real franchises: Rookies (40), Sophomores (41), All-Star Away (50), All-Star Home (51), and Free Agents (0). When querying all league teams (dropdowns, standings, rosters, record books, etc.), always use `WHERE teamid BETWEEN 1 AND League::MAX_REAL_TEAMID` — not just `WHERE teamid <> 0`. Only include teamid 0 (Free Agents/Waivers) or the All-Star/Rookie IDs when there is a specific reason to (e.g., free agency lists, All-Star voting). See `League::getAllTeamsResult()` for the canonical pattern. |
| Null in queries | Build conditional SQL; `bind_param` has no NULL type |
| Database booleans (INT cols) | `hasMLE === 1`, `hasLLE === 1` (native int) |
| Division guards | Use `=== 0` or `=== 0.0`, not `== 0` |
