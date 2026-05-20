<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanDbTouchingCallInViewRule;

/**
 * @extends RuleTestCase<BanDbTouchingCallInViewRule>
 */
final class BanDbTouchingCallInViewRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanDbTouchingCallInViewRule();
    }

    public function testFlagsPlayerWithPlrRowInView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/PlayerWithPlrRowInView.php'],
            [
                [
                    'DB-touching construction inside a View class. '
                    . 'Move to the corresponding Service and pass the pre-built object as a render parameter.',
                    10,
                ],
            ],
        );
    }

    public function testFlagsTeamColorHelperInView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/TeamColorHelperInView.php'],
            [
                [
                    'DB-touching construction inside a View class. '
                    . 'Move to the corresponding Service and pass the pre-built object as a render parameter.',
                    9,
                ],
            ],
        );
    }

    public function testIgnoresCleanView(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/CleanView.php'],
            [],
        );
    }

    public function testIgnoresNonViewFiles(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/MysqliPropertyInService.php'],
            [],
        );
    }
}
