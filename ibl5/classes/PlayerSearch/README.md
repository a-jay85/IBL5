# PlayerSearch Module

**Status:** ✅ Complete (November 2025)
**Code Reduction:** 462 → 73 lines in module file (-84%)
**Security:** SQL injection vulnerability **FIXED** using prepared statements

## Overview

The Player Search module provides advanced search functionality for finding players based on multiple criteria including position, age, skill ratings, and more. This module was refactored from a vulnerable legacy implementation to a secure, testable architecture.

## Security Improvements

### Before (Vulnerable)
```php
// VULNERABLE: Direct string concatenation
$query .= " AND name LIKE '%$search_name%'";
$query .= " AND oo >= '$oo'";
```

### After (Secure)
```php
// SECURE: Prepared statements with parameter binding
$conditions[] = 'name LIKE ?';
$bindParams[] = '%' . $params['search_name'] . '%';
$bindTypes .= 's';
// ...
$stmt->bind_param($bindTypes, ...$bindParams);
```

## Architecture

```
classes/PlayerSearch/
├── PlayerSearchValidator.php   - Input validation & sanitization
├── PlayerSearchRepository.php  - Database queries (prepared statements)
├── PlayerSearchService.php     - Business logic & data processing
└── PlayerSearchView.php        - HTML rendering (output buffering)

modules/Player_Search/
└── index.php                   - Controller (~70 lines)

tests/PlayerSearch/
├── PlayerSearchValidatorTest.php  - 20 tests
├── PlayerSearchRepositoryTest.php - 9 tests
├── PlayerSearchServiceTest.php    - 7 tests
└── PlayerSearchViewTest.php       - 18 tests
```

## Key Features

### Validator (`PlayerSearchValidator`)
- **Position whitelist validation** - Only accepts valid positions (PG, SG, SF, PF, C)
- **Integer parameter validation** - Rejects negative numbers and non-numeric input
- **String sanitization** - Trims whitespace, limits length to 64 characters
- **Boolean validation** - Accepts only 0 or 1 values for form submission and active filters

### Repository (`PlayerSearchRepository`)
- **100% prepared statements** - No direct SQL injection possible
- **Dynamic query building** - Builds WHERE clauses based on provided filters
- **Column whitelist** - Maps parameter names to safe database column names
- **Reserved word handling** - Properly escapes `do` and `to` column names

### Service (`PlayerSearchService`)
- **Orchestrates search workflow** - Validates input, calls repository, transforms data
- **PlayerData integration** - Returns `PlayerData` objects instead of arrays for type safety
- **Automatic data transformation** - Converts raw database rows to `PlayerData` via `PlayerRepository`
- **No redundant processing** - Eliminates need for separate data processing methods

### View (`PlayerSearchView`)
- **Output buffering pattern** - Clean, readable HTML templates
- **XSS protection** - All output escaped with `htmlspecialchars()`
- **Maintains form state** - Repopulates form values after search

## Usage

```php
// Initialize classes
$validator = new \PlayerSearch\PlayerSearchValidator();
$repository = new \PlayerSearch\PlayerSearchRepository($mysqli_db);
$playerRepository = new \Player\PlayerRepository($mysqli_db);
$service = new \PlayerSearch\PlayerSearchService($validator, $repository, $playerRepository);
$view = new \PlayerSearch\PlayerSearchView($service);

// Execute search
$searchResult = $service->search($_POST);

// Render results - now using PlayerData objects
echo $view->renderSearchForm($searchResult['params']);
if ($searchResult['count'] > 0) {
    echo $view->renderTableHeader();
    foreach ($searchResult['players'] as $player) {
        // $player is now a PlayerData object, not an array
        echo $view->renderPlayerRow($player, $rowIndex++);
    }
    echo $view->renderTableFooter();
}
```

## Search Filters

| Filter | Type | Operator | Description |
|--------|------|----------|-------------|
| `pos` | String | `=` | Position (PG, SG, SF, PF, C) |
| `age` | Integer | `<=` | Maximum age |
| `search_name` | String | `LIKE` | Player name (partial match) |
| `college` | String | `LIKE` | College name (partial match) |
| `exp`, `exp_max` | Integer | Range | Years of experience |
| `bird`, `bird_max` | Integer | Range | Bird years |
| `oo`, `do`, `po`, `to` | Integer | `>=` | Offensive ratings |
| `od`, `dd`, `pd`, `td` | Integer | `>=` | Defensive ratings |
| `talent`, `skill` | Integer | `>=` | Attribute ratings |
| `r_fga`, `r_fgp`, etc. | Integer | `>=` | Shooting ratings |
| `active` | Boolean | `=` | Include retired players (0=no, 1=yes) |

## Test Coverage

- **52 total tests** covering all four classes
- **208 assertions** verifying behavior
- **SQL injection prevention tests** validate security
- **XSS prevention tests** verify HTML escaping

Run tests:
```bash
cd ibl5 && vendor/bin/phpunit tests/PlayerSearch/
```

## Related Documentation

- [DEVELOPMENT_GUIDE.md](../../DEVELOPMENT_GUIDE.md) - Overall development standards
- [DepthChart SECURITY.md](../DepthChart/SECURITY.md) - Security patterns reference
- [Leaderboards README](../Leaderboards/) - Similar refactoring pattern
