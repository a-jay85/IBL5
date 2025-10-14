# Depth Chart Entry Test Suite

## Overview

This comprehensive PHPUnit test suite validates the Depth Chart Entry module (`ibl5/modules/Depth_Chart_Entry/index.php`) to ensure correct functionality for depth chart submission and export operations. The test suite was designed to support and verify a future refactor of the module.

## Test Coverage

The test suite provides comprehensive coverage of all major functionality:

### 1. Validation Tests (`DepthChartValidationTest.php`)
Tests all validation rules for depth chart submissions:

- **Active Player Validation**
  - Regular season: Exactly 12 active players required
  - Playoffs: 10-12 active players allowed
  - Rejection of too few or too many active players

- **Position Depth Validation**
  - Regular season: Minimum 3-deep at each position (PG, SG, SF, PF, C)
  - Playoffs: Minimum 2-deep at each position
  - Individual position depth requirements

- **Multiple Starting Positions**
  - Players can only start at one position
  - Players can be backup at multiple positions
  - Proper detection of rule violations

- **Injury Handling**
  - Injured players (injury >= 15) don't count toward position depth
  - Minor injuries (injury < 15) still count
  - Proper depth calculations with injuries

- **Edge Cases**
  - Player names with special characters (apostrophes)
  - Empty player slots
  - Maximum player count (15 slots)

### 2. Submission Tests (`DepthChartSubmissionTest.php`)
Tests database operations during depth chart submission:

- **Database Updates**
  - PG, SG, SF, PF, C depth chart positions
  - Active status
  - Minutes allocation
  - Offensive focus (Auto/Outside/Drive/Post)
  - Defensive focus (Auto/Outside/Drive/Post)
  - Offensive intensity (-2 to +2)
  - Defensive intensity (-2 to +2)
  - Ball handling (-2 to +2)

- **Team History Updates**
  - Depth chart timestamp (`depth` field)
  - Sim depth chart timestamp (`sim_depth` field)

- **POST Data Processing**
  - Individual player data extraction
  - Team metadata processing
  - Multiple player batch processing
  - Special character handling

- **Counting Operations**
  - Active player counting
  - Position depth counting with injury consideration

### 3. File Export Tests (`DepthChartFileExportTest.php`)
Tests CSV file generation and export:

- **CSV Format**
  - Correct header generation
  - Individual player row formatting
  - Complete file generation with all players

- **File Operations**
  - File writing to disk
  - File naming conventions (`depthcharts/{TeamName}.txt`)
  - File overwriting behavior
  - Error handling for write failures

- **Content Structure**
  - All 12 required fields per player
  - Proper CSV delimiter usage
  - Offensive/Defensive focus formatting
  - Intensity and ball handling values

- **Edge Cases**
  - Full 15-player roster export
  - Partial roster export
  - Team names with spaces

### 4. Helper Function Tests (`DepthChartHelperFunctionsTest.php`)
Tests HTML form generation helper functions:

- **posHandler()** - Position depth dropdowns
  - Options: No, 1st, 2nd, 3rd, 4th, ok
  - Proper SELECTED attribute handling
  - All 6 option values

- **offdefHandler()** - Offensive/Defensive focus dropdowns
  - Options: Auto, Outside, Drive, Post
  - Values 0-3
  - SELECTED state management

- **oidibhHandler()** - Intensity/Ball handling dropdowns
  - Options: -2, -1, 0 (-), 1, 2
  - Negative value handling
  - Zero as neutral option

- **Minutes Handler** - Minutes allocation dropdown
  - Auto option (value 0)
  - Specific minute values (1-40)
  - Stamina cap calculations
  - Cap limitation to 40 minutes

- **Active Handler** - Active status dropdown
  - Yes/No options
  - Boolean selection (1/0)
  - Single selection enforcement

### 5. Integration Tests (`DepthChartIntegrationTest.php`)
Tests complete end-to-end workflows:

- **Complete Workflow**
  - Valid submission from form to database
  - File generation and storage
  - All validation steps
  - Database update execution

- **Validation Scenarios**
  - Successful validation for valid rosters
  - Failure scenarios for invalid rosters
  - Season-specific rule application

- **Database Integration**
  - All 12 field updates per player
  - Team history timestamp updates
  - Multi-player batch updates

- **Season-Specific Rules**
  - Regular season requirements (12 active, 3-deep)
  - Playoff requirements (10-12 active, 2-deep)
  - Proper rule switching

- **Complex Scenarios**
  - Injured player handling in validation
  - Multiple starting position detection
  - Email subject formatting

## Test Structure

```
tests/DepthChart/
├── DepthChartValidationTest.php       - 42 tests for validation rules
├── DepthChartSubmissionTest.php       - 23 tests for database operations
├── DepthChartFileExportTest.php       - 18 tests for file export
├── DepthChartHelperFunctionsTest.php  - 30 tests for helper functions
├── DepthChartIntegrationTest.php      - 10 tests for end-to-end workflows
└── README.md                           - This documentation
```

**Total: 123 comprehensive tests**

## Running the Tests

### Run All Depth Chart Tests
```bash
cd /home/runner/work/IBL5/IBL5/ibl5
php vendor/bin/phpunit tests/DepthChart/ --testdox
```

### Run Specific Test Class
```bash
php vendor/bin/phpunit tests/DepthChart/DepthChartValidationTest.php --testdox
```

