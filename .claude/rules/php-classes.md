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

**Note:** PHP-Nuke modules in `/ibl5/modules/` may still use legacy `$db` patterns.
The `MySQL` class is deprecated and only exists for PHP-Nuke backward compatibility.

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
