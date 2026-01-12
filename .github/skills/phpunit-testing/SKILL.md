---
name: phpunit-testing
description: PHPUnit 12.4+ test writing with behavior-focused patterns and mock objects for IBL5. Use when writing tests, creating test files, or reviewing test quality.
---

# IBL5 PHPUnit Testing

Write PHPUnit 12.4+ tests following behavior-focused testing principles.

## PHPUnit 12.4.3 Commands

```bash
# ✅ CORRECT
vendor/bin/phpunit tests/Module/
vendor/bin/phpunit --filter testMethodName
vendor/bin/phpunit --testsuite "Module Tests"

# ❌ WRONG - These options don't exist
vendor/bin/phpunit -v
vendor/bin/phpunit --verbose
vendor/bin/phpunit -c phpunit.xml
```

## Test Quality Principles

### ✅ DO:
- Test behaviors through public APIs only
- Use descriptive test names explaining behavior
- Test one behavior per test
- Use data providers for similar cases
- Verify observable outcomes

### ❌ DON'T:
- **NEVER** use `ReflectionClass` for private methods
- **NEVER** use `markTestSkipped()` - delete instead
- **NEVER** check SQL query structure (except security tests)
- **NEVER** assert on method call counts (except caching tests)

## Unit Test File Structure

```php
<?php

declare(strict_types=1);

namespace Tests\ModuleName;

use PHPUnit\Framework\TestCase;
use ModuleName\ModuleService;

class ModuleServiceTest extends TestCase
{
    /** @var RepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private RepositoryInterface $mockRepository;
    
    private ModuleService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(RepositoryInterface::class);
        $this->service = new ModuleService($this->mockRepository);
    }

    public function testDescriptiveBehaviorName(): void
    {
        // Arrange
        $this->mockRepository->method('findById')
            ->willReturn(['id' => 1, 'name' => 'Test']);
        
        // Act
        $result = $this->service->getById(1);
        
        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('Test', $result['name']);
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testRejectsInvalidInput(mixed $input, string $expectedError): void
    {
        $result = $this->service->validate($input);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString($expectedError, $result->getError());
    }

    public static function invalidInputProvider(): array
    {
        return [
            'empty string' => ['', 'cannot be empty'],
            'negative number' => [-1, 'must be positive'],
            'null value' => [null, 'required'],
        ];
    }
}
```

## Integration Test File Structure

Integration tests use `IntegrationTestCase` base class with `TestDataFactory` for complete workflow testing:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\ModuleName;

use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;
use ModuleName\ModuleHandler;

class ModuleIntegrationTest extends IntegrationTestCase
{
    private ModuleHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create real or mock dependencies
        $this->handler = new ModuleHandler($this->mockDb);
        