### Run Tests by Group
```bash
# Run only validation tests
php vendor/bin/phpunit tests/DepthChart/ --group validation --testdox

# Run only database tests
php vendor/bin/phpunit tests/DepthChart/ --group database --testdox

# Run only file export tests
php vendor/bin/phpunit tests/DepthChart/ --group file-export --testdox

# Run only integration tests
php vendor/bin/phpunit tests/DepthChart/ --group integration --testdox
```

## Test Groups

Tests are organized with the following group annotations:

- `@group validation` - Validation logic tests
- `@group active-players` - Active player count validation
- `@group position-depth` - Position depth validation
- `@group multiple-starters` - Multiple starting position validation
- `@group injury-handling` - Injury-related logic
- `@group edge-cases` - Edge case handling
- `@group submission` - Submission process tests
- `@group database` - Database operation tests
- `@group post-data` - POST data processing
- `@group file-export` - File generation tests
- `@group csv-format` - CSV formatting tests
- `@group file-writing` - File I/O operations
- `@group helper-functions` - Helper function tests
- `@group position-handler` - Position dropdown tests
- `@group offdef-handler` - Offensive/Defensive focus tests
- `@group oidibh-handler` - Intensity/Ball handling tests
- `@group integration` - Integration tests
- `@group complete-workflow` - Full workflow tests

## Dependencies

The test suite uses the following mock objects from `tests/bootstrap.php`:

- **MockDatabase** - Simulates database operations without requiring actual database connection
- **Season** - Mock season object with configurable phase and rules
- **Shared** - Mock shared functions class

## Key Business Rules Tested

### Regular Season Rules
- Exactly 12 active players required
- Minimum 3-deep at each position (PG, SG, SF, PF, C)
- No player can start at multiple positions
- Injured players (injury >= 15) don't count toward depth

### Playoff Rules
- 10-12 active players allowed
- Minimum 2-deep at each position
- All other regular season rules apply

### Database Schema
The module updates the following fields in `ibl_plr`:
- `dc_PGDepth`, `dc_SGDepth`, `dc_SFDepth`, `dc_PFDepth`, `dc_CDepth` - Position depths (0-5)
- `dc_active` - Active status (0=No, 1=Yes)
- `dc_minutes` - Minutes allocation (0=Auto, 1-40)
- `dc_of` - Offensive focus (0=Auto, 1=Outside, 2=Drive, 3=Post)
- `dc_df` - Defensive focus (0=Auto, 1=Outside, 2=Drive, 3=Post)
- `dc_oi` - Offensive intensity (-2 to +2)
- `dc_di` - Defensive intensity (-2 to +2)
- `dc_bh` - Ball handling (-2 to +2)

And updates `ibl_team_history`:
- `depth` - Last depth chart submission timestamp
- `sim_depth` - Last sim-ready depth chart timestamp

### File Export Format
CSV file with format:
```
Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI
Player Name,1,0,0,0,0,1,35,0,0,0,0
...
```

Saved to: `depthcharts/{TeamName}.txt`

### Email Notification
Subject: `{TeamName} Depth Chart - {SetName} Offensive Set`
Recipient: `ibldepthcharts@gmail.com`
Content: CSV file content

## Testing Methodology

The test suite follows established conventions from the Extension and Trading test suites:

1. **Arrange-Act-Assert** pattern for all tests
2. **Clear test names** describing exactly what is being tested
3. **Mock objects** to isolate units under test
4. **Group annotations** for organized test execution
5. **Comprehensive assertions** with descriptive messages
6. **Edge case coverage** for robustness
7. **Integration tests** to verify complete workflows

## Mock Objects

### MockDatabase
Tracks all SQL queries executed and returns configurable results:
```php
$mockDb = new MockDatabase();
$mockDb->setReturnTrue(true); // Make UPDATE/INSERT return true
$mockDb->sql_query($query);
$queries = $mockDb->getExecutedQueries(); // Verify queries
```

### Season
Configurable season phase for testing different rule sets:
```php
$season = new Season($mockDb);
$season->phase = 'Regular Season'; // or 'Playoffs'
```

## Future Enhancements

Possible improvements to the test suite:

- Add tests for UI rendering and HTML generation
- Add tests for position eligibility logic (PG, G, SG, GF, SF, F, PF, FC, C)
- Add tests for offensive set selection and management
- Add tests for user authentication and permissions
- Add tests for email delivery failures
- Add tests for concurrent submissions from multiple teams
- Add performance tests for large roster processing
- Add mutation testing coverage

## Maintenance

When modifying `modules/Depth_Chart_Entry/index.php`:

1. **Run the full test suite first** to establish baseline
2. **Update tests** to match new requirements
3. **Add new tests** for new functionality
4. **Ensure all tests pass** before committing
5. **Update this documentation** if validation rules change
6. **Maintain test coverage** above 90% for critical paths

## Refactoring Support

This test suite is specifically designed to support refactoring:

1. **Comprehensive coverage** ensures no functionality is lost
2. **Clear assertions** make it obvious when behavior changes
3. **Integration tests** verify end-to-end workflows still work
4. **Mock objects** allow testing without full application context
5. **Documented rules** serve as specification for new implementation

## Contact

For questions about these tests or the Depth Chart Entry module:
- Review the test code - heavily documented with clear assertions
- Refer to `modules/Depth_Chart_Entry/index.php` for current implementation
- See `tests/Extension/README.md` for similar testing patterns
