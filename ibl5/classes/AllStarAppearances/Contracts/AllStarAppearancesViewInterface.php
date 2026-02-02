<?php

declare(strict_types=1);

namespace AllStarAppearances\Contracts;

/**
 * View interface for All-Star Appearances module rendering.
 *
 * Provides method to render the all-star appearances table.
 */
interface AllStarAppearancesViewInterface
{
    /**
     * Render the all-star appearances table.
     *
     * @param array<int, array{name: string, appearances: int}> $appearances Array of player appearance data
     * @return string HTML output for the all-star appearances table
     */
    public function render(array $appearances): string;
}
