<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanNonSnakeCaseColumnsRule;

/**
 * @extends RuleTestCase<BanNonSnakeCaseColumnsRule>
 */
final class BanNonSnakeCaseColumnsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanNonSnakeCaseColumnsRule();
    }

    public function testFlagsBannedBacktickColumns(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/BannedNonSnakeCaseColumns.php'],
            [
                [
                    'Banned backtick-quoted column reference `Clutch` in SQL string. '
                    . 'Rename to `clutch`; migration 116 snake-cased player rating columns.',
                    5,
                ],
                [
                    'Banned backtick-quoted column reference `Consistency` in SQL string. '
                    . 'Rename to `consistency`; migration 116 snake-cased player rating columns.',
                    6,
                ],
                [
                    'Banned backtick-quoted column reference `PGDepth` in SQL string. '
                    . 'Rename to `pg_depth`; migration 116 snake-cased depth columns.',
                    7,
                ],
                [
                    'Banned backtick-quoted column reference `SGDepth` in SQL string. '
                    . 'Rename to `sg_depth`; migration 116 snake-cased depth columns.',
                    8,
                ],
                [
                    'Banned backtick-quoted column reference `SFDepth` in SQL string. '
                    . 'Rename to `sf_depth`; migration 116 snake-cased depth columns.',
                    9,
                ],
                [
                    'Banned backtick-quoted column reference `PFDepth` in SQL string. '
                    . 'Rename to `pf_depth`; migration 116 snake-cased depth columns.',
                    10,
                ],
                [
                    'Banned backtick-quoted column reference `CDepth` in SQL string. '
                    . 'Rename to `c_depth`; migration 116 snake-cased depth columns.',
                    11,
                ],
                [
                    'Banned backtick-quoted column reference `dc_PGDepth` in SQL string. '
                    . 'Rename to `dc_pg_depth`; migration 116 snake-cased depth-chart columns.',
                    12,
                ],
                [
                    'Banned backtick-quoted column reference `dc_SGDepth` in SQL string. '
                    . 'Rename to `dc_sg_depth`; migration 116 snake-cased depth-chart columns.',
                    13,
                ],
                [
                    'Banned backtick-quoted column reference `dc_SFDepth` in SQL string. '
                    . 'Rename to `dc_sf_depth`; migration 116 snake-cased depth-chart columns.',
                    14,
                ],
                [
                    'Banned backtick-quoted column reference `dc_PFDepth` in SQL string. '
                    . 'Rename to `dc_pf_depth`; migration 116 snake-cased depth-chart columns.',
                    15,
                ],
                [
                    'Banned backtick-quoted column reference `dc_CDepth` in SQL string. '
                    . 'Rename to `dc_c_depth`; migration 116 snake-cased depth-chart columns.',
                    16,
                ],
                [
                    'Banned backtick-quoted column reference `dc_canPlayInGame` in SQL string. '
                    . 'Rename to `dc_can_play_in_game`; migration 116 snake-cased depth-chart columns.',
                    17,
                ],
                [
                    'Banned backtick-quoted column reference `playingTime` in SQL string. '
                    . 'Rename to `playing_time`; migration 116 snake-cased FA-pref columns.',
                    18,
                ],
                [
                    'Banned backtick-quoted column reference `sta` in SQL string. '
                    . 'Rename to `stamina`; migration 116 expanded abbreviated rating columns. '
                    . '(Note: bare `sta` without backticks may legitimately appear in unrelated contexts.)',
                    19,
                ],
                [
                    'Banned backtick-quoted column reference `discordID` in SQL string. '
                    . 'Rename to `discord_id`; migration 117 snake-cased team-info columns.',
                    20,
                ],
                [
                    'Banned backtick-quoted column reference `Contract_Wins` in SQL string. '
                    . 'Rename to `contract_wins`; migration 117 snake-cased team-info columns.',
                    21,
                ],
                [
                    'Banned backtick-quoted column reference `Contract_Losses` in SQL string. '
                    . 'Rename to `contract_losses`; migration 117 snake-cased team-info columns.',
                    22,
                ],
                [
                    'Banned backtick-quoted column reference `Contract_AvgW` in SQL string. '
                    . 'Rename to `contract_avg_w`; migration 117 snake-cased team-info columns.',
                    23,
                ],
                [
                    'Banned backtick-quoted column reference `Contract_AvgL` in SQL string. '
                    . 'Rename to `contract_avg_l`; migration 117 snake-cased team-info columns.',
                    24,
                ],
                [
                    'Banned backtick-quoted column reference `Used_Extension_This_Chunk` in SQL string. '
                    . 'Rename to `used_extension_this_chunk`; migration 117 snake-cased team-info columns.',
                    25,
                ],
                [
                    'Banned backtick-quoted column reference `Used_Extension_This_Season` in SQL string. '
                    . 'Rename to `used_extension_this_season`; migration 117 snake-cased team-info columns.',
                    26,
                ],
                [
                    'Banned backtick-quoted column reference `HasMLE` in SQL string. '
                    . 'Rename to `has_mle`; migration 117 snake-cased team-info columns.',
                    27,
                ],
                [
                    'Banned backtick-quoted column reference `HasLLE` in SQL string. '
                    . 'Rename to `has_lle`; migration 117 snake-cased team-info columns.',
                    28,
                ],
            ],
        );
    }

    public function testAllowsPostMigrationNames(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/AllowedSnakeCaseColumnNames.php'],
            [],
        );
    }
}
