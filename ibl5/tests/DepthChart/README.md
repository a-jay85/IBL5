# Depth Chart Entry Test Suite

## Overview

This PHPUnit test suite validates the Depth Chart Entry module (`ibl5/modules/Depth_Chart_Entry/index.php`) with an architect-level focus on testing actual production logic, not mocks or trivial operations.

## Test Coverage

**Total: 31 focused tests** (refactored from 88)

### 1. Validation Tests (`DepthChartValidationTest.php`) - 6 tests
Tests validation rules extracted from submit() function logic:
- Regular season and playoff active player validation (2 tests)
- Position depth validation for both seasons (2 tests)
- Multiple starting position validation (1 test)
- Injury handling in depth calculation (1 test)

### 2. Submission Tests (`DepthChartSubmissionTest.php`) - 6 tests
Tests data processing logic from submit():
- POST data processing (2 tests)
- Active player and position depth counting (2 tests)
- Special character handling and multiple starter detection (2 tests)

### 3. File Export Tests (`DepthChartFileExportTest.php`) - 4 tests
Tests CSV file generation and storage:
- CSV format generation and field validation (1 test)
- File writing, reading, and overwriting (1 test)
- File naming conventions (1 test)
- Full roster export (1 test)

### 4. Helper Function Tests (`DepthChartHelperFunctionsTest.php`) - 5 tests
Tests dropdown generation logic from userinfo():
- posHandler(), offdefHandler(), oidibhHandler() (3 tests)
- Minutes stamina cap calculation (1 test)
- Active status dropdown logic (1 test)

### 5. Integration Tests (`DepthChartIntegrationTest.php`) - 10 tests
Tests complete end-to-end workflows

## Architectural Improvements

Refactored from 88 tests to 31 tests by removing:
1. **Redundant Tests**: Combined tests testing the same logic with different values
2. **Trivial Tests**: Removed tests that only verified basic arithmetic
3. **Mock-Only Tests**: Removed circular tests that only verified mock behavior
4. **Inline Logic Tests**: Consolidated HTML generation logic tests

## Running the Tests

```bash
# Run all Depth Chart tests
php vendor/bin/phpunit tests/DepthChart/ --testdox

# Run specific test class
php vendor/bin/phpunit tests/DepthChart/DepthChartValidationTest.php --testdox

# Run by group
php vendor/bin/phpunit tests/DepthChart/ --group validation --testdox
php vendor/bin/phpunit tests/DepthChart/ --group integration --testdox
```

## Key Business Rules

### Regular Season
- Exactly 12 active players
- Minimum 3-deep at each position
- No player starts at multiple positions
- Injured players (injury >= 15) excluded from depth

### Playoffs
- 10-12 active players allowed
- Minimum 2-deep at each position
- All other rules apply

### File Export
CSV format: `Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI`
**Note:** Ball handling (BH) saved to database but NOT exported to CSV

Saved to: `depthcharts/{TeamName}.txt`

## Test Results

```bash
OK (128 tests, 371 assertions)
- 31 Depth Chart tests (refactored from 88)
- 97 existing Extension and Trading tests
```

## Contact

For questions, refer to:
- Test code with clear assertions
- `modules/Depth_Chart_Entry/index.php` for implementation
- `tests/Extension/README.md` for similar patterns
