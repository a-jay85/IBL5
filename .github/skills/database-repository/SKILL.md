---
name: database-repository
description: Database repository pattern using BaseMysqliRepository with prepared statements for IBL5. Use when creating repositories, writing database queries, or extending BaseMysqliRepository.
---

# Database Repository Pattern

All IBL repositories extend `BaseMysqliRepository` for standardized prepared statements.

## BaseMysqliRepository Methods

### executeQuery($query, $types, ...$params)
Execute prepared statement, returns statement object.
```php
$stmt = $this->executeQuery(
    "SELECT * FROM ibl_plr WHERE tid = ? AND age <= ?",
    "ii",
    $teamId,
    $maxAge
);
$result = $stmt->get_result();
$stmt->close();
```

### fetchOne($query, $types, ...$params)
Single row as associative array (or null).
```php
return $this->fetchOne(
    "SELECT * FROM ibl_plr WHERE pid = ? LIMIT 1",
    "i",
    $playerId
);
```

### fetchAll($query, $types, ...$params)
All rows as array of associative arrays.
```php
return $this->fetchAll(
    "SELECT * FROM ibl_plr WHERE teamname = ? ORDER BY ordinal",
    "s",
    $teamName
);
```

### execute($query, $types, ...$params)
INSERT/UPDATE/DELETE, returns affected rows.
```php
return $this->execute(
    "UPDATE ibl_plr SET teamname = ? WHERE pid = ?",
    "si",
    $newTeam,
    $playerId
);
```

### getLastInsertId()
Get auto-increment ID after INSERT.
```php
$this->execute("INSERT INTO table (name) VALUES (?)", "s", $name);
return $this->getLastInsertId();
```

## Type Specification String

| Char | Type | Use For |
|------|------|---------|
| `i` | integer | INT, SMALLINT, BIGINT, DATE as Unix timestamp |
| `s` | string | VARCHAR, TEXT, CHAR |
| `d` | double | FLOAT, DOUBLE, DECIMAL |
| `b` | blob | BLOB, BINARY (rare) |

## Error Codes

| Code | Meaning |
|------|---------|
| 1001 | Type/parameter count mismatch |
| 1002 | Prepare failed (invalid SQL) |
| 1003 | Execute failed (constraint violation) |

## Repository Template

```php
<?php

declare(strict_types=1);

namespace ModuleName;

use ModuleName\Contracts\ModuleRepositoryInterface;

class ModuleRepository extends \BaseMysqliRepository implements ModuleRepositoryInterface
{
    /**
     * @see ModuleRepositoryInterface::findById()
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_table WHERE id = ? LIMIT 1",
            "i",
            $id
        );
    }

    /**
     * @see ModuleRepositoryInterface::findByTeam()
     */
    public function findByTeam(int $teamId): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_table WHERE tid = ? ORDER BY ordinal",
            "i",
            $teamId
        );
    }

    /**
     * @see ModuleRepositoryInterface::update()
     */
    public function update(int $id, string $value): int
    {
        return $this->execute(
            "UPDATE ibl_table SET column = ? WHERE id = ?",
            "si",
            $value,
            $id
        );
    }
}
```

## NULL Handling

mysqli's `bind_param()` has no NULL type. Build conditional queries:
```php
if ($value === null) {
    return $this->fetchAll("SELECT * FROM t WHERE col IS NULL");
} else {
    return $this->fetchAll("SELECT * FROM t WHERE col = ?", "s", $value);
}
```

## Date/DateTime Handling

Bind as Unix timestamps with `i` type:
```php
$this->fetchOne(
    "SELECT * FROM games WHERE date > FROM_UNIXTIME(?)",
    "i",
    time()
);
```

## Templates

See [templates/CustomRepository.php](./templates/CustomRepository.php) for starter template.
