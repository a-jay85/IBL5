<?php

declare(strict_types=1);

namespace Standings\Contracts;

/**
 * StandingsViewInterface - Contract for standings HTML rendering
 *
 * Defines methods for generating HTML output for standings display.
 * Implementations handle all presentation logic including clinched indicators
 * and sortable table generation.
 *
 * @see \Standings\StandingsView For the concrete implementation
 */
interface StandingsViewInterface
{
    /**
     * Render the complete standings page HTML
     *
     * Generates HTML for both conferences and all divisions.
     *
     * @return string Complete HTML for standings page
     */
    public function render(): string;

    /**
     * Render standings for a specific region
     *
     * @param string $region Region name (e.g., 'Eastern', 'Atlantic')
     * @return string HTML for the region's standings table
     */
    public function renderRegion(string $region): string;
}
