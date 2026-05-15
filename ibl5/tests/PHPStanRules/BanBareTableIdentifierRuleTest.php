<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanBareTableIdentifierRule;

/**
 * @extends RuleTestCase<BanBareTableIdentifierRule>
 */
final class BanBareTableIdentifierRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanBareTableIdentifierRule();
    }

    public function testFlagsBareTableIdentifiers(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BareTableIdentifier.php'],
            [
                [
                    "Table identifier 'ibl_plr' must be wrapped in backticks for PHPStan rename safety.",
                    5,
                ],
                [
                    "Table identifier 'ibl_plr' must be wrapped in backticks for PHPStan rename safety.",
                    6,
                ],
                [
                    "Table identifier 'ibl_votes_EOY' must be wrapped in backticks for PHPStan rename safety.",
                    7,
                ],
                [
                    "Table identifier 'ibl_draft' must be wrapped in backticks for PHPStan rename safety.",
                    8,
                ],
                [
                    "Table identifier 'ibl_settings' must be wrapped in backticks for PHPStan rename safety.",
                    9,
                ],
            ],
        );
    }

    public function testAllowsBacktickedTableIdentifiers(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BacktickedTableIdentifier.php'],
            [],
        );
    }
}
