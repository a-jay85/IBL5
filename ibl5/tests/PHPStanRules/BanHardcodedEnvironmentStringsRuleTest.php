<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanHardcodedEnvironmentStringsRule;

/**
 * @extends RuleTestCase<BanHardcodedEnvironmentStringsRule>
 */
final class BanHardcodedEnvironmentStringsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanHardcodedEnvironmentStringsRule();
    }

    public function testFlagsEnvStringInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/EnvStringInService.php'],
            [
                [
                    'Hardcoded environment string "localhost" is banned. '
                    . 'Inject an environment/config flag instead of branching on a literal host name.',
                    5,
                ],
            ],
        );
    }

    public function testAllowsEnvStringInDiscord(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Allowed/Discord.php'],
            [],
        );
    }
}
