# Draft Module Architecture

**Last Updated:** December 3, 2025  
**Test Coverage:** 35 tests, 92 assertions  
**Architecture Pattern:** Interface-Driven Architecture with Repository/Service/View/Controller classes

## Overview

The Draft module manages the annual IBL draft process with complete separation of concerns. All classes implement interfaces that define their contracts, enabling clear documentation and easy testing.

## Architecture Overview

```
Draft Module (Interface-Driven Architecture)
├── Contracts/ (Interface Definitions)
│   ├── DraftRepositoryInterface
│   ├── DraftValidatorInterface
│   ├── DraftProcessorInterface
│   ├── DraftViewInterface
│   └── DraftSelectionHandlerInterface
└── Implementation Classes
    ├── DraftRepository (implements DraftRepositoryInterface)
    ├── DraftValidator (implements DraftValidatorInterface)
    ├── DraftProcessor (implements DraftProcessorInterface)
    ├── DraftView (implements DraftViewInterface)
    └── DraftSelectionHandler (implements DraftSelectionHandlerInterface)
```

## Interface-Driven Architecture Benefits

1. **Single Source of Truth** - Interface defines contract, implementation is detail
2. **LLM Readability** - Interfaces are scannable and self-documenting
3. **Clear Responsibilities** - Each interface shows exactly what a class does
4. **Type Safety** - Enforces method signatures at runtime
5. **Testability** - Mock interfaces easily, test contracts
6. **Maintainability** - Changes visible at interface level first

## Classes and Interfaces

### DraftRepositoryInterface / DraftRepository
**Responsibility:** All database operations for draft functionality

**Location:** `/ibl5/classes/Draft/Contracts/DraftRepositoryInterface.php` | `/ibl5/classes/Draft/DraftRepository.php`

**Public Methods:**
```php
getCurrentDraftSelection(int $draftRound, int $draftPick): ?string
updateDraftTable(string $playerName, string $date, int $draftRound, int $draftPick): bool
updateRookieTable(string $playerName, string $teamName): bool
createPlayerFromDraftClass(string $playerName, string $teamName): bool
isPlayerAlreadyDrafted(string $playerName): bool
getNextTeamOnClock(): ?string
getAllDraftClassPlayers(): array
getCurrentDraftPick(): ?array
```

**Key Implementation Details:**
- Uses `DatabaseService::escapeString()` for SQL injection prevention
- Private method `getNextAvailablePid()` manages temporary PIDs (90000+)
- Maps ibl_draft_class columns to ibl_plr for player creation
- All methods use prepared statement patterns with escaping

---

### DraftValidatorInterface / DraftValidator
**Responsibility:** Input validation and error handling

**Location:** `/ibl5/classes/Draft/Contracts/DraftValidatorInterface.php` | `/ibl5/classes/Draft/DraftValidator.php`

**Public Methods:**
```php
validateDraftSelection(?string $playerName, ?string $currentDraftSelection, bool $isPlayerAlreadyDrafted = false): bool
getErrors(): array
clearErrors(): void
```

**Key Implementation Details:**
- Three-step validation: player selected → pick available → player not drafted
- Stores user-facing error messages for display
- Clears errors automatically before each validation

---

### DraftProcessorInterface / DraftProcessor
**Responsibility:** Business logic for announcements and messages

**Location:** `/ibl5/classes/Draft/Contracts/DraftProcessorInterface.php` | `/ibl5/classes/Draft/DraftProcessor.php`

**Public Methods:**
```php
createDraftAnnouncement(int $draftPick, int $draftRound, string $seasonYear, string $teamName, string $playerName): string
createNextTeamMessage(string $baseMessage, ?string $discordID, ?string $seasonYear): string
getSuccessMessage(string $message): string
getDatabaseErrorMessage(): string
```

**Key Implementation Details:**
- Formats announcements in Markdown for Discord
- Separates base message from next-team/completion logic
- Returns HTML-safe output suitable for web display

---

### DraftViewInterface / DraftView
**Responsibility:** HTML rendering and user interface

**Location:** `/ibl5/classes/Draft/Contracts/DraftViewInterface.php` | `/ibl5/classes/Draft/DraftView.php`

**Public Methods:**
```php
renderValidationError(string $errorMessage): string
renderDraftInterface(array $players, string $teamLogo, string $pickOwner, int $draftRound, int $draftPick, int $seasonYear, int $tid): string
renderPlayerTable(array $players, string $teamLogo, string $pickOwner): string
getRetryInstructions(string $errorMessage): string
hasUndraftedPlayers(array $players): bool
```

**Key Implementation Details:**
- Uses `DatabaseService::safeHtmlOutput()` for XSS prevention
- Player table supports 27 columns (stats and ratings)
- Drafted players shown as strikethrough and disabled
- Only team owning pick can select undrafted players

---

### DraftSelectionHandlerInterface / DraftSelectionHandler
**Responsibility:** Orchestrates complete draft selection workflow

**Location:** `/ibl5/classes/Draft/Contracts/DraftSelectionHandlerInterface.php` | `/ibl5/classes/Draft/DraftSelectionHandler.php`

**Public Methods:**
```php
handleDraftSelection(string $teamName, ?string $playerName, int $draftRound, int $draftPick): string
```

