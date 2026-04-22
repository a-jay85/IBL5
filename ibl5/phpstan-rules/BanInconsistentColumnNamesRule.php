<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans backtick-quoted references to former divergent column names that were
 * unified by migration 114 (Tier 2 cross-table column-naming unification).
 * Prevents the columns from being re-introduced by future PRs.
 *
 * Three concept families covered:
 *   - Turnovers: `stats_to` → `stats_tvr` (live layer)
 *   - 3-pointer ratings: `r_tga`/`r_tgp` (live + olympics_plr) and `tga`/`tgp`
 *     (ibl_draft_class) → `r_3ga`/`r_3gp`
 *   - Team-id: `tid`, `teamID`, `TeamID`, `team_id`, `homeTID`/`visitorTID`,
 *     `homeTeamID`/`visitorTeamID`, `owner_tid`/`teampick_tid` → `teamid` /
 *     `home_teamid` / `visitor_teamid` / `owner_teamid` / `teampick_teamid`
 *
 * @implements Rule<String_>
 */
final class BanInconsistentColumnNamesRule implements Rule
{
    /**
     * Backticked identifiers banned in SQL string literals. Keyed by the
     * exact backtick-quoted token (case-sensitive); value is a short
     * explanation pointing at the canonical name.
     */
    private const BANNED_TOKENS = [
        // Turnovers
        '`stats_to`' => 'Rename to `stats_tvr` (turnovers, live layer); migration 114 unified `stats_to`/`tvr`.',

        // 3-pointer ratings. Note: bare `tga`/`tgp` are NOT banned — they
        // remain valid counting-stat column names on `ibl_hist` and the
        // olympics career tables. The migration renamed only the *rating*
        // columns on `ibl_draft_class` (tga→r_3ga, tgp→r_3gp); any surviving
        // `` `tga` `` against `ibl_draft_class` would fail at runtime via
        // `SchemaValidator` boot assertions + the CrossTableColumnNamingTest.
        '`r_tga`' => 'Rename to `r_3ga` (3P attempts rating); migration 114 unified the rating to match `ibl_hist`.',
        '`r_tgp`' => 'Rename to `r_3gp` (3P percentage rating); migration 114 unified the rating to match `ibl_hist`.',

        // Team-id (bare)
        '`tid`' => 'Rename to `teamid`; migration 114 unified team-id spelling across the schema.',
        '`team_id`' => 'Rename to `teamid`; migration 114 dropped the underscore variant.',
        '`teamID`' => 'Rename to `teamid`; migration 114 dropped the camelCase variant.',
        '`TeamID`' => 'Rename to `teamid`; migration 114 dropped the PascalCase variant.',

        // Team-id (compound)
        '`homeTID`' => 'Rename to `home_teamid`; migration 114 unified compound team-id columns.',
        '`visitorTID`' => 'Rename to `visitor_teamid`; migration 114 unified compound team-id columns.',
        '`homeTeamID`' => 'Rename to `home_teamid`; migration 114 unified compound team-id columns.',
        '`visitorTeamID`' => 'Rename to `visitor_teamid`; migration 114 unified compound team-id columns.',
        '`owner_tid`' => 'Rename to `owner_teamid`; migration 114 unified `*_tid` to `*_teamid`.',
        '`teampick_tid`' => 'Rename to `teampick_teamid`; migration 114 unified `*_tid` to `*_teamid`.',
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
                    ->identifier('ibl.bannedInconsistentColumnName')
                    ->build();
            }
        }

        return $errors;
    }
}
