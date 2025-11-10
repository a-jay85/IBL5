# StartersLineupComponent Usage Guide

## Overview
The `StartersLineupComponent` is a standalone UI component for rendering starting lineup displays. It separates data processing from presentation, making it reusable across different contexts.

## Architecture

### Components
1. **StartersLineupComponent** (`UI\Components\StartersLineupComponent`)
   - Pure UI rendering component
   - Takes structured data and team colors
   - Returns HTML representation

2. **TeamStatsService** (`Team\TeamStatsService`)
   - Extracts starter data from database results
   - Uses StartersLineupComponent for rendering

## Usage Examples

### Example 1: Using via TeamStatsService (Current Usage)
```php
// This is how it's currently used in TeamController
$statsService = new \Team\TeamStatsService($db);
$result = $repository->getRosterUnderContract($teamID);
$html = $statsService->getLastSimsStarters($result, $team);
echo $html;
```

### Example 2: Using the Component Directly
```php
use UI\Components\StartersLineupComponent;

// Prepare starters data
$starters = [
    'PG' => ['name' => 'John Doe', 'pid' => 1],
    'SG' => ['name' => 'Jane Smith', 'pid' => 2],
    'SF' => ['name' => 'Bob Johnson', 'pid' => 3],
    'PF' => ['name' => 'Mike Williams', 'pid' => 4],
    'C' => ['name' => 'Tom Brown', 'pid' => 5]
];

// Instantiate component
$component = new StartersLineupComponent();

// Render
$html = $component->render($starters, 'FF0000', '0000FF');
echo $html;
```

### Example 3: Using extractStartersData for Custom Processing
```php
use Team\TeamStatsService;

$statsService = new TeamStatsService($db);
$result = $db->sql_query("SELECT * FROM ibl_plr WHERE tid = 5");

// Extract just the data
$starters = $statsService->extractStartersData($result);

// Now you can:
// - Store it in cache
// - Pass it to an API endpoint
// - Transform it further
// - Use it with the component later
```

### Example 4: Custom Component Extension
```php
use UI\Components\StartersLineupComponent;

class CustomStartersComponent extends StartersLineupComponent
{
    public function renderAsJSON(array $starters): string
    {
        return json_encode($starters);
    }
    
    public function renderCompact(array $starters, string $color1, string $color2): string
    {
        // Custom compact rendering logic
    }
}
```

## Data Structure

### Input: Starters Array
```php
[
    'PG' => ['name' => string|null, 'pid' => int|null],
    'SG' => ['name' => string|null, 'pid' => int|null],
    'SF' => ['name' => string|null, 'pid' => int|null],
    'PF' => ['name' => string|null, 'pid' => int|null],
    'C' => ['name' => string|null, 'pid' => int|null]
]
```

### Parameters
- **$starters** (array): Array of starting players with position keys
- **$color1** (string): Primary team color (hex without #)
- **$color2** (string): Secondary team color (hex without #)

### Output
HTML table string with:
- Team-colored header
- Player positions (PG, SG, SF, PF, C)
- Player images
- Links to player pages

## Benefits of This Refactoring

1. **Separation of Concerns**
   - Data extraction logic is separate from UI rendering
   - Each component has a single, clear responsibility

2. **Reusability**
   - Component can be used anywhere, not just in TeamController
   - Can be used in reports, emails, exports, APIs, etc.

3. **Testability**
   - Component can be tested independently
   - No database mocking needed for UI tests

4. **Maintainability**
   - Changes to display logic don't affect data processing
   - Changes to data structure don't affect rendering

5. **Flexibility**
   - Data can be cached, transformed, or stored before rendering
   - Component can be extended for different rendering styles

## Testing

Tests are located in:
- `/home/runner/work/IBL5/IBL5/ibl5/tests/UI/StartersLineupComponentTest.php`
- `/home/runner/work/IBL5/IBL5/ibl5/tests/Team/TeamStatsServiceTest.php`

Run tests with:
```bash
phpunit tests/UI/StartersLineupComponentTest.php
phpunit tests/Team/TeamStatsServiceTest.php
```
