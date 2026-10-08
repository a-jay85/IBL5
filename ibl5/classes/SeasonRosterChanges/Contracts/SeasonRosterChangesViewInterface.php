<?php

declare(strict_types=1);

namespace SeasonRosterChanges\Contracts;

/**
 * SeasonRosterChangesViewInterface - Contract for player movement HTML rendering
 *
 * @phpstan-import-type MovementRow from SeasonRosterChangesRepositoryInterface
 *
 * @see \SeasonRosterChanges\SeasonRosterChangesView For the concrete implementation
 */
interface SeasonRosterChangesViewInterface
{
    /**
     * Render the player movement table
     *
     * @param list<MovementRow> $movements Player movement data
     * @return string HTML output
     */
    public function render(array $movements): string;
}
