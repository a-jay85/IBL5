# Depth Chart Entry Test Suite

## Overview

This PHPUnit test suite validates the Depth Chart Entry module (`ibl5/modules/Depth_Chart_Entry/index.php`) with an architect-level focus on testing actual production logic, not mocks or trivial operations.

## Test Coverage

**Total: 28 focused tests** (reduced from 31 after removing position depth validation)

### 1. Validation Tests (`DepthChartValidationTest.php`) - 4 tests
Tests validation rules extracted from submit() function logic:
- Regular season and playoff active player validation (2 tests)
- Multiple starting position validation (1 test)
- Injury handling in depth calculation (1 test)

### 2. Submission Tests (`DepthChartSubmissionTest.php`) - 6 tests
Tests data processing logic from submit():
- POST data processing (2 tests)
- Active player and position counting (2 tests)
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

### 5. Integration Tests (`DepthChartIntegrationTest.php`) - 9 tests
Tests complete end-to-end workflows

## Architectural Improvements

Refactored from 88 tests to 28 tests by removing:
1. **Redundant Tests**: Combined tests testing the same logic with different values
2. **Trivial Tests**: Removed tests that only verified basic arithmetic
3. **Mock-Only Tests**: Removed circular tests that only verified mock behavior
4. **Inline Logic Tests**: Consolidated HTML generation logic tests
5. **Position Depth Tests**: Removed as module does not enforce position depth requirements

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
- No player starts at multiple positions
- Injured players (injury >= 15) excluded from counting

### Playoffs
- 10-12 active players allowed
- All other rules apply

### File Export
CSV format: `Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH`
**Note:** Ball handling (BH) is now included in CSV export (updated from previous version)

Saved to: `depthcharts/{TeamName}.txt`

## Test Results

```bash
OK (183 tests, 609 assertions)
- 28 Depth Chart tests (reduced from 31)
- 155 other tests (Extension, Trading, Update All The Things)
```

## Contact

For questions, refer to:
- Test code with clear assertions
- `modules/Depth_Chart_Entry/index.php` for implementation
- `tests/Extension/README.md` for similar patterns
