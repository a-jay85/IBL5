# IBL5 Architecture Patterns

**Purpose:** Comprehensive interface-driven architecture patterns for module refactoring.  
**When to reference:** During refactoring tasks, creating new modules, or reviewing architecture decisions.

---

## Interface-Driven Architecture Pattern

**Established Pattern (Implemented in PlayerSearch, FreeAgency, Player modules)**

The codebase uses **interface contracts** as the single source of truth for class responsibilities. This pattern maximizes LLM readability and maintainability.

### Architecture Overview

**For each refactored module, create interfaces in a `Contracts/` subdirectory:**

```
Module/
├── Contracts/
│   ├── ModuleInterface.php                    # Facade contract (if applicable)
│   ├── ModuleRepositoryInterface.php          # Data access contract
│   ├── ModuleValidatorInterface.php           # Validation contract
│   ├── ModuleProcessorInterface.php           # Business logic contract
│   ├── ModuleServiceInterface.php             # Service layer contract
│   └── ModuleViewInterface.php                # View rendering contract
├── Module.php                   # Facade (implements ModuleInterface)
├── ModuleRepository.php         # Data access (implements ModuleRepositoryInterface)
├── ModuleValidator.php          # Validation (implements ModuleValidatorInterface)
├── ModuleProcessor.php          # Business logic (implements ModuleProcessorInterface)
├── ModuleService.php            # Services (implements ModuleServiceInterface)
└── ModuleView.php               # Views (implements ModuleViewInterface)
```

---

## Interface Documentation Standards

**Each interface MUST contain comprehensive PHPDoc documenting:**

1. **Method Signatures** - All parameter types and return types
2. **Behavioral Documentation** - What the method does and why
3. **Parameter Constraints** - Valid ranges, required formats, constraints
4. **Return Value Structure** - Describe arrays, objects, edge cases
5. **Important Behaviors** - Edge cases, error conditions, side effects
6. **Usage Examples** (optional) - For complex methods

### Example Interface

```php
<?php

namespace PlayerSearch\Contracts;

/**
 * PlayerSearchValidatorInterface - Validates player search input
 * 
 * Enforces whitelist validation and input sanitization for player search operations.
 * All methods return true/false to indicate validation success/failure.
 */
interface PlayerSearchValidatorInterface
{
    /**
     * Validate and sanitize player name search input
     *
     * @param string $playerName Raw player name from user input (max 64 characters)
     * @return string Sanitized player name (whitespace trimmed, safe for queries)
     * @throws InvalidArgumentException If playerName exceeds 64 characters
     * 
     * **Behaviors:**
     * - Trims leading/trailing whitespace
     * - Returns empty string if input is null or empty
     * - Throws exception if name exceeds maximum length
     * - Does NOT escape for SQL (use prepared statements instead)
     */
    public function validatePlayerName(string $playerName): string;

    /**
     * Validate player position against whitelist
     *
     * @param string $position Player position (e.g., "PG", "SG", "SF", "PF", "C")
     * @return bool True if position is valid (in whitelist), false otherwise
     * 
     * **Valid Positions:** PG, SG, SF, PF, C
     * **Behaviors:**
     * - Case-insensitive validation (converts to uppercase)
     * - Returns false for unknown positions
     * - Returns false for null/empty position
     */
    public function validatePosition(string $position): bool;
}
```

---

## Implementation Pattern

**All implementations MUST:**

1. **Use `implements InterfaceType` clause** - Explicit contract declaration
2. **Add `@see InterfaceNamespace\InterfaceName` docblock** - Point to contract
3. **Replace redundant method docblocks with `@see` references** - Avoid duplication
4. **Maintain type hints** - Match interface signatures exactly
5. **Support both legacy and modern database implementations** - Use `method_exists()` to detect capability

### Example Implementation

```php
<?php

namespace PlayerSearch;

use PlayerSearch\Contracts\PlayerSearchValidatorInterface;

/**
 * @see PlayerSearchValidatorInterface
 */
class PlayerSearchValidator implements PlayerSearchValidatorInterface
{
    private const VALID_POSITIONS = ['PG', 'SG', 'SF', 'PF', 'C'];
    private const MAX_NAME_LENGTH = 64;

    /**
     * @see PlayerSearchValidatorInterface::validatePlayerName()
     */
    public function validatePlayerName(string $playerName): string
    {
        $sanitized = trim($playerName);
        if (strlen($sanitized) > self::MAX_NAME_LENGTH) {
            throw new InvalidArgumentException('Player name exceeds maximum length');
        }
        return $sanitized;
    }

    /**
     * @see PlayerSearchValidatorInterface::validatePosition()
     */
    public function validatePosition(string $position): bool
    {
        if (empty($position)) {
            return false;
        }
        return in_array(strtoupper($position), self::VALID_POSITIONS, true);
    }
}
```

---

## When to Use `@see` vs Full Docblock

**Replace method docblocks with `@see InterfaceName::methodName()` when:**
- The method is public and part of the interface contract
- The interface provides complete documentation of behavior
- The implementation is straightforward and self-explanatory

**Keep full docblocks when:**
- Implementation details differ from interface (rarely)
- There are implementation-specific optimizations (rarely)
- Complex internal logic needs explanation (keep minimal; prefer refactoring)

