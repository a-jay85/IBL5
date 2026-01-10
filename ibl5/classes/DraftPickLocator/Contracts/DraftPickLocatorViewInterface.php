<?php

declare(strict_types=1);

namespace DraftPickLocator\Contracts;

/**
 * DraftPickLocatorViewInterface - Contract for draft pick locator view rendering
 *
 * Defines methods for generating HTML output for draft pick matrix.
 *
 * @see \DraftPickLocator\DraftPickLocatorView For the concrete implementation
 */
interface DraftPickLocatorViewInterface
{
    /**
     * Render the complete draft pick matrix
     *
     * @param array $teamsWithPicks Teams with their draft pick data
     * @param int $currentEndingYear Current season ending year
     * @return string HTML output
     */
    public function render(array $teamsWithPicks, int $currentEndingYear): string;
}
