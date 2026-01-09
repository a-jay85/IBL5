# PlayerAwards Module

Refactored module for searching and displaying player awards history.

## Architecture

This module follows the **Interface-Driven Repository/Service/View** pattern established in the IBL5 codebase.

```
PlayerAwards/
├── Contracts/
│   ├── PlayerAwardsValidatorInterface.php   # Input validation contract
│   ├── PlayerAwardsRepositoryInterface.php  # Database operations contract
│   ├── PlayerAwardsServiceInterface.php     # Business logic contract
│   └── PlayerAwardsViewInterface.php        # HTML rendering contract
├── PlayerAwardsValidator.php                # Validates search parameters
├── PlayerAwardsRepository.php               # Database queries (extends BaseMysqliRepository)
├── PlayerAwardsService.php                  # Orchestrates validation and search
├── PlayerAwardsView.php                     # Renders HTML output
└── README.md                                # This file
```

## Usage

```php
// In module index.php
global $mysqli_db;

$validator = new \PlayerAwards\PlayerAwardsValidator();
$repository = new \PlayerAwards\PlayerAwardsRepository($mysqli_db);
$service = new \PlayerAwards\PlayerAwardsService($validator, $repository);
$view = new \PlayerAwards\PlayerAwardsView($service);

// Execute search
$searchResult = $service->search($_POST);

// Render
echo $view->renderSearchForm($searchResult['params']);
if (!empty($_POST)) {
    echo $view->renderTableHeader();
    foreach ($searchResult['awards'] as $index => $award) {
        echo $view->renderAwardRow($award, $index);
    }
    echo $view->renderTableFooter();
}
```

## Search Parameters

| Parameter | Form Field | Type | Description |
|-----------|-----------|------|-------------|
| name | `aw_name` | string | Partial player name match (LIKE) |
| award | `aw_Award` | string | Partial award name match (LIKE) |
| year | `aw_year` | int | Exact year match |
| sortby | `aw_sortby` | int | Sort option: 1=name, 2=award, 3=year |

## Database Schema

Table: `ibl_awards`

| Column | Type | Description |
|--------|------|-------------|
| table_ID | int(11) | Primary key (AUTO_INCREMENT) |
| year | int(11) | Year of the award |
| Award | varchar(128) | Award name/type |
| name | varchar(32) | Player name |

## Security

- **SQL Injection Prevention**: All queries use prepared statements via `BaseMysqliRepository`
- **XSS Prevention**: All output uses `HtmlSanitizer::safeHtmlOutput()`
- **Input Validation**: All parameters validated before database queries
- **Sort Column Whitelisting**: Sort columns validated against whitelist

## Testing

Tests are located in `/ibl5/tests/PlayerAwards/`.

Run tests:
```bash
cd ibl5
vendor/bin/phpunit tests/PlayerAwards/
```

## Related Modules

- [PlayerSearch](../PlayerSearch/README.md) - Similar search pattern
- [Leaderboards](../Leaderboards/) - Similar table display pattern
- [Player](../Player/) - Reference implementation for interface-driven architecture
