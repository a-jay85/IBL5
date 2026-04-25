<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans backtick-quoted references to former PascalCase / camelCase column
 * names that were snake_cased by migrations 116–118 (Tier 3a–3c cosmetic
 * case-consistency renames). Prevents the columns from being re-introduced
 * by future PRs.
 *
 * ADR-0010 covers the full Tier 3 roadmap; this rule is extended by PR 2-4.
 *
 * @implements Rule<String_>
 */
final class BanNonSnakeCaseColumnsRule implements Rule
{
    /**
     * Backticked identifiers banned in SQL string literals. Keyed by the
     * exact backtick-quoted token (case-sensitive); value is a short
     * explanation pointing at the canonical name.
     */
    private const BANNED_TOKENS = [
        // Player ratings (ibl_plr, ibl_plr_snapshots, ibl_olympics_plr).
        '`Clutch`' => 'Rename to `clutch`; migration 116 snake-cased player rating columns.',
        '`Consistency`' => 'Rename to `consistency`; migration 116 snake-cased player rating columns.',

        // Position-depth columns (ibl_plr, ibl_plr_snapshots, ibl_olympics_plr).
        '`PGDepth`' => 'Rename to `pg_depth`; migration 116 snake-cased depth columns.',
        '`SGDepth`' => 'Rename to `sg_depth`; migration 116 snake-cased depth columns.',
        '`SFDepth`' => 'Rename to `sf_depth`; migration 116 snake-cased depth columns.',
        '`PFDepth`' => 'Rename to `pf_depth`; migration 116 snake-cased depth columns.',
        '`CDepth`' => 'Rename to `c_depth`; migration 116 snake-cased depth columns.',

        // Depth-chart dc_* columns (ibl_plr, ibl_olympics_plr,
        // ibl_saved_depth_chart_players, ibl_olympics_saved_depth_chart_players).
        '`dc_PGDepth`' => 'Rename to `dc_pg_depth`; migration 116 snake-cased depth-chart columns.',
        '`dc_SGDepth`' => 'Rename to `dc_sg_depth`; migration 116 snake-cased depth-chart columns.',
        '`dc_SFDepth`' => 'Rename to `dc_sf_depth`; migration 116 snake-cased depth-chart columns.',
        '`dc_PFDepth`' => 'Rename to `dc_pf_depth`; migration 116 snake-cased depth-chart columns.',
        '`dc_CDepth`' => 'Rename to `dc_c_depth`; migration 116 snake-cased depth-chart columns.',
        '`dc_canPlayInGame`' => 'Rename to `dc_can_play_in_game`; migration 116 snake-cased depth-chart columns.',

        // Free-agency preference.
        '`playingTime`' => 'Rename to `playing_time`; migration 116 snake-cased FA-pref columns.',

        // Self-documenting renames.
        '`sta`' => 'Rename to `stamina`; migration 116 expanded abbreviated rating columns. (Note: bare `sta` without backticks may legitimately appear in unrelated contexts.)',

        // Team-info columns (ibl_team_info, ibl_olympics_team_info).
        '`discordID`' => 'Rename to `discord_id`; migration 117 snake-cased team-info columns.',
        '`Contract_Wins`' => 'Rename to `contract_wins`; migration 117 snake-cased team-info columns.',
        '`Contract_Losses`' => 'Rename to `contract_losses`; migration 117 snake-cased team-info columns.',
        '`Contract_AvgW`' => 'Rename to `contract_avg_w`; migration 117 snake-cased team-info columns.',
        '`Contract_AvgL`' => 'Rename to `contract_avg_l`; migration 117 snake-cased team-info columns.',
        '`Used_Extension_This_Chunk`' => 'Rename to `used_extension_this_chunk`; migration 117 snake-cased team-info columns.',
        '`Used_Extension_This_Season`' => 'Rename to `used_extension_this_season`; migration 117 snake-cased team-info columns.',
        '`HasMLE`' => 'Rename to `has_mle`; migration 117 snake-cased team-info columns.',
        '`HasLLE`' => 'Rename to `has_lle`; migration 117 snake-cased team-info columns.',

        // Standings columns (ibl_standings, ibl_olympics_standings).
        '`leagueRecord`' => 'Rename to `league_record`; migration 118 snake-cased standings columns.',
        '`confRecord`' => 'Rename to `conf_record`; migration 118 snake-cased standings columns.',
        '`confGB`' => 'Rename to `conf_gb`; migration 118 snake-cased standings columns.',
        '`divRecord`' => 'Rename to `div_record`; migration 118 snake-cased standings columns.',
        '`divGB`' => 'Rename to `div_gb`; migration 118 snake-cased standings columns.',
        '`homeRecord`' => 'Rename to `home_record`; migration 118 snake-cased standings columns.',
        '`awayRecord`' => 'Rename to `away_record`; migration 118 snake-cased standings columns.',
        '`gamesUnplayed`' => 'Rename to `games_unplayed`; migration 118 snake-cased standings columns.',
        '`confWins`' => 'Rename to `conf_wins`; migration 118 snake-cased standings columns.',
        '`confLosses`' => 'Rename to `conf_losses`; migration 118 snake-cased standings columns.',
        '`divWins`' => 'Rename to `div_wins`; migration 118 snake-cased standings columns.',
        '`divLosses`' => 'Rename to `div_losses`; migration 118 snake-cased standings columns.',
        '`homeWins`' => 'Rename to `home_wins`; migration 118 snake-cased standings columns.',
        '`homeLosses`' => 'Rename to `home_losses`; migration 118 snake-cased standings columns.',
        '`awayWins`' => 'Rename to `away_wins`; migration 118 snake-cased standings columns.',
        '`awayLosses`' => 'Rename to `away_losses`; migration 118 snake-cased standings columns.',
        '`confMagicNumber`' => 'Rename to `conf_magic_number`; migration 118 snake-cased standings columns.',
        '`divMagicNumber`' => 'Rename to `div_magic_number`; migration 118 snake-cased standings columns.',
        '`clinchedConference`' => 'Rename to `clinched_conference`; migration 118 snake-cased standings columns.',
        '`clinchedDivision`' => 'Rename to `clinched_division`; migration 118 snake-cased standings columns.',
        '`clinchedPlayoffs`' => 'Rename to `clinched_playoffs`; migration 118 snake-cased standings columns.',
        '`clinchedLeague`' => 'Rename to `clinched_league`; migration 118 snake-cased standings columns.',

        // Contract salary columns (ibl_plr, ibl_plr_snapshots, ibl_olympics_plr,
        // ibl_cash_considerations, ibl_trade_cash, ibl_hist).
        '`cy1`' => 'Rename to `salary_yr1`; migration 119 renamed contract salary columns.',
        '`cy2`' => 'Rename to `salary_yr2`; migration 119 renamed contract salary columns.',
        '`cy3`' => 'Rename to `salary_yr3`; migration 119 renamed contract salary columns.',
        '`cy4`' => 'Rename to `salary_yr4`; migration 119 renamed contract salary columns.',
        '`cy5`' => 'Rename to `salary_yr5`; migration 119 renamed contract salary columns.',
        '`cy6`' => 'Rename to `salary_yr6`; migration 119 renamed contract salary columns.',

        // Migration 120: Tier 5 misc snake_case cleanup (ADR-0010).
        // Trade cash columns (ibl_trade_cash).
        '`tradeOfferID`' => 'Rename to `trade_offer_id`; migration 120 snake-cased trade cash columns.',
        '`sendingTeam`' => 'Rename to `sending_team`; migration 120 snake-cased trade cash columns.',
        '`receivingTeam`' => 'Rename to `receiving_team`; migration 120 snake-cased trade cash columns.',

        // Awards tables (ibl_awards, ibl_gm_awards, ibl_gm_history,
        // ibl_team_awards, ibl_olympics_win_loss).
        '`Award`' => 'Rename to `award`; migration 120 snake-cased awards columns.',
        '`table_ID`' => 'Rename to `table_id`; migration 120 snake-cased awards columns.',
        '`ID`' => 'Rename to `id`; migration 120 snake-cased ibl_team_awards.ID.',

        // Free-agency offer flags (ibl_fa_offers).
        '`MLE`' => 'Rename to `mle`; migration 120 snake-cased FA offer flags.',
        '`LLE`' => 'Rename to `lle`; migration 120 snake-cased FA offer flags.',

        // ASG ballot columns (ibl_votes_ASG).
        '`East_F1`' => 'Rename to `east_f1`; migration 120 snake-cased ASG ballot columns.',
        '`East_F2`' => 'Rename to `east_f2`; migration 120 snake-cased ASG ballot columns.',
        '`East_F3`' => 'Rename to `east_f3`; migration 120 snake-cased ASG ballot columns.',
        '`East_F4`' => 'Rename to `east_f4`; migration 120 snake-cased ASG ballot columns.',
        '`East_B1`' => 'Rename to `east_b1`; migration 120 snake-cased ASG ballot columns.',
        '`East_B2`' => 'Rename to `east_b2`; migration 120 snake-cased ASG ballot columns.',
        '`East_B3`' => 'Rename to `east_b3`; migration 120 snake-cased ASG ballot columns.',
        '`East_B4`' => 'Rename to `east_b4`; migration 120 snake-cased ASG ballot columns.',
        '`West_F1`' => 'Rename to `west_f1`; migration 120 snake-cased ASG ballot columns.',
        '`West_F2`' => 'Rename to `west_f2`; migration 120 snake-cased ASG ballot columns.',
        '`West_F3`' => 'Rename to `west_f3`; migration 120 snake-cased ASG ballot columns.',
        '`West_F4`' => 'Rename to `west_f4`; migration 120 snake-cased ASG ballot columns.',
        '`West_B1`' => 'Rename to `west_b1`; migration 120 snake-cased ASG ballot columns.',
        '`West_B2`' => 'Rename to `west_b2`; migration 120 snake-cased ASG ballot columns.',
        '`West_B3`' => 'Rename to `west_b3`; migration 120 snake-cased ASG ballot columns.',
        '`West_B4`' => 'Rename to `west_b4`; migration 120 snake-cased ASG ballot columns.',

        // EOY ballot columns (ibl_votes_EOY).
        '`MVP_1`' => 'Rename to `mvp_1`; migration 120 snake-cased EOY ballot columns.',
        '`MVP_2`' => 'Rename to `mvp_2`; migration 120 snake-cased EOY ballot columns.',
        '`MVP_3`' => 'Rename to `mvp_3`; migration 120 snake-cased EOY ballot columns.',
        '`ROY_1`' => 'Rename to `roy_1`; migration 120 snake-cased EOY ballot columns.',
        '`ROY_2`' => 'Rename to `roy_2`; migration 120 snake-cased EOY ballot columns.',
        '`ROY_3`' => 'Rename to `roy_3`; migration 120 snake-cased EOY ballot columns.',
        '`GM_1`' => 'Rename to `gm_1`; migration 120 snake-cased EOY ballot columns.',
        '`GM_2`' => 'Rename to `gm_2`; migration 120 snake-cased EOY ballot columns.',
        '`GM_3`' => 'Rename to `gm_3`; migration 120 snake-cased EOY ballot columns.',
        '`Six_1`' => 'Rename to `six_1`; migration 120 snake-cased EOY ballot columns.',
        '`Six_2`' => 'Rename to `six_2`; migration 120 snake-cased EOY ballot columns.',
        '`Six_3`' => 'Rename to `six_3`; migration 120 snake-cased EOY ballot columns.',

        // Sim sequence (ibl_sim_dates).
        '`Sim`' => 'Rename to `sim`; migration 120 snake-cased ibl_sim_dates.Sim.',

        // PHP-Nuke config + stories (nuke_config, nuke_stories).
        '`CensorMode`' => 'Rename to `censor_mode`; migration 120 snake-cased nuke_config columns.',
        '`CensorReplace`' => 'Rename to `censor_replace`; migration 120 snake-cased nuke_config columns.',
        '`Default_Theme`' => 'Rename to `default_theme`; migration 120 snake-cased nuke_config columns.',
        '`Version_Num`' => 'Rename to `version_num`; migration 120 snake-cased nuke_config columns.',
        '`pollID`' => 'Rename to `poll_id`; migration 120 snake-cased nuke_stories.pollID.',
    ];

    public function getNodeType(): string
    {
        return String_::class;
    }

    /**
     * @param String_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Enforce in classes/ and html/ only — tests, migrations, and scripts
        // may still reference old names in comments or historical fixtures.
        $isClassesFile = str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR);
        $isHtmlFile = str_contains($file, DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR);

        if (!$isClassesFile && !$isHtmlFile) {
            return [];
        }

        $value = $node->value;
        $errors = [];

        foreach (self::BANNED_TOKENS as $token => $guidance) {
            if (str_contains($value, $token)) {
                $errors[] = RuleErrorBuilder::message(
                    'Banned backtick-quoted column reference ' . $token . ' in SQL string. '
                    . $guidance
                )
                    ->identifier('ibl.bannedNonSnakeCaseColumn')
                    ->build();
            }
        }

        return $errors;
    }
}
