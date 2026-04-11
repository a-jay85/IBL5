---
description: Interface-driven PHP class standards, XSS rules, and PHPStan gotchas for ibl5/classes.
paths: ibl5/classes/**/*.php
last_verified: 2026-04-11
---

# PHP Class Development Rules

> **Why this exists:** [ADR-0001](../../ibl5/docs/decisions/0001-interface-driven-architecture.md) explains why every module uses Repository/Service/View with `Contracts/` interfaces. [ADR-0005](../../ibl5/docs/decisions/0005-strict-types-enforcement.md) explains the `declare(strict_types=1)` mandate and PHPStan level `max` + strict-rules floor.

## Interface Standards
- Method signatures with full PHPDoc
- `@see InterfaceName::methodName()` instead of duplicating docblocks
- Class constants for domain values

## Database Access Pattern
All classes in `/ibl5/classes/` use mysqli with prepared statements via BaseMysqliRepository:
```php
// Repository pattern (preferred for database operations)
class MyRepository extends BaseMysqliRepository
{
    public function getById(int $id): ?array
    {
        return $this->fetchOne("SELECT * FROM table WHERE id = ?", "i", $id);
    }

    public function getAll(): array
    {
        return $this->fetchAll("SELECT * FROM table", "");
    }

    public function update(int $id, string $name): bool
    {
        return $this->execute("UPDATE table SET name = ? WHERE id = ?", "si", $name, $id) !== false;
    }
}
```

**Note:** The `MySQL` class exists only for PHP-Nuke backward compatibility and should not be used in new code.

## API Response Types

- **JSON:** `Api\Response\JsonResponder` — standard API responses (Controller → JsonResponder)
- **CSV:** `Api\Response\CsvResponder` — streaming CSV with UTF-8 BOM for Excel compatibility (Controller → Transformer → CsvResponder). See `PlayerExportController` for the canonical example.

## View Rendering Pattern
Use output buffering with `HtmlSanitizer::e()` for XSS-safe output:
```php
public function renderTable(array $data): string
{
    ob_start();
    ?>
<table>
    <?php foreach ($data as $row): ?>
    <tr><td><?= \Utilities\HtmlSanitizer::e($row['name']) ?></td></tr>
    <?php endforeach; ?>
</table>
    <?php
    return ob_get_clean();
}
```

## Statistics Formatting

> **Why this exists:** [ADR-0003](../../ibl5/docs/decisions/0003-statsformatter-mandate.md) explains why `StatsFormatter` is the single source of truth and `number_format()` is banned by `BanNumberFormatRule`.

Use `BasketballStats\StatsFormatter` — `number_format()` is banned by PHPStan:
- `formatPercentage($made, $attempted)` - FG%, 3P% (3 decimals)
- `formatPerGameAverage($total, $games)` - PPG, APG (1 decimal)
- `formatPer36Stat($total, $minutes)` - Per-36 stats
- `formatTotal($value)` - Totals with commas
- `formatWithDecimals($value, $decimals)` - Custom decimal places
- `safeDivide($num, $denom)` - Zero-division handling

## No Unused Methods
Only implement methods with active callers. Dead code increases maintenance burden.

## PHPStan Common Pitfalls

### Type `\mysqli` for database parameters, not `object`
When a class stores or passes a database connection, type the property and parameter as `\mysqli`, not `object`. Using `object` causes cascading PHPStan errors everywhere the connection is passed to methods that expect `\mysqli` (e.g., `Season`, `TeamColorHelper::getTeamColors()`, `Team::initialize()`).

### Handle nullable Player properties
`Player` properties like `$player->name` (`?string`), `$player->teamID` (`?int`) are nullable. Always use null coalescing when passing to methods that expect non-nullable types: `$player->name ?? ''`, `$player->teamID ?? 0`.

### Use concrete return types, not interface types, on factory methods
When a static factory method like `PlayerStats::withPlayerID()` returns a new instance of the concrete class, use `self` as the return type — not the interface type. Returning the interface type causes PHPStan to lose the concrete type, triggering cascading `argument.type` errors at call sites.

### Match PHPDoc array shapes to actual data sources
When a method accepts or returns a color scheme, config, or structured array, the `@param`/`@return` PHPDoc must match the *actual* array shape produced by the source. For example, `TeamColorHelper::generateColorScheme()` returns `array{primary, secondary, gradient_start, gradient_mid, gradient_end, border, border_rgb, accent, text, text_muted}` — don't invent a different shape in consuming methods.

### Don't use `@var` to narrow array shapes below the method's return type
Adding `/** @var array{success: bool, error?: string} $result */` on a variable assigned from a method that returns more fields (e.g., also `teams_stored`, `messages`) causes PHPStan to reject the annotation as incompatible. Either use the full shape or omit the `@var` entirely and let PHPStan infer from the method signature.

### Avoid variable method calls (`$obj->$methodName()`)
PHPStan cannot verify types through dynamic/variable method calls. This causes `method.dynamicName`, `method.nonObject`, and `argument.type` errors. Use explicit match expressions or pass pre-rendered results instead.

### `array_filter()` requires a callback in strict mode
PHPStan strict-rules forbids `array_filter($arr)` without a callback (it uses loose truthiness). Always pass an explicit callback: `array_filter($arr, static fn ($x): bool => $x > 0)`.

### Narrow `mixed` before operations
Values from `array<string, mixed>` are `mixed`. Before concatenation, casting, or passing to typed parameters, narrow with `is_string()`, `is_int()`, etc. Don't use `(string)$mixed` — use `is_string($val) ? $val : ''`.

### `$_GET`/`$_POST` values are `mixed`
Superglobal values are `mixed`, not `string`. Use `is_string($_GET['key'])` to narrow before passing to string-typed methods.
