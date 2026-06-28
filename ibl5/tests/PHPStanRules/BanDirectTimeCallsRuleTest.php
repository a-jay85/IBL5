<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanDirectTimeCallsRule;

/**
 * @extends RuleTestCase<BanDirectTimeCallsRule>
 */
final class BanDirectTimeCallsRuleTest extends RuleTestCase
{
    private const MESSAGE = 'Direct now-returning time call is banned outside the Clock seam. '
        . 'Inject Clock\\ClockInterface (constructor for instance classes; settable static '
        . 'clock with factory fallback for static classes) and call $clock->now(). '
        . 'Pass an explicit timestamp to date()/strtotime() for deterministic formatting.';

    protected function getRule(): Rule
    {
        return new BanDirectTimeCallsRule();
    }

    public function testFlagsBareNowCalls(): void
    {
        // Flags time(), date('Y') (<=1 arg), and mktime() (0 args); the
        // arg-bearing date()/gmdate()/strtotime() calls in the same fixture
        // are deliberately absent from the expected list (over-ban guard).
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/DirectTimeInService.php'],
            [
                [self::MESSAGE, 8],
                [self::MESSAGE, 9],
                [self::MESSAGE, 10],
            ],
        );
    }

    public function testAllowsExplicitTimestampArg(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/ExplicitTimestampArgs.php'],
            [],
        );
    }

    public function testAllowsClockImplAndNukeCompat(): void
    {
        $this->analyse(
            [
                __DIR__ . '/Fixtures/classes/Allowed/SystemClock.php',
                __DIR__ . '/Fixtures/classes/Allowed/NukeCompat.php',
            ],
            [],
        );
    }
}
