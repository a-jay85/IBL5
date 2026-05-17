<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanGlobalKeywordRule;

/**
 * @extends RuleTestCase<BanGlobalKeywordRule>
 */
final class BanGlobalKeywordRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanGlobalKeywordRule();
    }

    public function testFlagsGlobalInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/GlobalKeywordInService.php'],
            [
                [
                    '`global $leagueContext;` is banned outside Bootstrap and legacy-compat files. '
                    . 'Inject collaborators via constructor.',
                    7,
                ],
            ],
        );
    }

    public function testAllowsGlobalInLegacyFunctions(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Allowed/LegacyFunctions.php'],
            [],
        );
    }
}
