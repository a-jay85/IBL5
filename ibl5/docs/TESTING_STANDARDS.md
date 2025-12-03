# IBL5 Testing Standards

**Purpose:** Comprehensive testing guidelines for PHPUnit 12.4+ in the IBL5 codebase.  
**When to reference:** Writing tests, reviewing test code, setting up test infrastructure.

---

## Testing Requirements

- **Framework**: PHPUnit 12.4+ compatibility required
- **Test Location**: All tests in `ibl5/tests/` directory
- **PR Completion Criteria**: No warnings or failures allowed
- Always run the full test suite before stopping work on a PR
- Add tests for new functionality
- Update tests when refactoring existing code
- Static production data (when available) is preferred over mock data
- Mock functionality should not be used unless absolutely necessary
- Instantiation of classes should be done via the class autoloader
- Do not write tests that only test mocks or instantiation
- **Schema Reference**: Use `ibl5/schema.sql` to understand table structures when creating test data

---

## ⚠️ CRITICAL: Never Skip Tests - Remove Them Instead

**DO NOT use `$this->markTestSkipped()` to document removed tests.** Skipped tests:
- Create technical debt and confusion about what's actually being tested
- Clutter the test suite and make it harder to understand coverage
- May accidentally be re-enabled by future developers without understanding the reason

**Instead:**
1. **COMPLETELY DELETE the entire test method** if it no longer serves a purpose
2. **DOCUMENT the reason in related tests or code comments** if there's valuable context to preserve
3. **Update integration tests** if the behavior now requires end-to-end testing instead
4. **Never create placeholder tests** with `markTestSkipped()` - if a test doesn't run, it shouldn't exist

```php
// ❌ WRONG - Don't do this
public function testRemovedTest()
{
    $this->markTestSkipped('Removed following best practices');
}

// ✅ CORRECT - Either add the test back with proper implementation, or delete it entirely
```

---

## Unit Test Quality Principles

