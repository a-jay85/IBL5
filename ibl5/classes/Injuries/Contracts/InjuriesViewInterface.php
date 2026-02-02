<?php

declare(strict_types=1);

namespace Injuries\Contracts;

/**
 * View interface for Injuries module rendering.
 *
 * Provides methods to render injured players table.
 */
interface InjuriesViewInterface
{
    /**
     * Render the injured players table.
     *
     * @param array<int, array{
     *     playerID: int,
     *     name: string,
     *     position: string,
     *     daysRemaining: int,
     *     returnDate: string,
     *     teamID: int,
     *     teamCity: string,
     *     teamName: string,
     *     teamColor1: string,
     *     teamColor2: string
     * }> $injuredPlayers Array of injured player data
     * @return string HTML output for the injured players table
     */
    public function render(array $injuredPlayers): string;
}
