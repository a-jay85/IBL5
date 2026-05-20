<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanCastFunctionsRule;

/**
 * @extends RuleTestCase<BanCastFunctionsRule>
 */
final class BanCastFunctionsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanCastFunctionsRule();
    }

    public function testFlagsIntvalInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/IntvalInService.php'],
            [
                [
                    'intval() is banned. Use (int) cast instead. '
                    . 'PHPStan can narrow types through casts but not through these functions, '
                    . 'and intval(\'08\') silently returns 0 (octal radix surprise).',
                    5,
                ],
            ],
        );
    }

    public function testFlagsFloatvalInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/FloatvalInService.php'],
            [
                [
                    'floatval() is banned. Use (float) cast instead. '
                    . 'PHPStan can narrow types through casts but not through these functions, '
                    . 'and intval(\'08\') silently returns 0 (octal radix surprise).',
                    5,
                ],
            ],
        );
    }

    public function testFlagsStrvalInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/StrvalInService.php'],
            [
                [
                    'strval() is banned. Use (string) cast instead. '
                    . 'PHPStan can narrow types through casts but not through these functions, '
                    . 'and intval(\'08\') silently returns 0 (octal radix surprise).',
                    5,
                ],
            ],
        );
    }

    public function testAllowsIntvalInStatsSanitizer(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Allowed/StatsSanitizer.php'],
            [],
        );
    }

    public function testAllowsIntvalInConfigBootstrap(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Allowed/ConfigBootstrap.php'],
            [],
        );
    }

    public function testAllowsIntvalOutsideClassesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/OutsideClassesCastFunction.php'],
            [],
        );
    }
}
