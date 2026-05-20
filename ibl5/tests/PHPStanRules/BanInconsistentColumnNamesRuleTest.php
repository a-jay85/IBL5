<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanInconsistentColumnNamesRule;

/**
 * @extends RuleTestCase<BanInconsistentColumnNamesRule>
 */
final class BanInconsistentColumnNamesRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanInconsistentColumnNamesRule();
    }

    public function testFlagsBannedBacktickColumns(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BannedInconsistentColumns.php'],
            [
                [
                    'Banned backtick-quoted column reference `car_to` in SQL string. '
                    . 'Rename to `car_tvr` (career turnovers); migration 128 unified career names with `stats_tvr`.',
                    5,
                ],
                [
                    'Banned backtick-quoted column reference `car_tgm` in SQL string. '
                    . 'Rename to `car_3gm` (career 3-pointers made); migration 128 aligned with `game_3gm` family.',
                    6,
                ],
                [
                    'Banned backtick-quoted column reference `car_tga` in SQL string. '
                    . 'Rename to `car_3ga` (career 3-pointers attempted); migration 128 aligned with `game_3ga` family.',
                    7,
                ],
                [
                    'Banned backtick-quoted column reference `stats_to` in SQL string. '
                    . 'Rename to `stats_tvr` (turnovers, live layer); migration 114 unified `stats_to`/`tvr`.',
                    8,
                ],
                [
                    'Banned backtick-quoted column reference `r_tga` in SQL string. '
                    . 'Rename to `r_3ga` (3P attempts rating); migration 114 unified the rating to match `ibl_hist`.',
                    9,
                ],
                [
                    'Banned backtick-quoted column reference `r_tgp` in SQL string. '
                    . 'Rename to `r_3gp` (3P percentage rating); migration 114 unified the rating to match `ibl_hist`.',
                    10,
                ],
                [
                    'Banned backtick-quoted column reference `tid` in SQL string. '
                    . 'Rename to `teamid`; migration 114 unified team-id spelling across the schema.',
                    11,
                ],
                [
                    'Banned backtick-quoted column reference `team_id` in SQL string. '
                    . 'Rename to `teamid`; migration 114 dropped the underscore variant.',
                    12,
                ],
                [
                    'Banned backtick-quoted column reference `teamID` in SQL string. '
                    . 'Rename to `teamid`; migration 114 dropped the camelCase variant.',
                    13,
                ],
                [
                    'Banned backtick-quoted column reference `TeamID` in SQL string. '
                    . 'Rename to `teamid`; migration 114 dropped the PascalCase variant.',
                    14,
                ],
                [
                    'Banned backtick-quoted column reference `homeTID` in SQL string. '
                    . 'Rename to `home_teamid`; migration 114 unified compound team-id columns.',
                    15,
                ],
                [
                    'Banned backtick-quoted column reference `visitorTID` in SQL string. '
                    . 'Rename to `visitor_teamid`; migration 114 unified compound team-id columns.',
                    16,
                ],
                [
                    'Banned backtick-quoted column reference `homeTeamID` in SQL string. '
                    . 'Rename to `home_teamid`; migration 114 unified compound team-id columns.',
                    17,
                ],
                [
                    'Banned backtick-quoted column reference `visitorTeamID` in SQL string. '
                    . 'Rename to `visitor_teamid`; migration 114 unified compound team-id columns.',
                    18,
                ],
                [
                    'Banned backtick-quoted column reference `owner_tid` in SQL string. '
                    . 'Rename to `owner_teamid`; migration 114 unified `*_tid` to `*_teamid`.',
                    19,
                ],
                [
                    'Banned backtick-quoted column reference `teampick_tid` in SQL string. '
                    . 'Rename to `teampick_teamid`; migration 114 unified `*_tid` to `*_teamid`.',
                    20,
                ],
            ],
        );
    }

    public function testAllowsPostMigrationNames(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/AllowedUnifiedColumnNames.php'],
            [],
        );
    }
}
