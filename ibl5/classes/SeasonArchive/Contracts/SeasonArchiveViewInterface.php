<?php

declare(strict_types=1);

namespace SeasonArchive\Contracts;

/**
 * SeasonArchiveViewInterface - Contract for season archive HTML rendering
 *
 * Defines methods for generating HTML output for the season archive
 * index page and individual season detail pages.
 *
 * @phpstan-import-type SeasonSummary from SeasonArchiveServiceInterface
 * @phpstan-import-type SeasonDetail from SeasonArchiveServiceInterface
 *
 * @see \SeasonArchive\SeasonArchiveView For the concrete implementation
 */
interface SeasonArchiveViewInterface
{
    /**
     * Render the season archive index page
     *
     * Displays a table of all seasons with links to detail pages.
     *
     * @param list<SeasonSummary> $seasons Array of season summaries
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     * @param array<string, int> $playerIds
     * @param array<string, int> $teamIds
     * @return string HTML output
     */
    public function renderIndex(
        array $seasons,
        array $teamColors = [],
        array $playerIds = [],
        array $teamIds = []
    ): string;

    /**
     * Render a single season detail page
     *
     * Displays all awards, standings, bracket, and roster data for a season.
     *
     * @param SeasonDetail $seasonData Full season data
     * @return string HTML output
     */
    public function renderSeasonDetail(array $seasonData): string;
}
