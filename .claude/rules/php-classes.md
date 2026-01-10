---
paths: ibl5/classes/**/*.php
---

# PHP Class Development Rules

## Interface-Driven Architecture
Structure modules in `ibl5/classes/ModuleName/`:
```
Module/
├── Contracts/
│   ├── ModuleRepositoryInterface.php
│   └── ModuleServiceInterface.php
├── ModuleRepository.php    # implements ModuleRepositoryInterface
├── ModuleService.php       # implements ModuleServiceInterface
└── ModuleView.php          # HTML rendering
```

## Interface Standards
- Method signatures with full PHPDoc
- `@see InterfaceName::methodName()` instead of duplicating docblocks
- Class constants for domain values

## Database Dual-Implementation
Support both database types:
```php
if (method_exists($this->db, 'sql_escape_string')) {
    // LEGACY: sql_* methods with DatabaseService::escapeString()
    $escaped = \Services\DatabaseService::escapeString($this->db, $input);
} else {
    // MODERN: prepared statements (preferred)
    $stmt = $this->db->prepare("SELECT * FROM table WHERE col = ?");
    $stmt->bind_param('s', $input);
}
```

## View Rendering Pattern
Use output buffering:
```php
public function renderTable(array $data): string
{
    ob_start();
    ?>
<table>
    <?php foreach ($data as $row): ?>
    <tr><td><?= \Utilities\HtmlSanitizer::safeHtmlOutput($row['name']) ?></td></tr>
    <?php endforeach; ?>
</table>
    <?php
    return ob_get_clean();
}
```

## Statistics Formatting
Use `BasketballStats\StatsFormatter`:
- `formatPercentage($made, $attempted)` - FG%, 3P% (3 decimals)
- `formatPerGameAverage($total, $games)` - PPG, APG (1 decimal)
- `formatPer36Stat($total, $minutes)` - Per-36 stats
- `formatTotal($value)` - Totals with commas
- `safeDivide($num, $denom)` - Zero-division handling

## No Unused Methods
Only implement methods with active callers. Dead code increases maintenance burden.
