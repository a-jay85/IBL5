<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

/**
 * Repository interface for PLR file database operations.
 */
interface PlrParserRepositoryInterface
{
    /**
     * Upsert a player record into ibl_plr.
     *
     * @param array<string, int|string|float> $data Column-value pairs for the player
     * @return int Number of affected rows
     */
    public function upsertPlayer(array $data): int;

    /**
     * Upsert a historical stats record into ibl_hist.
     *
     * @param array<string, int|string|float> $data Column-value pairs for historical stats
     * @return int Number of affected rows
     */
    public function upsertHistoricalStats(array $data): int;

    /**
     * Assign team names to players based on team ID mappings.
     *
     * @param list<array{teamid: int, team_name: string}> $teamData Team ID to name mappings
     * @return int Number of teams assigned
     */
    public function assignTeamNames(array $teamData): int;

    /**
     * Get all real team data (ID and name).
     *
     * @return list<array{teamid: int, team_name: string}>
     */
    public function getAllTeamData(): array;
}
