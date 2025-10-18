# Free Agency Test Suite

## Overview

This comprehensive PHPUnit test suite validates the Free Agency functionality in IBL5, covering the Free Agency module (`modules/Free_Agency/index.php`), offer submission (`freeagentoffer.php`), and offer deletion (`freeagentofferdelete.php`).

The test suite is designed to verify Free Agency functionality after refactoring efforts and follows the patterns established in the Extension test suite.

## Test Structure

### Test Classes

1. **FreeAgencyOfferValidationTest.php** (21 tests)
   - Zero contract amount validation
   - Minimum salary validation
   - Bird Rights handling
   - Raise percentage validation (10% vs 12.5%)
   - Salary decrease validation
   - Cap space validation (soft and hard cap)
   - Maximum contract validation
   - Player already signed validation
   - MLE/LLE/Veteran Minimum special cases

2. **FreeAgencyOfferProcessingTest.php** (18 tests)
   - Offer insertion into database
   - Offer amendment (replacing existing offers)
   - Offer deletion
   - Modifier calculations (loyalty, tradition, security, playing time, winner)
   - Perceived value calculations
   - Contract years calculation
   - Millions committed at position calculation
   - Discord notification triggers

3. **FreeAgencyModuleDisplayTest.php** (22 tests)
   - Free agent identification based on contract year
   - Cap space calculations (soft and hard)
   - Roster spot tracking
   - Future salary calculations
   - Veteran minimum calculations by experience
   - Maximum contract calculations by experience
   - Bird Rights display indicators
   - MLE/LLE availability display
   - Player demand display for veterans and undrafted rookies

4. **FreeAgencyIntegrationTest.php** (9 tests)
   - Complete offer submission workflow
   - Offer amendment workflow
   - Offer deletion workflow
   - Multiple teams offering same player
   - MLE offer workflow
   - Bird Rights workflow
   - Invalid offer rejection
   - Hard cap enforcement
   - Perceived value calculation in complete workflow

## Running the Tests

### Run All Free Agency Tests
```bash
cd /path/to/ibl5
./vendor/bin/phpunit tests/FreeAgency/
```

### Run Individual Test Files
```bash
./vendor/bin/phpunit tests/FreeAgency/FreeAgencyOfferValidationTest.php
./vendor/bin/phpunit tests/FreeAgency/FreeAgencyOfferProcessingTest.php
./vendor/bin/phpunit tests/FreeAgency/FreeAgencyModuleDisplayTest.php
./vendor/bin/phpunit tests/FreeAgency/FreeAgencyIntegrationTest.php
```

### Run Tests by Group
```bash
# Run only validation tests
./vendor/bin/phpunit --group validation

# Run only processing tests
./vendor/bin/phpunit --group processing

# Run only display tests
./vendor/bin/phpunit --group display

# Run only integration tests
./vendor/bin/phpunit --group integration
```

## Test Groups

Tests are organized into the following groups for targeted execution:

### Validation Groups
- `@group validation` - All validation tests
- `@group zero-amounts` - Tests for zero contract amounts
- `@group minimum-salary` - Tests for veteran minimum
- `@group bird-rights` - Tests for Bird Rights handling
- `@group raises` - Tests for raise validation
- `@group salary-decreases` - Tests for salary decrease validation
- `@group cap-space` - Tests for cap space validation
- `@group max-contract` - Tests for maximum contract validation
- `@group already-signed` - Tests for already signed players
- `@group mle` - Tests for Mid-Level Exception
- `@group lle` - Tests for Low-Level Exception
- `@group veteran-minimum` - Tests for veteran minimum offers

### Processing Groups
- `@group processing` - All processing tests
- `@group offer-creation` - Tests for creating offers
- `@group offer-amendment` - Tests for amending offers
- `@group offer-deletion` - Tests for deleting offers
- `@group modifiers` - Tests for modifier calculations
- `@group perceived-value` - Tests for perceived value calculation
- `@group contract-years` - Tests for contract years calculation
- `@group millions-at-position` - Tests for position salary calculations
- `@group discord` - Tests for Discord notification triggers

### Display Groups
- `@group display` - All display tests
- `@group free-agent-identification` - Tests for FA identification
- `@group roster-spots` - Tests for roster spot tracking
- `@group cap-space` - Tests for cap space display
- `@group future-years` - Tests for future salary calculations
- `@group veteran-minimum` - Tests for veteran minimum display
- `@group maximum-contract` - Tests for maximum contract display
- `@group bird-rights-display` - Tests for Bird Rights indicators
- `@group mle-lle-display` - Tests for MLE/LLE indicators
- `@group demand-display` - Tests for demand display

### Integration Groups
- `@group integration` - All integration tests
- `@group complete-workflow` - Complete workflow tests
- `@group amendment-workflow` - Amendment workflow tests
- `@group deletion-workflow` - Deletion workflow tests
- `@group multiple-offers` - Multiple team offers tests
- `@group mle-workflow` - MLE workflow tests
- `@group bird-rights-workflow` - Bird Rights workflow tests
- `@group validation-workflow` - Validation workflow tests
- `@group cap-workflow` - Cap space workflow tests
- `@group modifier-calculation` - Modifier calculation tests

## Key Business Rules Tested

