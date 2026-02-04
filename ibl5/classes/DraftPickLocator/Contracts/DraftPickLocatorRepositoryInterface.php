<?php

declare(strict_types=1);

namespace DraftPickLocator\Contracts;

/**
 * DraftPickLocatorRepositoryInterface - Contract for draft pick data access
 *
 * Defines methods for retrieving draft pick ownership data.
 *
 * @phpstan-type TeamInfoRow array{teamid: int, team_city: string, team_name: string, color1: string, color2: string}
 * @phpstan-type DraftPickRow array{ownerofpick: string, year: int, round: int}
 *
 * @see \DraftPickLocator\DraftPickLocatorRepository For the concrete implementation
 */
interface DraftPickLocatorRepositoryInterface
{
    /**
     * Get all teams with basic info
     *
     * @return list<array{teamid: int, team_city: string, team_name: string, color1: string, color2: string}>
     */
    public function getAllTeams(): array;

    /**
     * Get draft picks for a specific team
     *
     * @param string $teamName Team name
     * @return list<array{ownerofpick: string, year: int, round: int}>
     */
    public function getDraftPicksForTeam(string $teamName): array;
}
