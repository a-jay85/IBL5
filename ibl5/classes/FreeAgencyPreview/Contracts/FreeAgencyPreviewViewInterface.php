<?php

declare(strict_types=1);

namespace FreeAgencyPreview\Contracts;

/**
 * View interface for Free Agency Preview module rendering.
 *
 * Provides method to render the free agency preview table.
 */
interface FreeAgencyPreviewViewInterface
{
    /**
     * Render the free agency preview page.
     *
     * @param int $seasonEndingYear The ending year of the current season
     * @param array<int, array> $freeAgents Array of upcoming free agents
     * @return string HTML output for the free agency preview page
     */
    public function render(int $seasonEndingYear, array $freeAgents): string;
}
