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
                [
                    'Banned backtick-quoted column reference `leagueRecord` in SQL string. '
                    . 'Rename to `league_record`; migration 118 snake-cased standings columns.',
                    29,
                ],
                [
                    'Banned backtick-quoted column reference `confRecord` in SQL string. '
                    . 'Rename to `conf_record`; migration 118 snake-cased standings columns.',
                    30,
                ],
                [
                    'Banned backtick-quoted column reference `confGB` in SQL string. '
                    . 'Rename to `conf_gb`; migration 118 snake-cased standings columns.',
                    31,
                ],
                [
                    'Banned backtick-quoted column reference `divRecord` in SQL string. '
                    . 'Rename to `div_record`; migration 118 snake-cased standings columns.',
                    32,
                ],
                [
                    'Banned backtick-quoted column reference `divGB` in SQL string. '
                    . 'Rename to `div_gb`; migration 118 snake-cased standings columns.',
                    33,
                ],
                [
                    'Banned backtick-quoted column reference `homeRecord` in SQL string. '
                    . 'Rename to `home_record`; migration 118 snake-cased standings columns.',
                    34,
                ],
                [
                    'Banned backtick-quoted column reference `awayRecord` in SQL string. '
                    . 'Rename to `away_record`; migration 118 snake-cased standings columns.',
                    35,
                ],
                [
                    'Banned backtick-quoted column reference `gamesUnplayed` in SQL string. '
                    . 'Rename to `games_unplayed`; migration 118 snake-cased standings columns.',
                    36,
                ],
                [
                    'Banned backtick-quoted column reference `confWins` in SQL string. '
                    . 'Rename to `conf_wins`; migration 118 snake-cased standings columns.',
                    37,
                ],
                [
                    'Banned backtick-quoted column reference `confLosses` in SQL string. '
                    . 'Rename to `conf_losses`; migration 118 snake-cased standings columns.',
                    38,
                ],
                [
                    'Banned backtick-quoted column reference `divWins` in SQL string. '
                    . 'Rename to `div_wins`; migration 118 snake-cased standings columns.',
                    39,
                ],
                [
                    'Banned backtick-quoted column reference `divLosses` in SQL string. '
                    . 'Rename to `div_losses`; migration 118 snake-cased standings columns.',
                    40,
                ],
                [
                    'Banned backtick-quoted column reference `homeWins` in SQL string. '
                    . 'Rename to `home_wins`; migration 118 snake-cased standings columns.',
                    41,
                ],
                [
                    'Banned backtick-quoted column reference `homeLosses` in SQL string. '
                    . 'Rename to `home_losses`; migration 118 snake-cased standings columns.',
                    42,
                ],
                [
                    'Banned backtick-quoted column reference `awayWins` in SQL string. '
                    . 'Rename to `away_wins`; migration 118 snake-cased standings columns.',
                    43,
                ],
                [
                    'Banned backtick-quoted column reference `awayLosses` in SQL string. '
                    . 'Rename to `away_losses`; migration 118 snake-cased standings columns.',
                    44,
                ],
                [
                    'Banned backtick-quoted column reference `confMagicNumber` in SQL string. '
                    . 'Rename to `conf_magic_number`; migration 118 snake-cased standings columns.',
                    45,
                ],
                [
                    'Banned backtick-quoted column reference `divMagicNumber` in SQL string. '
                    . 'Rename to `div_magic_number`; migration 118 snake-cased standings columns.',
                    46,
                ],
                [
                    'Banned backtick-quoted column reference `clinchedConference` in SQL string. '
                    . 'Rename to `clinched_conference`; migration 118 snake-cased standings columns.',
                    47,
                ],
                [
                    'Banned backtick-quoted column reference `clinchedDivision` in SQL string. '
                    . 'Rename to `clinched_division`; migration 118 snake-cased standings columns.',
                    48,
                ],
                [
                    'Banned backtick-quoted column reference `clinchedPlayoffs` in SQL string. '
                    . 'Rename to `clinched_playoffs`; migration 118 snake-cased standings columns.',
                    49,
                ],
                [
                    'Banned backtick-quoted column reference `clinchedLeague` in SQL string. '
                    . 'Rename to `clinched_league`; migration 118 snake-cased standings columns.',
                    50,
                ],
                [
                    'Banned backtick-quoted column reference `cy1` in SQL string. '
                    . 'Rename to `salary_yr1`; migration 119 renamed contract salary columns.',
                    51,
                ],
                [
                    'Banned backtick-quoted column reference `cy2` in SQL string. '
                    . 'Rename to `salary_yr2`; migration 119 renamed contract salary columns.',
                    52,
                ],
                [
                    'Banned backtick-quoted column reference `cy3` in SQL string. '
                    . 'Rename to `salary_yr3`; migration 119 renamed contract salary columns.',
                    53,
                ],
                [
                    'Banned backtick-quoted column reference `cy4` in SQL string. '
                    . 'Rename to `salary_yr4`; migration 119 renamed contract salary columns.',
                    54,
                ],
                [
                    'Banned backtick-quoted column reference `cy5` in SQL string. '
                    . 'Rename to `salary_yr5`; migration 119 renamed contract salary columns.',
                    55,
                ],
                [
                    'Banned backtick-quoted column reference `cy6` in SQL string. '
                    . 'Rename to `salary_yr6`; migration 119 renamed contract salary columns.',
                    56,
                ],
                [
                    'Banned backtick-quoted column reference `tradeOfferID` in SQL string. '
                    . 'Rename to `trade_offer_id`; migration 120 snake-cased trade cash columns.',
                    57,
                ],
                [
                    'Banned backtick-quoted column reference `sendingTeam` in SQL string. '
                    . 'Rename to `sending_team`; migration 120 snake-cased trade cash columns.',
                    58,
                ],
                [
                    'Banned backtick-quoted column reference `receivingTeam` in SQL string. '
                    . 'Rename to `receiving_team`; migration 120 snake-cased trade cash columns.',
                    59,
                ],
                [
                    'Banned backtick-quoted column reference `Award` in SQL string. '
                    . 'Rename to `award`; migration 120 snake-cased awards columns.',
                    60,
                ],
                [
                    'Banned backtick-quoted column reference `table_ID` in SQL string. '
                    . 'Rename to `table_id`; migration 120 snake-cased awards columns.',
                    61,
                ],
                [
                    'Banned backtick-quoted column reference `ID` in SQL string. '
                    . 'Rename to `id`; migration 120 snake-cased ibl_team_awards.ID.',
                    62,
                ],
                [
                    'Banned backtick-quoted column reference `MLE` in SQL string. '
                    . 'Rename to `mle`; migration 120 snake-cased FA offer flags.',
                    63,
                ],
                [
                    'Banned backtick-quoted column reference `LLE` in SQL string. '
                    . 'Rename to `lle`; migration 120 snake-cased FA offer flags.',
                    64,
                ],
                [
                    'Banned backtick-quoted column reference `East_F1` in SQL string. '
                    . 'Rename to `east_f1`; migration 120 snake-cased ASG ballot columns.',
                    65,
                ],
                [
                    'Banned backtick-quoted column reference `East_F2` in SQL string. '
                    . 'Rename to `east_f2`; migration 120 snake-cased ASG ballot columns.',
                    66,
                ],
                [
                    'Banned backtick-quoted column reference `East_F3` in SQL string. '
                    . 'Rename to `east_f3`; migration 120 snake-cased ASG ballot columns.',
                    67,
                ],
                [
                    'Banned backtick-quoted column reference `East_F4` in SQL string. '
                    . 'Rename to `east_f4`; migration 120 snake-cased ASG ballot columns.',
                    68,
                ],
                [
                    'Banned backtick-quoted column reference `East_B1` in SQL string. '
                    . 'Rename to `east_b1`; migration 120 snake-cased ASG ballot columns.',
                    69,
                ],
                [
                    'Banned backtick-quoted column reference `East_B2` in SQL string. '
                    . 'Rename to `east_b2`; migration 120 snake-cased ASG ballot columns.',
                    70,
                ],
                [
                    'Banned backtick-quoted column reference `East_B3` in SQL string. '
                    . 'Rename to `east_b3`; migration 120 snake-cased ASG ballot columns.',
                    71,
                ],
                [
                    'Banned backtick-quoted column reference `East_B4` in SQL string. '
                    . 'Rename to `east_b4`; migration 120 snake-cased ASG ballot columns.',
                    72,
                ],
                [
                    'Banned backtick-quoted column reference `West_F1` in SQL string. '
                    . 'Rename to `west_f1`; migration 120 snake-cased ASG ballot columns.',
                    73,
                ],
                [
                    'Banned backtick-quoted column reference `West_F2` in SQL string. '
                    . 'Rename to `west_f2`; migration 120 snake-cased ASG ballot columns.',
                    74,
                ],
                [
                    'Banned backtick-quoted column reference `West_F3` in SQL string. '
                    . 'Rename to `west_f3`; migration 120 snake-cased ASG ballot columns.',
                    75,
                ],
                [
                    'Banned backtick-quoted column reference `West_F4` in SQL string. '
                    . 'Rename to `west_f4`; migration 120 snake-cased ASG ballot columns.',
                    76,
                ],
                [
                    'Banned backtick-quoted column reference `West_B1` in SQL string. '
                    . 'Rename to `west_b1`; migration 120 snake-cased ASG ballot columns.',
                    77,
                ],
                [
                    'Banned backtick-quoted column reference `West_B2` in SQL string. '
                    . 'Rename to `west_b2`; migration 120 snake-cased ASG ballot columns.',
                    78,
                ],
                [
                    'Banned backtick-quoted column reference `West_B3` in SQL string. '
                    . 'Rename to `west_b3`; migration 120 snake-cased ASG ballot columns.',
                    79,
                ],
                [
                    'Banned backtick-quoted column reference `West_B4` in SQL string. '
                    . 'Rename to `west_b4`; migration 120 snake-cased ASG ballot columns.',
                    80,
                ],
                [
                    'Banned backtick-quoted column reference `MVP_1` in SQL string. '
                    . 'Rename to `mvp_1`; migration 120 snake-cased EOY ballot columns.',
                    81,
                ],
                [
                    'Banned backtick-quoted column reference `MVP_2` in SQL string. '
                    . 'Rename to `mvp_2`; migration 120 snake-cased EOY ballot columns.',
                    82,
                ],
                [
                    'Banned backtick-quoted column reference `MVP_3` in SQL string. '
                    . 'Rename to `mvp_3`; migration 120 snake-cased EOY ballot columns.',
                    83,
                ],
                [
                    'Banned backtick-quoted column reference `ROY_1` in SQL string. '
                    . 'Rename to `roy_1`; migration 120 snake-cased EOY ballot columns.',
                    84,
                ],
                [
                    'Banned backtick-quoted column reference `ROY_2` in SQL string. '
                    . 'Rename to `roy_2`; migration 120 snake-cased EOY ballot columns.',
                    85,
                ],
                [
                    'Banned backtick-quoted column reference `ROY_3` in SQL string. '
                    . 'Rename to `roy_3`; migration 120 snake-cased EOY ballot columns.',
                    86,
                ],
                [
                    'Banned backtick-quoted column reference `GM_1` in SQL string. '
                    . 'Rename to `gm_1`; migration 120 snake-cased EOY ballot columns.',
                    87,
                ],
                [
                    'Banned backtick-quoted column reference `GM_2` in SQL string. '
                    . 'Rename to `gm_2`; migration 120 snake-cased EOY ballot columns.',
                    88,
                ],
                [
                    'Banned backtick-quoted column reference `GM_3` in SQL string. '
                    . 'Rename to `gm_3`; migration 120 snake-cased EOY ballot columns.',
                    89,
                ],
                [
                    'Banned backtick-quoted column reference `Six_1` in SQL string. '
                    . 'Rename to `six_1`; migration 120 snake-cased EOY ballot columns.',
                    90,
                ],
                [
                    'Banned backtick-quoted column reference `Six_2` in SQL string. '
                    . 'Rename to `six_2`; migration 120 snake-cased EOY ballot columns.',
                    91,
                ],
                [
                    'Banned backtick-quoted column reference `Six_3` in SQL string. '
                    . 'Rename to `six_3`; migration 120 snake-cased EOY ballot columns.',
                    92,
                ],
                [
                    'Banned backtick-quoted column reference `Sim` in SQL string. '
                    . 'Rename to `sim`; migration 120 snake-cased ibl_sim_dates.Sim.',
                    93,
                ],
                [
                    'Banned backtick-quoted column reference `CensorMode` in SQL string. '
                    . 'Rename to `censor_mode`; migration 120 snake-cased nuke_config columns.',
                    94,
                ],
                [
                    'Banned backtick-quoted column reference `CensorReplace` in SQL string. '
                    . 'Rename to `censor_replace`; migration 120 snake-cased nuke_config columns.',
                    95,
                ],
                [
                    'Banned backtick-quoted column reference `Default_Theme` in SQL string. '
                    . 'Rename to `default_theme`; migration 120 snake-cased nuke_config columns.',
                    96,
                ],
                [
                    'Banned backtick-quoted column reference `Version_Num` in SQL string. '
                    . 'Rename to `version_num`; migration 120 snake-cased nuke_config columns.',
                    97,
                ],
                [
                    'Banned backtick-quoted column reference `pollID` in SQL string. '
                    . 'Rename to `poll_id`; migration 120 snake-cased nuke_stories.pollID.',
                    98,
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
