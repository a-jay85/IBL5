---
description: Canonical interface-driven Repository/Service/View patterns for new modules.
last_verified: 2026-06-25
---

# IBL5 Architecture Patterns

**Purpose:** Comprehensive interface-driven architecture patterns for module refactoring.  
**When to reference:** During refactoring tasks, creating new modules, or reviewing architecture decisions.

---

## Interface-Driven Architecture Pattern

**Established Pattern (canonical example: `ibl5/classes/Waivers/` — Repository, Service, Processor, Validator, View, Controller — see ADR-0001)**

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

namespace PlayerDatabase\Contracts;

/**
 * PlayerDatabaseValidatorInterface - Validates player search input
 * 
 * Enforces whitelist validation and input sanitization for player search operations.
 * All methods return true/false to indicate validation success/failure.
 */
interface PlayerDatabaseValidatorInterface
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

namespace PlayerDatabase;

use PlayerDatabase\Contracts\PlayerDatabaseValidatorInterface;

/**
 * @see PlayerDatabaseValidatorInterface
 */
class PlayerDatabaseValidator implements PlayerDatabaseValidatorInterface
{
    private const VALID_POSITIONS = ['PG', 'SG', 'SF', 'PF', 'C'];
    private const MAX_NAME_LENGTH = 64;

    /**
     * @see PlayerDatabaseValidatorInterface::validatePlayerName()
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
     * @see PlayerDatabaseValidatorInterface::validatePosition()
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

## Database Access Pattern

IBL5 uses a single database implementation: **modern mysqli with prepared statements** via `$mysqli_db` (a global `\mysqli` connection with native type casting enabled).

All database access goes through repository classes extending `BaseMysqliRepository`, or inline `$mysqli_db->prepare()` in legacy procedural modules.

### Repository Pattern (Preferred for New Code)

```php
// ✅ Repository extending BaseMysqliRepository
return $this->fetchOne(
    "SELECT * FROM ibl_demands WHERE name LIKE ?",
    's',
    '%' . $playerName . '%'
);
```

### Inline Prepared Statements (Legacy Modules)

```php
// ✅ Inline mysqli prepared statement (legacy procedural modules)
$stmt = $mysqli_db->prepare("SELECT * FROM ibl_plr WHERE tid = ? AND status = ?");
$stmt->bind_param('is', $teamId, $status);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Process row
}
$stmt->close();
```

### Rules

- Never use `->query()` directly on `$mysqli_db` outside `BaseMysqliRepository` — `BanDirectMysqliQueryRule` enforces this.
- Bind all user-supplied values as parameters; never interpolate values into SQL strings.
- `$prefix` (config table prefix) may be interpolated into identifier names since identifiers cannot be bound.

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
- ✅ **PlayerDatabase** (4 interfaces, 4 implementations, 54 tests)
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

---

## Naming Conventions

Two cross-module naming distinctions that recur in `ibl5/classes/`. Both are
conventions to follow, not bugs to fix.

### `*ApiHandler` (module-local HTMX) vs `Api\Controller\*Controller` (REST)

The codebase has two parallel HTTP-endpoint styles. They are distinct on purpose:

| Style | Lives under | Dispatched by | Returns | Use for |
|-------|-------------|---------------|---------|---------|
| `*ApiHandler` | a **feature module** namespace (e.g. `DepthChartEntry\DepthChartEntryApiHandler`) | instantiated **directly** in the owning `ibl5/modules/<Module>/index.php` | an **HTML partial** for an HTMX swap into an already-rendered page | in-page interactivity within one module's UI (HTMX `hx-get`/`hx-post` fragment endpoints) |
| `Api\Controller\*Controller` | `ibl5/classes/Api/Controller/` | the central `ibl5/classes/Api/Router.php` route table | a **JSON** REST response | the versioned external REST API (API-key auth, rate limiting, ETag caching — see API_GUIDE.md) |

**Rule of thumb:** if a new endpoint feeds an HTMX fragment swap inside one
module's page, it is a `*ApiHandler` in that module. If it is a routed,
JSON, externally consumed REST endpoint, it is an `Api\Controller\*Controller`
registered in `Api/Router.php`. A `*ApiHandler` is **not** part of the REST API
and is never registered in `Api/Router.php`.

Current `*ApiHandler` inventory (module-local HTMX):
`DepthChartEntry\DepthChartEntryApiHandler`,
`DraftHistory\DraftHistoryApiHandler`,
`FranchiseRecordBook\FranchiseRecordBookApiHandler`,
`LeagueStarters\LeagueStartersApiHandler`,
`NextSim\NextSimTabApiHandler`,
`SavedDepthChart\SavedDepthChartApiHandler`,
`Team\TeamApiHandler`,
`Trading\TradeRosterPreviewApiHandler`.

### `Trading*` vs `Trade*` prefix within `Trading/`

This convention is documented at its source, next to the code it governs:
**`ibl5/classes/Trading/README.md`**. In brief: `Trading*` is reserved for
module-level entry points (Service, View, Controller); `Trade*` is for
single-trade-scoped domain objects (repositories, validator, processor, offer).
It is advisory-enforced by the `TradingPrefixConventionRule` PHPStan rule
(`ibl5/phpstan-rules/TradingPrefixConventionRule.php`). See that README for the
full inventory and rationale.
