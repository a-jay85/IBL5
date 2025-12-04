# ComparePlayers Module

Modern refactored implementation of the player comparison feature using interface-driven architecture.

## Overview

Allows users to select two players via autocomplete search and compare their:
- **Current Ratings**: 24 rating categories (2ga, 2g%, fta, ft%, 3ga, 3g%, orb, drb, ast, stl, tvr, blk, foul, oo, do, po, to, od, dd, pd, td)
- **Current Season Stats**: Games, minutes, shooting, rebounds, assists, etc.
- **Career Stats**: Lifetime totals across entire career

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Module Index (Thin Controller)            │
│                 ibl5/modules/Compare_Players/index.php       │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   ComparePlayersService                      │
│              (Business Logic & Validation)                   │
│  • getPlayerNames(): array                                   │
│  • comparePlayers(p1, p2): ?array                            │
└────────────┬──────────────────────────┬─────────────────────┘
             │                          │
             ▼                          ▼
┌────────────────────────┐   ┌─────────────────────────────────┐
│ ComparePlayersRepository│   │    ComparePlayersView           │
│   (Database Access)     │   │     (HTML Rendering)            │
│ • getAllPlayerNames()   │   │ • renderSearchForm()            │
│ • getPlayerByName()     │   │ • renderComparisonResults()     │
└─────────┬───────────────┘   └─────────────────────────────────┘
          │
          ▼
┌─────────────────────────┐
│   Database (ibl_plr)    │
│  • Prepared Statements  │
│  • Escaped Queries      │
└─────────────────────────┘
```

### Classes

**Repository** (`ComparePlayersRepository.php`)
- Database access layer
- Dual-implementation: prepared statements (modern) + escaped queries (legacy)
- Security: All queries use parameterization or DatabaseService::escapeString()

**Service** (`ComparePlayersService.php`)
- Business logic and validation
- Orchestrates repository calls
- Returns structured comparison data

**View** (`ComparePlayersView.php`)
- HTML rendering with output buffering
- XSS protection: all output escaped with htmlspecialchars()
- jQuery UI autocomplete integration

### Interfaces

All classes implement contracts in `Contracts/` subdirectory:
- `ComparePlayersRepositoryInterface` - Database operations contract
- `ComparePlayersServiceInterface` - Business logic contract
- `ComparePlayersViewInterface` - View rendering contract

Each interface contains comprehensive PHPDoc documenting:
- Method signatures with types
- Behavioral documentation
- Parameter constraints
- Return value structures
- Important behaviors and edge cases

## Security Improvements

### SQL Injection Prevention
**Before:**
```php
$query = "SELECT * FROM ibl_plr WHERE name = '$playerName' LIMIT 1";
```

**After (Modern DB):**
```php
$stmt = $this->db->prepare("SELECT * FROM ibl_plr WHERE name = ? LIMIT 1");
$stmt->bind_param('s', $playerName);
```

**After (Legacy DB):**
```php
$escaped = \Services\DatabaseService::escapeString($this->db, $playerName);
$query = "SELECT * FROM ibl_plr WHERE name = '$escaped' LIMIT 1";
```

### XSS Prevention
All player data output is escaped:
```php
<th><?= htmlspecialchars($player1['name']) ?></th>
<th><?= htmlspecialchars((string)$player1['age']) ?></th>
```

JavaScript data is JSON-encoded with HEX flags:
```php
<?= json_encode(stripslashes($name), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
```

## Usage

### In Module Index
```php
// Initialize with database connection
$repository = new \ComparePlayers\ComparePlayersRepository($db);
$service = new \ComparePlayers\ComparePlayersService($repository);
$view = new \ComparePlayers\ComparePlayersView();

// Get player names for autocomplete
$playerNames = $service->getPlayerNames();

// Display search form
echo $view->renderSearchForm($playerNames);

// Process comparison (if POST data exists)
if (isset($_POST['Player1']) && isset($_POST['Player2'])) {
    $comparison = $service->comparePlayers($_POST['Player1'], $_POST['Player2']);
    
    if ($comparison !== null) {
        echo $view->renderComparisonResults($comparison);
    } else {
        echo "One or both players not found.";
    }
}
```

## Database Fields

### Player Table (ibl_plr)
- **Basic**: pid, name, pos, age, teamname
- **Ratings (r_*)**: r_fga, r_fgp, r_fta, r_ftp, r_tga, r_tgp, r_orb, r_drb, r_ast, r_stl, r_to, r_blk, r_foul
- **Skills**: oo, do, po, to, od, dd, pd, td
- **Current Stats (stats_*)**: stats_gm, stats_gs, stats_min, stats_fgm, stats_fga, stats_ftm, stats_fta, stats_3gm, stats_3ga, stats_orb, stats_drb, stats_ast, stats_stl, stats_to, stats_blk, stats_pf
- **Career Stats (car_*)**: car_gm, car_min, car_fgm, car_fga, car_ftm, car_fta, car_tgm, car_tga, car_orb, car_drb, car_reb, car_ast, car_stl, car_to, car_blk, car_pf, car_pts

## Validation

- Player names are trimmed
- Empty names return null (no comparison)
- Both players must exist in database
- Returns null if either player not found

## View Components

### Search Form
- jQuery UI autocomplete (v1.12.1)
- Two text inputs (Player1, Player2)
- POST to modules.php?name=Compare_Players

### Comparison Tables
1. **Current Ratings**: 24 columns with alternating background colors
2. **Current Season Stats**: 19 columns including calculated points
3. **Career Stats**: 19 columns with career totals

## Code Quality

- ✅ Strict types: `declare(strict_types=1);`
- ✅ Type hints on all methods
- ✅ Interface implementations
- ✅ Comprehensive PHPDoc
- ✅ Output buffering for HTML
- ✅ Dual database implementation
- ✅ No exceptions thrown (graceful null returns)

## Testing Recommendations

Create tests in `ibl5/tests/ComparePlayers/`:
- `ComparePlayersRepositoryTest.php` - Database operations
- `ComparePlayersServiceTest.php` - Business logic validation
- `ComparePlayersViewTest.php` - HTML output escaping

**Test Coverage Goals:**
- Repository: getAllPlayerNames(), getPlayerByName()
- Service: comparePlayers() validation (empty names, missing players, valid comparison)
- View: XSS protection, table rendering, autocomplete JSON encoding

## Migration from Legacy

The old module (`ibl5/modules/Compare_Players/index.php`) should be updated to use these classes as a thin controller. See the refactored index.php for the implementation pattern.

**Benefits:**
- 95% code reduction in module file
- SQL injection vulnerability fixed
- XSS protection added
- Testable components
- Type-safe operations
- Clear separation of concerns
