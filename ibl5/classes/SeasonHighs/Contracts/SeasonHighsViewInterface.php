<?php

declare(strict_types=1);

namespace SeasonHighs\Contracts;

/**
 * View interface for Season Highs module rendering.
 *
 * Provides method to render the season highs page.
 *
 * @phpstan-import-type SeasonHighsData from SeasonHighsServiceInterface
 * @phpstan-import-type RcbSeasonHighEntry from SeasonHighsServiceInterface
 */
interface SeasonHighsViewInterface
{
    /**
     * Render the season highs page.
     *
     * @param string $seasonPhase Current season phase
     * @param SeasonHighsData $data Season highs data
     * @return string HTML output for the season highs page
     */
    public function render(string $seasonPhase, array $data): string;

    /**
     * Render home/away season highs from RCB data.
     *
     * @param array{home: array<string, list<RcbSeasonHighEntry>>, away: array<string, list<RcbSeasonHighEntry>>} $data
     * @return string HTML output
     */
    public function renderHomeAwayHighs(array $data): string;
}
