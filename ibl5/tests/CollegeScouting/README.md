# College Scouting Module Tests

This directory contains unit tests for the College Scouting (Draft) module.

## Test Files

### DraftSelectionTest.php
Tests for the draft selection functionality, ensuring that:
- Players can be selected and drafted correctly
- The form structure is valid and maintains radio button associations
- Special characters (e.g., apostrophes in player names) are handled correctly
- Draft picks are validated properly (available vs. already taken)
- The complete draft workflow functions as expected

## Bug Fix Context

These tests were created to verify the fix for a bug where sorting the draft table would cause player selections to fail. The issue was caused by invalid HTML structure where the form tag was nested incorrectly within the table.

**Before Fix:**
```html
<table class="sortable">
    <tr><th>Headers</th></tr>
    <form>
        <tr><td><input type="radio"...></td></tr>
    </form>
</table>
```

**After Fix:**
```html
<form>
    <table class="sortable">
        <tr><th>Headers</th></tr>
        <tr><td><input type="radio"...></td></tr>
    </table>
    <input type="submit">
</form>
```

The corrected structure ensures that when sorttable.js manipulates the DOM to sort rows, the radio buttons remain properly associated with the form element.

## Running Tests

Run all College Scouting tests:
```bash
./vendor/bin/phpunit --testsuite "College Scouting Module Tests"
```

Run with detailed output:
```bash
./vendor/bin/phpunit --testdox --testsuite "College Scouting Module Tests"
```

## Test Coverage

The test suite covers:
- ✓ POST data handling
- ✓ Player name validation (including special characters)
- ✓ Draft pick availability checking
- ✓ Database query formation
- ✓ Radio button HTML structure
- ✓ Form structure validation
- ✓ Complete draft workflow integration
