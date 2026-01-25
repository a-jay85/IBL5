---
paths: ibl5/tests/**/*.php
---

# PHPUnit Testing Rules

## PHPUnit 12.4+ Syntax
```bash
# ✅ CORRECT commands
vendor/bin/phpunit tests/Module/
vendor/bin/phpunit --filter testMethodName
vendor/bin/phpunit -c phpunit.ci.xml        # Use specific config
vendor/bin/phpunit --display-all-issues     # Show ALL issues (deprecations, warnings, etc.)

# ❌ WRONG - These options don't exist in PHPUnit 12.x
vendor/bin/phpunit -v
vendor/bin/phpunit --verbose
```

## Display Issue Details
PHPUnit 12.x only shows summary counts by default. To see full details:
- `--display-all-issues` - **Recommended:** shows everything
- `--display-deprecations`, `--display-warnings`, `--display-notices` - specific types

## Test File Structure
```php
<?php

declare(strict_types=1);

namespace Tests\ModuleName;

use PHPUnit\Framework\TestCase;

class ModuleServiceTest extends TestCase
{
    /** @var InterfaceName&\PHPUnit\Framework\MockObject\MockObject */
    private InterfaceName $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(InterfaceName::class);
    }

    public function testDescriptiveBehaviorName(): void
    {
        // Arrange
        $input = ['key' => 'value'];
        
        // Act
        $result = $this->service->publicMethod($input);
        
        // Assert
        $this->assertTrue($result->isValid());
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testRejectsInvalidInput(mixed $input, string $expectedError): void
    {
        $result = $this->service->validate($input);
        $this->assertStringContainsString($expectedError, $result->getError());
    }

    public static function invalidInputProvider(): array
    {
        return [
            'empty string' => ['', 'cannot be empty'],
            'negative number' => [-1, 'must be positive'],
        ];
    }
}
```

## ✅ DO:
- Test behaviors through public APIs only
- Use descriptive test names
- Test one behavior per test
- Use data providers for similar cases
- Use `@see` instead of `{@inheritdoc}`

## ❌ DON'T:
- **NEVER** use `ReflectionClass` for private methods
- **NEVER** use `markTestSkipped()` - delete instead
- **NEVER** check SQL query structure (except security tests)

## Test Registration
Register in `ibl5/phpunit.xml`:
```xml
<testsuite name="ModuleName Tests">
    <directory>tests/ModuleName</directory>
</testsuite>
```

## Completion Criteria
- Zero warnings, zero failures
- No skipped tests
- All public methods tested