**ALL tests MUST follow these principles from ["Stop Vibe Coding Your Unit Tests"](https://www.andy-gallagher.com/blog/stop-vibe-coding-your-unit-tests/):**

### ✅ DO:
- **Test behaviors through public APIs only** - Focus on observable outcomes
- **Use descriptive test names** that explain the behavior being tested
- **Keep assertions focused on "what" not "how"** - Test outcomes, not implementation
- **Test one behavior per test** - Each test should have a single, clear purpose
- **Use data providers** for similar test cases with different inputs
- **Verify success/failure of operations** - Not the internal mechanics
- **Test edge cases and error conditions** through public method returns

### ❌ DON'T:
- **NEVER use `ReflectionClass` to test private methods** - Private methods are implementation details
- **NEVER check SQL query structure** unless it's the actual behavior being tested (e.g., SQL injection prevention)
- **NEVER depend on internal implementation details** - Tests should survive refactoring
- **NEVER write redundant tests** that add no value beyond existing coverage
- **NEVER test multiple unrelated behaviors** in a single test
- **NEVER assert on method call counts** unless testing caching/memoization behavior

---

## Test Examples

### ❌ BAD - Testing private method with reflection
```php
public function testPrivateMethodLogic()
{
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('privateHelper');
    $method->setAccessible(true);
    $result = $method->invoke($this->service, $input);
    $this->assertEquals($expected, $result);
}
```

### ✅ GOOD - Testing behavior through public API
```php
public function testServiceProcessesDataCorrectly()
{
    $result = $this->service->processData($input);
    $this->assertTrue($result->isValid());
    $this->assertEquals($expectedOutput, $result->getOutput());
}
```

### ❌ BAD - Checking SQL query structure
```php
public function testUpdatePlayerContract()
{
    $this->repository->updateContract($playerId, $salary);
    $queries = $this->mockDb->getExecutedQueries();
    $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
    $this->assertStringContainsString('SET salary = 1000', $queries[0]);
}
```

### ✅ GOOD - Testing operation success
```php
public function testUpdatePlayerContractSucceeds()
{
    $result = $this->repository->updateContract($playerId, $salary);
    $this->assertTrue($result, 'Contract update should succeed');
    $this->assertEquals($salary, $this->repository->getPlayerSalary($playerId));
}
```

### Security Testing Exception

SQL query checking IS appropriate when testing security features:
```php
// ✅ GOOD - Testing SQL injection prevention
public function testEscapesUserInput()
{
    $maliciousInput = "'; DROP TABLE ibl_plr; --";
    $this->repository->findByName($maliciousInput);
    $queries = $this->mockDb->getExecutedQueries();
    $this->assertStringContainsString("\\'; DROP", $queries[0]);
}
```

**Reference**: See `ibl5/docs/TEST_REFACTORING_SUMMARY.md` for complete refactoring history and additional examples.

---

## PHPUnit Test Suite Registration

**Every new test directory and test class MUST be registered in `ibl5/phpunit.xml`.**

**After writing test files, you MUST:**

1. **Verify test directory structure** - Tests should be in `ibl5/tests/ModuleName/`
2. **Add test suite to phpunit.xml** - Register the directory or individual test files
3. **Update testsuite name** - Use descriptive names (e.g., "Player Module Tests", "FreeAgency Module Tests")
4. **Verify tests are discoverable** - Run `vendor/bin/phpunit --list-suites` to confirm registration

### Adding a new module's tests:

```xml
<!-- ✅ CORRECT - Add test suite to phpunit.xml -->
<testsuites>
    <!-- ... existing suites ... -->
    <testsuite name="Compare Players Module Tests">
        <directory>tests/ComparePlayers</directory>
    </testsuite>
</testsuites>
```

### For individual files:

```xml
<testsuite name="FreeAgency Module Tests">
    <file>tests/FreeAgency/FreeAgencyDemandCalculatorTest.php</file>
    <file>tests/FreeAgency/FreeAgencyNegotiationHelperTest.php</file>
</testsuite>
```

**DO NOT:**
- Leave test files unregistered in phpunit.xml
- Create tests without verifying they run via `vendor/bin/phpunit`
- Assume tests will be discovered automatically - they must be explicitly registered

---

## PHPUnit 12.4.3 Command Syntax

**The version of PHPUnit in this project has DIFFERENT command-line options than older versions.**

### ❌ WRONG - Invalid options:
```bash
vendor/bin/phpunit -v                      # Unknown option "-v"
vendor/bin/phpunit --verbose               # Unknown option "--verbose"
vendor/bin/phpunit -c phpunit.xml          # Unknown option "-c"
vendor/bin/phpunit --configuration file    # Unknown option "--configuration"
vendor/bin/phpunit --coverage-html dir     # Unknown option "--coverage-html"
```

### ✅ CORRECT - Valid options:
```bash
vendor/bin/phpunit                         # Run all tests (default)
vendor/bin/phpunit tests/Player/           # Run specific directory
vendor/bin/phpunit tests/Player/PlayerTest.php  # Run specific file
vendor/bin/phpunit --filter testMethodName # Run tests matching pattern
vendor/bin/phpunit --testsuite suiteName   # Run specific test suite
vendor/bin/phpunit --quiet                 # Minimal output
vendor/bin/phpunit --debug                 # Debug output
vendor/bin/phpunit --help                  # Show all available options
```

**Reference:** Check available options with `vendor/bin/phpunit --help` before using unknown flags.

---

## Test Development with Database

When writing tests that need database data:

1. **Query actual data:** Use DatabaseConnection to fetch real player/team/game data
2. **Use transactions:** Wrap test operations in transactions that rollback
3. **Static data preferred:** Cache frequently-used test data rather than querying repeatedly

```php
public function testPlayerFetch()
{
    global $mysqli_db;
    
    // Get a real player from the database
    $result = $mysqli_db->query("SELECT * FROM ibl_plr LIMIT 1");
    $player = $result->fetch_assoc();
    
    $this->assertIsArray($player);
    $this->assertNotEmpty($player['pid']);
}
```

---

## Quick Checklist

Before submitting a PR with tests:

- [ ] All tests pass without warnings or failures
- [ ] Tests registered in `ibl5/phpunit.xml`
- [ ] No `markTestSkipped()` calls
- [ ] No reflection to test private methods
- [ ] Tests verify behavior, not implementation
- [ ] Descriptive test names
- [ ] One behavior per test
- [ ] Edge cases covered
