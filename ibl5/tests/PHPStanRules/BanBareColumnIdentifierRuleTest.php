<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanBareColumnIdentifierRule;

/**
 * @extends RuleTestCase<BanBareColumnIdentifierRule>
 */
final class BanBareColumnIdentifierRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanBareColumnIdentifierRule();
    }

    public function testFlagsBareTableQualifiedColumns(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BareColumnIdentifier.php'],
            [
                [
                    "Table-qualified column 'ibl_plr.pid' must be wrapped in backticks "
                    . '(`ibl_plr`.`pid`) for PHPStan rename safety.',
                    5,
                ],
                [
                    "Table-qualified column 'ibl_team_info.team_name' must be wrapped in backticks "
                    . '(`ibl_team_info`.`team_name`) for PHPStan rename safety.',
                    6,
                ],
                [
                    "Table-qualified column 'ibl_plr.retired' must be wrapped in backticks "
                    . '(`ibl_plr`.`retired`) for PHPStan rename safety.',
                    7,
                ],
            ],
        );
    }

    public function testAllowsBacktickedAliasedBareAndStarColumns(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BacktickedColumnIdentifier.php'],
            [],
        );
    }
}