### Offer Validation
1. **Zero Contract Amounts**: Year 1 cannot be zero
2. **Veteran Minimum**: First year must meet minimum based on experience
3. **Bird Rights**: Bird rights reset when player changes teams
4. **Raises**: 
   - 10% maximum raise per year without Bird rights (2 or fewer years)
   - 12.5% maximum raise per year with Bird rights (3+ years)
5. **Salary Decreases**: Cannot decrease salary in later years (zeros allowed)
6. **Cap Space**:
   - Hard cap: Cannot exceed (Soft Cap + $2000)
   - Soft cap: Can only exceed with Bird rights (3+) or MLE/LLE
7. **Maximum Contracts**:
   - 0-6 years experience: 25% of soft cap ($1,375K)
   - 7-9 years experience: 30% of soft cap ($1,650K)
   - 10+ years experience: 35% of soft cap ($1,925K)
8. **Already Signed**: Players cannot be offered if cy=0 and cy1!=0

### Offer Processing
1. **Offer Creation**: Offers inserted into `ibl_fa_offers` table
2. **Offer Amendment**: Existing offer deleted, new offer inserted
3. **Offer Deletion**: Offer removed from `ibl_fa_offers` table
4. **Modifiers**:
   - **Loyalty**: ±2.5% per point above/below neutral (same/different team)
   - **Winner**: 0.0153% per win-loss differential point
   - **Tradition**: 0.0153% per tradition win-loss differential point
   - **Security**: Based on contract length and player preference
   - **Playing Time**: Based on money committed at position
5. **Perceived Value**: Offer Average × Total Modifier × Random Factor
6. **Discord Notifications**: Sent for offers > $145K when notifications are on

### Special Cases
1. **MLE (Mid-Level Exception)**:
   - 1-6 year offers: $450K, $495K, $540K, $585K, $630K, $675K
   - Allows exceeding soft cap
   - Sets MLE flag in database
2. **LLE (Low-Level Exception)**:
   - 1 year offer: $145K
   - Allows exceeding soft cap
   - Sets LLE flag in database
3. **Veteran Minimum**:
   - Varies by experience: $52K (0 yrs) to $117K (10+ yrs)
   - Allows exceeding soft cap
4. **Undrafted Rookies**:
   - Limited to 2-year contracts
   - Demands shown only for years 3 and 4 (legacy implementation quirk)

### Display Logic
1. **Free Agent Identification**: draft_year + exp + cyt - cy = current_season_ending_year
2. **Roster Spots**: Start at 15, decrement for each player/offer (except pipe-prefixed names)
3. **Cap Space**: Soft Cap ($5,500K) and Hard Cap ($7,500K) minus committed salaries
4. **Future Salaries**: Calculated based on current year of contract (cy)
5. **Bird Rights Indicator**: "*<i>Player Name</i>*" for 3+ Bird years

## Mock Objects

The test suite uses `MockDatabase` class from `tests/bootstrap.php`:
- Simulates SQL queries without database connection
- Tracks executed queries for verification
- Returns configurable mock data
- Supports testing database operations

## Dependencies

- PHPUnit 12.4+
- PHP 8.3+
- Mock classes: Season, Shared, League, Discord, JSB (defined in bootstrap.php)

## Test Coverage

The test suite provides comprehensive coverage of:
- ✅ All validation rules from freeagentoffer.php
- ✅ All modifier calculations
- ✅ Offer creation, amendment, and deletion workflows
- ✅ Display logic from modules/Free_Agency/index.php
- ✅ Cap space and roster spot calculations
- ✅ Special cases (MLE, LLE, Veteran Minimum, Bird Rights)
- ✅ Discord notification triggers
- ✅ Free agent identification logic
- ✅ Maximum contract and veteran minimum calculations
- ✅ End-to-end integration workflows

**Total Tests**: 70 tests covering all major Free Agency functionality

## Future Enhancements

Possible improvements to the test suite:
- Add tests for concurrent offers from multiple teams
- Add tests for offer acceptance logic (freeagentfinish.php)
- Add tests for different season phases
- Add stress tests with extreme values
- Add mutation testing coverage
- Add tests for email notification content (if applicable)
- Add tests for actual Discord message formatting
- Add performance tests for large team rosters
- Add tests for edge cases in modifier calculations

## Maintenance

When modifying Free Agency code:
1. Run the full test suite first
2. Update tests to match new requirements
3. Ensure all tests pass before committing
4. Add new tests for new functionality
5. Keep test documentation up to date

## Contact

For questions about these tests, refer to:
- Original implementation: `modules/Free_Agency/index.php`, `freeagentoffer.php`, `freeagentofferdelete.php`
- Test code: `tests/FreeAgency/` directory
- Extension test patterns: `tests/Extension/` directory

## Refactoring Notes

This test suite is designed to support a future refactoring effort that would:
1. Extract validation logic into `FreeAgency\OfferValidator` class
2. Extract modifier calculations into `FreeAgency\ModifierCalculator` class
3. Extract database operations into `FreeAgency\DatabaseOperations` class
4. Extract display logic into `FreeAgency\DisplayHelper` class
5. Create `FreeAgency\OfferProcessor` orchestration class

The current tests validate the existing procedural code but are structured to easily adapt to an object-oriented refactoring following the Extension module pattern.
