---
description: Axis-A SQL injection audit of the IBL5 codebase (backlog #668), site-by-site verdicts and the fixes shipped with it.
last_verified: 2026-09-22
---

# SQL injection audit (Axis A) 2026-09-22

Backlog #668 asked whether raw request data reaches a SQL string anywhere in the
codebase. These four commands enumerated the surface:

```bash
grep -rn "sql_query\s*(" ibl5/ --include="*.php" | grep -v vendor | grep -v tests/
grep -rn "real_escape_string\s*(\|escapeString\s*(" ibl5/classes ibl5/modules --include="*.php"
grep -rn '"\s*\.\s*\$' ibl5/classes ibl5/modules --include="*.php" | grep -i "select\|where\|order by"
grep -rn "ORDER BY\s*\"\s*\.\|LIMIT\s*\"\s*\." ibl5/classes ibl5/modules --include="*.php"
```

## Verdicts

| Site | Verdict | Reasoning |
|------|---------|-----------|
| `sql_query()` production call sites | CLEAN | The enumeration returns zero lines. The only definition is the test mock in `ibl5/tests/WideUnit/Mocks/MockDatabase.php`. |
| `ibl5/classes/SeasonHighs/SeasonHighsRepository.php` null-fallback on the stat-name alias | NOT INJECTABLE, fail-open defect | `preg_replace('/[^a-zA-Z0-9_]/', '', $statName)` carries no `/u` modifier, so invalid UTF-8 cannot make it return null; null needs a PCRE engine error, unreachable for a single character class. The stat name is a key of the `SeasonHighsService` class constants. The fallback still inverted the guard: the one branch firing on sanitizer failure re-admitted the raw input. |
| `ibl5/classes/SeasonHighs/SeasonHighsRepository.php` raw stat-expression concatenation | NOT INJECTABLE, unguarded fragment | The only caller is `ibl5/classes/SeasonHighs/SeasonHighsService.php`, sourcing expressions from private class constants. No request data reaches the parameter. The repository still trusted any string a future caller passed. |
| `ibl5/classes/SeasonHighs/SeasonHighsRepository.php` inline `LIMIT` | CLEAN | `int $limit` under `declare(strict_types=1)`; PHP rejects a string at the call boundary. |
| `ibl5/classes/SeasonHighs/SeasonHighsRepository.php` location filter | CLEAN | A `match` with two literal arms and a `default => ''`; the input string never reaches SQL. |
| `ibl5/classes/AwardHistory/AwardHistoryRepository.php` sort column | CLEAN | `self::SORT_COLUMN_MAP[$params['sortby']] ?? 'year'`: an out-of-map key resolves to the literal `year`. The request value stays a lookup key throughout. |
| `ibl5/classes/SeasonLeaderboards/SeasonLeaderboardsRepository.php` sort column | CLEAN | `getSortColumn()` maps the request value through a closed `match` with a literal default; it never reaches the ORDER BY fragment. |
| `ibl5/classes/RecordHolders/PlayerRecordRepository.php` stat expressions | CLEAN | Expressions come from `RecordStatDefinitions` class constants via the service; no request path. Same shape as SeasonHighs, with no fail-open branch. |
| `ibl5/classes/TeamOrderBy.php` | CLEAN | A backed PHP enum; the value can only be one of the declared literals. |
| `ibl5/classes/Bootstrap/LegacyFunctions.php` table-name prefix | CLEAN | `global $prefix` is assigned once in the untracked config file as the literal `nuke`. `register_globals` does not exist on PHP 8, so no request key can populate it. Every row value in those statements is bound. |
| `ibl5/classes/BaseMysqliRepository.php` query parameter | CLEAN by design | Protected; reachable only from subclasses in `ibl5/classes/`. These methods are the parameterization seam that binds row values. The savepoint name is built from `bin2hex(random_bytes(4))`. |
| `ibl5/classes/Migration/SchemaValidator.php` `real_escape_string` | CLEAN | A migration-time schema validator. Its input is a migration file identifier supplied by the migration runner. |
| `ibl5/modules/Player/articles.php` `real_escape_string` plus `bind_param` | NOT INJECTABLE, functional defect | The value is bound, so the SQL text never carried it. `real_escape_string` on `O'Brien` yields `O\'Brien`; inside a LIKE pattern `\'` reads as a literal apostrophe, so matching still worked. The real mismatches were that a newline became `\n` which LIKE reads as a literal `n`, NUL became `\0` which reads as `0`, and `%` or `_` in the search term stayed unescaped wildcards. |

**Confirmed injections: zero.**

## Fixes shipped

The audit ships hardening anyway, so the clean verdict cannot regress silently.

- **`ibl5/classes/SeasonHighs/SeasonHighsRepository.php`.** `sanitizeStatName()` now throws
  instead of falling back to the raw stat name. `assertSafeStatExpression()` holds the stat
  expression to a closed character allowlist in both the single and batch paths.
  Regression tests in `ibl5/tests/SeasonHighs/SeasonHighsRepositoryTest.php`.
- **`ibl5/modules/Player/articles.php`.** `addcslashes($player, '\\%_')` replaces
  `real_escape_string`, so the bound value is the literal search term with only LIKE
  metacharacters escaped. Regression tests in
  `ibl5/tests/Module/EntryPoints/PlayerArticlesEntryPointTest.php`.
- **`ibl5/tests/Security/SqlInjectionSurfaceTest.php`.** A structural lock asserting that
  `sql_query(` has no production call sites, and that mysqli string escaping appears
  nowhere under `ibl5/classes/` or `ibl5/modules/` outside the allowlisted
  `ibl5/classes/Migration/SchemaValidator.php`.
