<?php

declare(strict_types=1);

namespace SeasonHighs\Contracts;

/**
 * View interface for Season Highs module rendering.
 *
 * Provides method to render the season highs page.
 */
interface SeasonHighsViewInterface
{
    /**
     * Render the season highs page.
     *
     * @param string $seasonPhase Current season phase
     * @param array{
     *     playerHighs: array<string, array<int, array{name: string, date: string, value: int}>>,
     *     teamHighs: array<string, array<int, array{name: string, date: string, value: int>>>
     * } $data Season highs data
     * @return string HTML output for the season highs page
     */
    public function render(string $seasonPhase, array $data): string;
}
