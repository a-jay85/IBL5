<?php

declare(strict_types=1);

namespace SeasonArchive\Contracts;

/**
 * SeasonArchiveIndexViewInterface - Contract for season archive index page rendering
 *
 * @phpstan-import-type SeasonSummary from SeasonArchiveServiceInterface
 *
 * @see \SeasonArchive\SeasonArchiveIndexView For the concrete implementation
 */
interface SeasonArchiveIndexViewInterface
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
}
