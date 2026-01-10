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

## Test File Structure

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

## Test Registration

Add to `ibl5/phpunit.xml`:
```xml
<testsuites>
    <testsuite name="ModuleName Tests">
        <directory>tests/ModuleName</directory>
    </testsuite>
</testsuites>
```

## Completion Criteria

- [ ] All tests pass: `vendor/bin/phpunit tests/ModuleName/`
- [ ] Tests registered in `ibl5/phpunit.xml`
- [ ] No `markTestSkipped()` calls
- [ ] No ReflectionClass for private methods
- [ ] Zero warnings, zero failures

## Templates

See [templates/BaseTestCase.php](./templates/BaseTestCase.php) for starter template.

## Reference Test Suites

- `tests/PlayerSearch/` - 54 tests, comprehensive validation
- `tests/Player/` - 84 tests, service and calculator coverage
- `tests/Waivers/` - Good edge case coverage
