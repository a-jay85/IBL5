<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanDieExitInProductionRule;

/**
 * @extends RuleTestCase<BanDieExitInProductionRule>
 */
final class BanDieExitInProductionRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanDieExitInProductionRule();
    }

    public function testFlagsDieInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/DieInService.php'],
            [
                [
                    'die/exit is banned outside Bootstrap, LegacyFunctions.php, and HtmxHelper.php. '
                    . 'Process termination prevents response logging, footer rendering, and unit testing. '
                    . 'Return a value, throw a typed exception, or use a Responder.',
                    5,
                ],
            ],
        );
    }

    public function testFlagsExitInService(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/ExitInService.php'],
            [
                [
                    'die/exit is banned outside Bootstrap, LegacyFunctions.php, and HtmxHelper.php. '
                    . 'Process termination prevents response logging, footer rendering, and unit testing. '
                    . 'Return a value, throw a typed exception, or use a Responder.',
                    5,
                ],
            ],
        );
    }

    public function testAllowsExitInBootstrap(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/ExitInBootstrap.php'],
            [],
        );
    }

    public function testAllowsDieInLegacyFunctions(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Allowed/LegacyFunctions.php'],
            [],
        );
    }

    public function testAllowsDieExitOutsideClassesDirectory(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/OutsideClassesDieExit.php'],
            [],
        );
    }
}
