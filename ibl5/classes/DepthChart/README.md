# Depth Chart Entry Module - Refactoring Documentation

## Overview
The Depth Chart Entry module has been refactored to follow best practices for testability, maintainability, and separation of concerns.

## Architecture

### Namespace: `DepthChart\`

The module is now organized into the following classes:

#### 1. **DepthChartController**
- **Purpose**: Main entry point coordinating the depth chart entry workflow
- **Responsibilities**:
  - Handles user authentication and team retrieval
  - Orchestrates data retrieval and view rendering
  - Manages the form display flow

#### 2. **DepthChartRepository**
- **Purpose**: Handles all database operations
- **Responsibilities**:
  - Fetches offensive sets for a team
  - Retrieves team player data
  - Updates player depth chart settings
  - Updates team history timestamps

#### 3. **DepthChartProcessor**
- **Purpose**: Processes and transforms depth chart data
- **Responsibilities**:
  - Processes form submission data
  - Validates position eligibility
  - Counts active players and position depth
  - Detects invalid configurations (e.g., multiple starting positions)
  - Generates CSV export format

#### 4. **DepthChartValidator**
- **Purpose**: Validates depth chart submissions
- **Responsibilities**:
  - Validates active player counts
  - Validates position depth requirements
  - Checks for players starting at multiple positions
  - Adjusts validation rules based on season phase (Regular Season vs Playoffs)
  - Generates formatted error messages

#### 5. **DepthChartView**
- **Purpose**: Renders all HTML output for the module
- **Responsibilities**:
  - Renders form elements (dropdowns, inputs)
  - Renders player rows with position eligibility
  - Renders submission results
  - Generates offensive set selector links

#### 6. **DepthChartSubmissionHandler**
- **Purpose**: Handles form submissions
- **Responsibilities**:
  - Orchestrates submission processing
  - Coordinates validation, database updates, and file operations
  - Sends email notifications
  - Displays submission results

## Benefits of Refactoring

### 1. **Testability**
- Each class has a single, well-defined responsibility
- Classes can be unit tested in isolation
- Mock objects can be injected for database operations
- 13 unit tests cover core validation and processing logic

### 2. **Maintainability**
- Clear separation of concerns makes code easier to understand
- Each class has focused responsibilities
- Changes to validation logic don't affect view rendering
- Database changes are isolated to the Repository class

### 3. **Readability**
- Descriptive class and method names
- Comprehensive PHPDoc documentation
- Logical organization of related functionality
- index.php reduced from 621 lines to 95 lines

### 4. **Extensibility**
- New validation rules can be added to DepthChartValidator
- View rendering can be customized without affecting business logic
- Database schema changes only require updates to Repository
- Easy to add new features without modifying existing code

## File Structure

```
ibl5/
├── modules/
│   └── Depth_Chart_Entry/
│       └── index.php (thin controller - 95 lines)
├── classes/
│   └── DepthChart/
│       ├── DepthChartController.php
│       ├── DepthChartProcessor.php
│       ├── DepthChartRepository.php
│       ├── DepthChartSubmissionHandler.php
│       ├── DepthChartValidator.php
│       └── DepthChartView.php
└── tests/
    └── DepthChart/
        ├── DepthChartProcessorTest.php
        └── DepthChartValidatorTest.php
```

## Testing

Run the Depth Chart tests:
```bash
vendor/bin/phpunit --testsuite="DepthChart Module Tests"
```

Run all tests:
```bash
vendor/bin/phpunit
```

### Test Coverage
- **13 tests** covering validation and processing logic
- Tests for regular season and playoff validation rules
- Tests for position eligibility logic
- Tests for CSV generation
- Tests for detecting invalid configurations

## Usage

The refactored module maintains the same external interface as the original:

```php
// Entry point remains the same
modules.php?name=Depth_Chart_Entry

// Set selection remains the same
modules.php?name=Depth_Chart_Entry&useset=1
modules.php?name=Depth_Chart_Entry&useset=2
modules.php?name=Depth_Chart_Entry&useset=3

// Submission remains the same
POST to modules.php?name=Depth_Chart_Entry&op=submit
```

## Migration Notes

- **No breaking changes** - External interface remains identical
- All existing functionality preserved
- Database schema unchanged
- Form submissions work exactly as before
- Email notifications continue to function

## Future Improvements

### Security
- Consider using prepared statements instead of string interpolation
- Implement CSRF token validation
- Add input sanitization layer

### Features
- Add drag-and-drop interface for depth chart management
- Implement depth chart history/versioning
- Add validation preview before submission
- Create REST API for mobile app integration

## Design Patterns Used

1. **Repository Pattern** - Abstracts database access
2. **MVC Pattern** - Separates Model, View, and Controller concerns
3. **Single Responsibility Principle** - Each class has one reason to change
4. **Dependency Injection** - Dependencies passed via constructor
5. **Factory Pattern** - Controller creates dependent objects

## Backward Compatibility

The refactoring maintains 100% backward compatibility:
- Same URLs and parameters
- Same form structure and field names
- Same validation rules and error messages
- Same database updates and file operations
- Same email notifications
