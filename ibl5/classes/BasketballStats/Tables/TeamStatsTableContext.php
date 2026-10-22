<?php

declare(strict_types=1);

namespace BasketballStats\Tables;

use Season\Season;
use Security\HtmlSanitizer;
use Team\Team;
use TeamOffDefStats\TeamOffDefStatsRepository;
use UI\TableStyles;

/**
 * Shared preamble for the team stats tables in this directory.
 *
 * Both helpers are pure static moves out of the table classes. The class holds
 * no state and never receives a logged-in identity; the structural test pins that.
 *
 * @phpstan-import-type TeamOffenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 * @phpstan-import-type TeamDefenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 */
final class TeamStatsTableContext
{
    /**
     * Resolve the season ending year from $yr (empty means the current season)
     * and look up the team's offense/defense totals, regular season first and
     * all games as the fallback.
     *
     * @return array{offense: TeamOffenseStatsRow, defense: TeamDefenseStatsRow}|null
     */
    public static function resolveBothStats(\mysqli $db, Team $team, string $yr): ?array
    {
        $season = \Season\SharedSeason::get($db);
        $seasonYear = ($yr !== '') ? (int) $yr : $season->endingYear;
        $offDefRepo = new TeamOffDefStatsRepository($db);

        return $offDefRepo->getTeamBothStats($team->name, $seasonYear)
            ?? $offDefRepo->getTeamBothStats($team->name, $seasonYear, false);
    }

    /**
     * The <table ...> open tag shared by every team stats table. The aria-label
     * is escaped here and the colours are sanitized by TableStyles, so call sites
     * echo the result through HtmlSanitizer::trusted().
     */
    public static function openTag(Team $team, string $ariaLabel = ''): string
    {
        return '<table class="ibl-data-table team-table responsive-table sortable"'
            . ($ariaLabel !== '' ? ' aria-label="' . HtmlSanitizer::e($ariaLabel) . '"' : '')
            . ' style="' . TableStyles::inlineTeamVars($team->color1, $team->color2) . '">';
    }
}
