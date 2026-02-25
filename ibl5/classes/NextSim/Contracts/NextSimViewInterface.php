<?php

declare(strict_types=1);

namespace NextSim\Contracts;

/**
 * NextSimViewInterface - Contract for next sim view rendering
 *
 * Defines methods for generating HTML output for next sim games.
 *
 * @phpstan-import-type NextSimGameData from \NextSim\Contracts\NextSimServiceInterface
 *
 * @see \NextSim\NextSimView For the concrete implementation
 */
interface NextSimViewInterface
{
    /**
     * Render the complete next sim display
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param \Team $userTeam User's team object
     * @param array<string, \Player\Player> $userStarters User's starting lineup by position
     * @return string HTML output
     */
    public function render(array $games, \Team $userTeam, array $userStarters): string;

    /**
     * Render the full ratings table for a single position
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param string $position Position abbreviation (PG, SG, SF, PF, C)
     * @param \Team $userTeam User's team object
     * @param array<string, \Player\Player> $userStarters User's starting lineup by position
     * @return string HTML table output
     */
    public function renderPositionTable(array $games, string $position, \Team $userTeam, array $userStarters): string;

    /**
     * Render the horizontal schedule strip of compact game cards
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @return string HTML output
     */
    public function renderScheduleStrip(array $games): string;

    /**
     * Render a tabbed position table with TableViewSwitcher tabs
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param string $activePosition Currently active position tab (PG, SG, SF, PF, C)
     * @param \Team $userTeam User's team object
     * @param array<string, \Player\Player> $userStarters User's starting lineup by position
     * @return string HTML output with tabs wrapping the position table
     */
    public function renderTabbedPositionTable(array $games, string $activePosition, \Team $userTeam, array $userStarters): string;
}
