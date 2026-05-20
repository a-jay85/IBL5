<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanAddslashesRule;

/**
 * @extends RuleTestCase<BanAddslashesRule>
 */
final class BanAddslashesRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanAddslashesRule();
    }

    public function testFlagsAddslashesCall(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/CallsAddslashes.php'],
            [
                [
                    'addslashes() is banned. Use prepared statements for database escaping '
                    . 'or json_encode()/htmlspecialchars() for output escaping.',
                    5,
                ],
            ],
        );
    }

    public function testAllowsAddslashesInBootstrapLegacyFunctions(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/Bootstrap/LegacyFunctions.php'],
            [],
        );
    }
}
