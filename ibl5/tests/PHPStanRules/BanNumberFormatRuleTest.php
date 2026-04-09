<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanNumberFormatRule;

/**
 * @extends RuleTestCase<BanNumberFormatRule>
 */
final class BanNumberFormatRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanNumberFormatRule();
    }

    public function testFlagsDirectNumberFormatCall(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/CallsNumberFormat.php'],
            [
                [
                    'Direct number_format() calls are banned. Use BasketballStats\StatsFormatter methods instead '
                    . '(formatPercentage, formatPerGameAverage, formatTotal, formatWithDecimals, etc.).',
                    5,
                ],
            ],
        );
    }

    public function testAllowsNumberFormatCallInsideStatsFormatterFile(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/StatsFormatter.php'],
            [],
        );
    }
}