---

## Database Implementation Flexibility

**CRITICAL: IBL5 supports TWO different database implementations:**

### 1. Legacy MySQL Abstraction Layer (`ibl5/classes/MySQL.php`)
- Provides phpBB-style abstraction layer over mysqli
- Methods: `sql_query()`, `sql_fetch_assoc()`, `sql_escape_string()`, etc.
- Does NOT have prepared statements built-in
- Uses string escaping via `sql_escape_string()` for SQL injection prevention
- Detection: Check `method_exists($db, 'sql_escape_string')`

### 2. Modern mysqli Implementation
- Direct mysqli connection object (when MySQL class not used)
- Methods: `prepare()`, `execute()`, `bind_param()`, etc.
- Has native prepared statement support
- Detection: When `method_exists($db, 'sql_escape_string')` returns false

### Dual Implementation Example

```php
public function getFreeAgencyDemands(string $playerName): array
{
    // Detect which database implementation is available
    if (method_exists($this->db, 'sql_escape_string')) {
        // LEGACY MySQL abstraction layer (phpBB-style)
        $escapedName = \Services\DatabaseService::escapeString($this->db, $playerName);
        $query = "SELECT * FROM ibl_demands WHERE name LIKE '%$escapedName%'";
        $result = $this->db->sql_query($query);
        $row = $this->db->sql_fetch_assoc($result);
    } else {
        // MODERN mysqli connection (preferred for new code)
        $query = "SELECT * FROM ibl_demands WHERE name LIKE ?";
        $stmt = $this->db->prepare($query);
        $searchTerm = '%' . $playerName . '%';
        $stmt->bind_param('s', $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
    }
    
    // Standard return format
    if ($row) {
        return [
            'dem1' => (int) ($row['dem1'] ?? 0),
            'dem2' => (int) ($row['dem2'] ?? 0),
        ];
    }
    
    return ['dem1' => 0, 'dem2' => 0];
}
```

### Database Implementation Detection Rules

```php
// ✅ CORRECT - Use method_exists() to detect capability
if (method_exists($db, 'sql_escape_string')) {
    // Legacy MySQL abstraction layer - use sql_* methods
    $result = $db->sql_query($query);
} else {
    // Modern mysqli - use prepared statements directly
    $stmt = $db->prepare($query);
}

// ❌ WRONG - Don't assume mysqli methods exist
// $stmt = $db->prepare($query);  // May not exist in legacy implementation
```

### String Escaping for Legacy Implementation

```php
// ✅ BEST - Use DatabaseService helper for escaping
use Services\DatabaseService;

$escapedString = DatabaseService::escapeString($db, $userInput);
$query = "SELECT * FROM table WHERE name = '$escapedString'";
$result = $db->sql_query($query);

// ✅ ACCEPTABLE - Direct mysqli_real_escape_string
if (isset($db->db_connect_id) && $db->db_connect_id) {
    $escapedString = mysqli_real_escape_string($db->db_connect_id, $userInput);
    $query = "SELECT * FROM table WHERE name = '$escapedString'";
    $result = $db->sql_query($query);
}
```

### Prepared Statements (Modern Implementation Preferred)

```php
// ✅ MODERN mysqli - Prepared statements (preferred)
$query = "SELECT * FROM ibl_plr WHERE tid = ? AND status = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('is', $teamId, $status);  // 'is' = integer, string
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Process row
}
```

### Migration Path

As the codebase migrates toward Laravel/modern PHP, all database interactions should:
1. Use prepared statements when possible
2. Check for modern mysqli capability first: `!method_exists($db, 'sql_escape_string')`
3. Fall back to legacy implementation only when necessary
4. Use `DatabaseService::escapeString()` for cross-compatibility

---

## Benefits of Interface-Driven Architecture

1. **LLM Readability** - Interfaces are scannable, contracts are obvious
2. **Self-Documenting** - No need for verbose comments
3. **Type Safety** - Enforces contracts at runtime and compile-time
4. **Refactoring Safety** - Changes to signatures caught immediately
5. **Testing** - Mock interfaces easily, test contracts
6. **Onboarding** - New developers understand responsibilities instantly
7. **Maintenance** - Single source of truth (interface) for all implementations

---

## Current Implementation Status

**Modules with Complete Interface Architecture:**
- ✅ **PlayerSearch** (4 interfaces, 4 implementations, 54 tests)
- ✅ **FreeAgency** (7 interfaces, 6 implementations, 11 tests)
- ✅ **Player** (9 interfaces, 8 implementations, 84 tests)

**Pattern to Apply to Remaining Modules:**
- Compare_Players, Leaderboards, Stats modules (Searchable_Stats, League_Stats, Chunk_Stats)
- All new modules going forward

---

## Quick Reference

| Component | Responsibility | Interface Suffix |
|-----------|---------------|------------------|
| Repository | Data access | `RepositoryInterface` |
| Validator | Input validation | `ValidatorInterface` |
| Service | Business logic | `ServiceInterface` |
| Processor | Complex operations | `ProcessorInterface` |
| View | HTML rendering | `ViewInterface` |
| Facade | Simplified API | `Interface` |
