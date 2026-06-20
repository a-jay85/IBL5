<?php

declare(strict_types=1);

namespace SeasonArchive\Contracts;

/**
 * SeasonDetailViewInterface - Contract for season detail page rendering
 *
 * @phpstan-import-type SeasonDetail from SeasonArchiveServiceInterface
 * @phpstan-import-type PlayoffSeries from SeasonArchiveServiceInterface
 *
 * @see \SeasonArchive\SeasonDetailView For the concrete implementation
 */
interface SeasonDetailViewInterface
{
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
