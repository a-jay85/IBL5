---
paths: ibl5/tests/**/*.php
---

# PHPUnit Testing Rules

## PHPUnit 13+ Syntax
```bash
# ✅ CORRECT commands
vendor/bin/phpunit tests/Module/
vendor/bin/phpunit --filter testMethodName
vendor/bin/phpunit -c phpunit.ci.xml        # Use specific config
vendor/bin/phpunit --display-all-issues     # Show ALL issues (deprecations, warnings, etc.)

# 💾 Token-saving: When just checking if tests pass (not debugging)
vendor/bin/phpunit | tail -n 3              # Show only final summary lines

# ❌ WRONG - These options don't exist in PHPUnit 13.x
vendor/bin/phpunit -v
vendor/bin/phpunit --verbose
```

## Display Issue Details
PHPUnit 13.x only shows summary counts by default. To see full details:
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

## Mock vs Stub

Use `createStub()` when a test double only provides canned return values (no `expects()` calls). Use `createMock()` only when you need to verify interactions with `expects()`. PHPUnit emits a notice when a mock object has no configured expectations.

```php
// ✅ No expectations — use createStub()
$repo = $this->createStub(RepositoryInterface::class);
$repo->method('findById')->willReturn($entity);

// ✅ Has expectations — use createMock()
$repo = $this->createMock(RepositoryInterface::class);
$repo->expects($this->once())->method('save')->with($entity);
```

## ❌ DON'T:
- **NEVER** use `createMock()` when no `expects()` calls are configured — use `createStub()` instead
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

**IMPORTANT:** Before considering ANY task involving PHP code complete:

1. **Run the FULL test suite**: `vendor/bin/phpunit` — never use `--testsuite` or `--filter` as the final verification. Changes in one module frequently break tests in other modules (shared mocks, interfaces, base classes).
2. **Verify clean output**: The final line must show `OK (X tests, Y assertions)` with NO warnings, failures, or errors
3. **Check for warnings**: If output shows `OK, but there were issues!`, run `--display-all-issues` and FIX the warnings
4. **Don't silence warnings**: Resolve root causes instead of suppressing warnings (unless truly necessary)

**When to use targeted suites:** Only use `--testsuite` or `--filter` when actively debugging a specific failing test to get faster feedback. Once the targeted test passes, immediately re-run the full suite.

Requirements:
- Zero warnings, zero failures, zero errors
- No skipped tests
- All public methods tested

## Post-Task Documentation Update

After completing any PHPUnit task (adding tests, fixing tests, etc.):

1. Run `vendor/bin/phpunit` and note the final test count
2. Update these files with new test count and coverage percentage:
   - `DEVELOPMENT_GUIDE.md` - Status line and relevant sections
   - `ibl5/docs/STRATEGIC_PRIORITIES.md` - Progress section (if significantly changed)
3. If adding integration tests, document them in the "Recent Updates" section of `DEVELOPMENT_GUIDE.md`