        // Prevent external notifications
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    protected function tearDown(): void
    {
        unset($this->handler);
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    public function testCompleteWorkflow(): void
    {
        // Arrange - Use TestDataFactory for consistent fixtures
        $player = TestDataFactory::createPlayer(['pid' => 1]);
        $team = TestDataFactory::createTeam(['teamid' => 1]);
        
        $this->mockDb->setQueryResult(
            'SELECT * FROM ibl_plr WHERE pid = 1',
            [$player]
        );

        // Act
        $result = $this->handler->processWorkflow(1);

        // Assert - Both outcome and database operations
        $this->assertTrue($result->isSuccessful());
        $this->assertQueryExecuted('INSERT INTO ibl_module');
        $this->assertQueryNotExecuted('DELETE FROM ibl_plr');
    }
}
```

## TestDataFactory

Factory methods provide consistent mock data with optional overrides:

```php
// Create player with defaults
$player = TestDataFactory::createPlayer();

// Override specific fields
$player = TestDataFactory::createPlayer(['pid' => 99, 'name' => 'John Doe']);

// Create team and season similarly
$team = TestDataFactory::createTeam(['teamid' => 2]);
$season = TestDataFactory::createSeason(['Beginning_Year' => 2026]);
```

Factory includes **all** fields required by `PlayerRepository`, including rating fields (r_fga, r_fgp, etc.) and positional data (offo, offd, offp, offt, defo, defd, defp, deft).

## MockDatabase Framework

The integration test suite includes a complete mock database system in `tests/Integration/Mocks/`:

- **MockDatabase** - Main mock database class with query tracking
- **MockPreparedStatement** - Mock prepared statements with parameter binding
- **MockDatabaseResult** - Mock result sets with row fetching
- **TestDataFactory** - Fixture creation factory for players, teams, seasons

Key features:
- Tracks all executed queries for assertion
- Simulates prepared statement behavior with parameter binding
- Automatically injects into global `$mysqli_db` for legacy code
- No real database required - all operations are in-memory

Example usage:
```php
// Set expected result for a query
$this->mockDb->setMockData([
    ['pid' => 1, 'name' => 'Player One'],
    ['pid' => 2, 'name' => 'Player Two']
]);

// Mock prepared statement with automatic injection
$stmt = $GLOBALS['mysqli_db']->prepare('SELECT * FROM ibl_plr WHERE pid = ?');
$stmt->bind_param('i', $playerId);
$stmt->execute();
$result = $stmt->get_result();
```

## Test Registration

Register new tests in **BOTH** configuration files:

### Standard Tests (Run Locally & CI/CD)
Add to **both** `ibl5/phpunit.xml` and `ibl5/phpunit.ci.xml`:
```xml
<testsuites>
    <testsuite name="ModuleName Tests">
        <directory>tests/ModuleName</directory>
    </testsuite>
</testsuites>
```

### Local-Only Tests (Requires Credentials)
Add **only** to `ibl5/phpunit.xml` (not CI/CD):
```xml
<!-- Example: DatabaseConnectionTest.php -->
<testsuite name="Root Tests">
    <file>tests/DatabaseConnectionTest.php</file>
</testsuite>
```

**Why Two Configs?**
- `phpunit.xml` - runs locally (includes tests requiring MAMP credentials)
- `phpunit.ci.xml` - runs in CI/CD pipeline (excludes local-only tests)
- CI/CD uses: `vendor/bin/phpunit --configuration phpunit.ci.xml`

## Completion Criteria

**Unit Tests:**
- [ ] All tests pass: `vendor/bin/phpunit tests/ModuleName/`
- [ ] Tests registered in **both** `ibl5/phpunit.xml` and `ibl5/phpunit.ci.xml` (unless local-only)
- [ ] No `markTestSkipped()` calls
- [ ] No ReflectionClass for private methods
- [ ] Zero warnings, zero failures

**Integration Tests:**
- [ ] Extends `IntegrationTestCase` for database interaction tests
- [ ] Uses `TestDataFactory` for consistent fixture creation
- [ ] Tests complete workflows, not isolated components
- [ ] Asserts both outcomes and database operations (`assertQueryExecuted`, etc.)
- [ ] Prevents external notifications (`$_SERVER['SERVER_NAME'] = 'localhost'`)
- [ ] Registered in **both** `phpunit.xml` and `phpunit.ci.xml`

## Templates

See [templates/BaseTestCase.php](./templates/BaseTestCase.php) for starter template.

## Reference Test Suites

### Unit Tests
- `tests/PlayerSearch/` - 54 tests, comprehensive validation
- `tests/Player/` - 84 tests, service and calculator coverage
- `tests/Waivers/` - Good edge case coverage

### Integration Tests
- `tests/Integration/Draft/` - Draft selection workflows with player creation
- `tests/Integration/Extension/` - Contract extension complete workflows
- `tests/Integration/Negotiation/` - Free agent negotiation processes
- `tests/Integration/Trading/` - Trade validation and processing
- `tests/Integration/FreeAgency/` - Free agency offer workflows
