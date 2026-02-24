<?php

declare(strict_types=1);

namespace CapSpace\Contracts;

/**
 * CapSpaceRepositoryInterface - Contract for salary cap data access
 *
 * Defines methods for retrieving team salary cap information from the database.
 *
 * @phpstan-type TeamInfoRow array{teamid: int, team_name: string, team_city: string, color1: string, color2: string, conference: string, division: string}
 * @phpstan-type ContractRow array{cy: int, cyt: int}
 *
 * @see \CapSpace\CapSpaceRepository For the concrete implementation
 */
interface CapSpaceRepositoryInterface
{
    /**
     * Get all teams for salary cap display
     *
     * @return list<TeamInfoRow> Team data ordered by teamid
     */
    public function getAllTeams(): array;

    /**
     * Get players under contract for a team after current season
     *
     * @param int $teamId Team ID
     * @return list<ContractRow> Contract year data for players with remaining years
     */
    public function getPlayersUnderContractAfterSeason(int $teamId): array;
}