**Workflow:**
1. Get current draft selection (if any)
2. Check if player already drafted
3. Validate selection (player, pick, status)
4. If valid: update all three database tables
5. Send Discord notifications to #general-chat and #draft-picks
6. Return HTML response (success or error)

---

## Database Tables

**ibl_draft** - Active draft structure
- `round` - Round number
- `pick` - Pick number within round
- `team` - Team with this pick
- `player` - Selected player (empty = available)
- `date` - Selection timestamp

**ibl_draft_class** - Prospect roster
- `name`, `pos`, `team` - Player identity
- `drafted` - Status flag (0 = available, 1 = drafted)
- `offo`, `offd`, `offp`, `offt` - Offensive ratings
- `defo`, `defd`, `defp`, `deft` - Defensive ratings
- `age`, `sta`, `tal`, `skl`, `int` - Attributes
- Stats columns: `fga`, `fgp`, `fta`, `ftp`, `tga`, `tgp`, `orb`, `drb`, `ast`, `stl`, `tvr`, `blk`

**ibl_plr** - Main player table
- Drafted players get temporary PIDs (90000+)
- Ratings mapped from ibl_draft_class
- Contract fields (cy1-cy6) initialized to 0

---

## Type Hints and Declarations

All classes use:
- `declare(strict_types=1)` for strict type checking
- Complete parameter type hints (int, string, bool, array, ?type)
- Return type hints on all methods (: type, : ?type, : void)
- No mixed types or untyped parameters

---

## Testing

**Test Coverage:** 35 tests, 92 assertions across 4 test files

**Test Files:**
- `tests/Draft/DraftRepositoryTest.php` - 13 tests
- `tests/Draft/DraftValidatorTest.php` - 10 tests
- `tests/Draft/DraftProcessorTest.php` - 7 tests
- `tests/Draft/DraftViewTest.php` - 5 tests

**Run Tests:**
```bash
cd ibl5
vendor/bin/phpunit --testsuite "Draft Module Tests"
```

**Test Examples:**
- Draft selection validation with multiple conditions
- Database query execution and escaping
- Message formatting with proper Markdown
- HTML rendering with XSS prevention

---

## Security

### SQL Injection Prevention
- All database input escaped via `DatabaseService::escapeString()`
- Repository enforces escaping at entry point
- Prepared statements recommended as future enhancement

### XSS Prevention
- All HTML output escaped via `DatabaseService::safeHtmlOutput()`
- Player names in form values use `htmlspecialchars(ENT_QUOTES)`
- View layer responsible for output safety

### Authorization
- Module assumes upstream authorization
- Recommend checking team ownership before calling handler
- No built-in permission checks (handled by module wrapper)

---

## Usage Examples

### Check Draft Status
```php
$repository = new Draft\DraftRepository($db);
$currentPick = $repository->getCurrentDraftPick();
if ($currentPick === null) {
    echo "Draft is complete!";
} else {
    echo "Team on clock: " . $currentPick['team'];
}
```

### Display Draft Interface
```php
$repository = new Draft\DraftRepository($db);
$view = new Draft\DraftView();

$players = $repository->getAllDraftClassPlayers();
$pick = $repository->getCurrentDraftPick();
$html = $view->renderDraftInterface(
    $players,
    $currentTeam,
    $pick['team'],
    $pick['round'],
    $pick['pick'],
    $season->endingYear,
    $teamID
);
echo $html;
```

### Handle Draft Selection
```php
$handler = new Draft\DraftSelectionHandler($db, $sharedFunctions, $season);
$html = $handler->handleDraftSelection(
    $_POST['teamname'],
    $_POST['player'] ?? null,
    (int)$_POST['draft_round'],
    (int)$_POST['draft_pick']
);
echo $html;  // HTML response (success or error message)
```

---

## Code Quality Metrics

**Current State:**
- 5 implementation classes + 5 interfaces
- 302 lines (DraftRepository) - complex database operations
- 206 lines (DraftView) - comprehensive UI rendering
- 148 lines (DraftSelectionHandler) - workflow orchestration
- 100 lines average for simpler classes
- 35 tests with 92 assertions = high confidence

**Interface Documentation:**
- All interfaces have complete PHPDoc
- All methods documented with behavior, parameters, returns
- Edge cases and important behaviors explained
- Usage examples provided for complex methods

---

## Future Enhancements

1. **Prepared Statements** - Migrate to parameterized queries
2. **Redis Caching** - Cache draft class and standings during draft
3. **Draft Undo** - Implement pick reversal for corrections
4. **Draft Analytics** - Track pick value and team strategies
5. **API Endpoints** - RESTful API for mobile applications
6. **Batch Drafting** - Support commissioner-driven rapid draft mode
7. **Trade Validation** - Validate pick trades during draft
8. **Email Notifications** - Automated email to team owners

---

## References

- Contracts: `ibl5/classes/Draft/Contracts/`
- Database Guide: `DATABASE_GUIDE.md`
- Test Suite: `tests/Draft/`
- Interface Pattern: See Player module (`ibl5/classes/Player/README.md`)
- Architecture Standards: `.github/copilot-instructions.md`
