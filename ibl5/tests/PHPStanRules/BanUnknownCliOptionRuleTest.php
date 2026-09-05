<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanUnknownCliOptionRule;

/** @extends RuleTestCase<BanUnknownCliOptionRule> */
final class BanUnknownCliOptionRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanUnknownCliOptionRule();
    }

    public function testFlagsGetoptCallWithoutGuard(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/UnguardedGetoptScript.php'],
            [
                [
                    'getopt() call lacks an unknown-option guard. '
                    . 'After calling getopt(), compare $argv against the accepted option list '
                    . 'and call usage_error() / exit(1) on any unrecognized argument. '
                    . 'Suppress with @phpstan-ignore ibl.unknownCliOption only after the guard is in place.',
                    3,
                ],
            ],
        );
    }

    public function testPassesFileWithNoGetoptCall(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/SuperglobalInService.php'],
            [],
        );
    }
}
