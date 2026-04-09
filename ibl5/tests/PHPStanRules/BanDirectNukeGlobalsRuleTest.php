<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanDirectNukeGlobalsRule;

/**
 * @extends RuleTestCase<BanDirectNukeGlobalsRule>
 */
final class BanDirectNukeGlobalsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanDirectNukeGlobalsRule();
    }

    public function testFlagsBannedIsUserCall(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/CallsIsUser.php'],
            [
                [
                    'Direct is_user() calls are banned. Use Utilities\NukeCompat adapter instead (injectable, mockable).',
                    5,
                ],
            ],
        );
    }

    public function testFlagsBannedIsAdminCall(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/CallsIsAdmin.php'],
            [
                [
                    'Direct is_admin() calls are banned. Use Utilities\NukeCompat adapter instead (injectable, mockable).',
                    5,
                ],
            ],
        );
    }

    public function testAllowsBannedCallInsideNukeCompatFile(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/NukeCompat.php'],
            [],
        );
    }

    public function testAllowsBannedCallInsideLegacyFunctionsFile(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/LegacyFunctions.php'],
            [],
        );
    }
}
