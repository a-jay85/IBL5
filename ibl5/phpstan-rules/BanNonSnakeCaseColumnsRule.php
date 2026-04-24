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
 * names that were snake_cased by migrations 116–117 (Tier 3a–3b cosmetic
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
