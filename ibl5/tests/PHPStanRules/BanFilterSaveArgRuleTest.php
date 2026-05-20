<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanFilterSaveArgRule;

/**
 * @extends RuleTestCase<BanFilterSaveArgRule>
 */
final class BanFilterSaveArgRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanFilterSaveArgRule();
    }

    public function testFlagsFilterWithThreeArgs(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/CallsFilterThreeArgs.php'],
            [
                [
                    'filter() must not be called with more than 2 arguments. '
                    . 'The legacy $save parameter has been removed.',
                    5,
                ],
            ],
        );
    }
}
