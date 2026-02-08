<?php

declare(strict_types=1);

namespace LeagueStarters\Contracts;

/**
 * LeagueStartersServiceInterface - Contract for league starters business logic
 *
 * Defines methods for retrieving starting lineups for all teams.
 *
 * @see \LeagueStarters\LeagueStartersService For the concrete implementation
 */
interface LeagueStartersServiceInterface
{
    /**
     * Get all starters by position across the league
     *
     * @return array<string, array<int, \Player\Player>> Starters organized by position
     */
    public function getAllStartersByPosition(): array;
}
