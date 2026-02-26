<?php

declare(strict_types=1);

namespace Team\Contracts;

use Player\Player;

/**
 * TeamQueryRepositoryInterface - Query methods for team-related data
 *
 * Provides standardized access to team roster, salary, draft, and free agency data.
 * Extracted from the Team entity class to separate query concerns from entity state.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-type DraftPickRow array{pickid: int, ownerofpick: string, teampick: string, year: string, round: string, notes: ?string, created_at: string, updated_at: string}
 * @phpstan-type FreeAgencyOfferRow array{pid: int, tid: int, team: string, name: string, offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, ...}
 */
interface TeamQueryRepositoryInterface
{
    /**
     * Get buyout players for a team
     *
     * @return list<PlayerRow> Array of buyout player rows
     */
    public function getBuyouts(int $teamId): array;

    /**
     * Get draft history for a team
     *
     * @return list<PlayerRow> Array of drafted player rows
     */
    public function getDraftHistory(string $teamName): array;

    /**
     * Get draft picks owned by a team
     *
     * @return list<DraftPickRow> Array of draft pick rows
     */
    public function getDraftPicks(string $teamName): array;

    /**
     * Get free agency offers made by a team
     *
     * @param int $teamId Team ID
     * @return list<FreeAgencyOfferRow> Array of offer rows
     */
    public function getFreeAgencyOffers(int $teamId): array;

    /**
     * Get free agency roster ordered by name
     *
     * @return list<PlayerRow> Array of player rows
     */
    public function getFreeAgencyRosterOrderedByName(int $teamId): array;

    /**
     * Get healthy and injured players ordered by name
     *
     * @param \Season|null $season Season object for free agency filtering
     * @return list<PlayerRow> Array of player rows
     */
    public function getHealthyAndInjuredPlayersOrderedByName(string $teamName, int $teamId, ?\Season $season = null): array;

    /**
     * Get healthy players ordered by name
     *
     * @param \Season|null $season Season object for free agency filtering
     * @return list<PlayerRow> Array of player rows
     */
    public function getHealthyPlayersOrderedByName(string $teamName, int $teamId, ?\Season $season = null): array;

    /**
     * Get player ID of last sim starter for a position
     *
     * @param string $position Position code (e.g., 'PG', 'SG', 'SF', 'PF', 'C')
     * @return int Player ID (0 if not found)
     */
    public function getLastSimStarterPlayerIDForPosition(int $teamId, string $position): int;

    /**
     * Get player ID of currently set starter for a position
     *
     * @param string $position Position code (e.g., 'PG', 'SG', 'SF', 'PF', 'C')
     * @return int Player ID (0 if not found)
     */
    public function getCurrentlySetStarterPlayerIDForPosition(int $teamId, string $position): int;

    /**
     * Get all players under contract (all positions)
     *
     * @return list<PlayerRow> Array of player rows
     */
    public function getAllPlayersUnderContract(string $teamName): array;

    /**
     * Get players under contract by position
     *
     * @param string $position Position code (e.g., 'PG', 'SG', 'SF', 'PF', 'C')
     * @return list<PlayerRow> Array of player rows
     */
    public function getPlayersUnderContractByPosition(string $teamName, string $position): array;

    /**
     * Get roster under contract ordered by name
     *
     * @return list<PlayerRow> Array of player rows
     */
    public function getRosterUnderContractOrderedByName(int $teamId): array;

    /**
     * Get roster under contract ordered by ordinal
     *
     * @return list<PlayerRow> Array of player rows
     */
    public function getRosterUnderContractOrderedByOrdinal(int $teamId): array;

    /**
     * Get salary cap array for all contract years
     *
     * @return array<string, int> Array of salary cap spent by year
     */
    public function getSalaryCapArray(string $teamName, int $teamId, \Season $season): array;

    /**
     * Get total current season salaries from player result array
     *
     * @param list<PlayerRow> $result Array of player rows
     * @return int Total current season salaries
     */
    public function getTotalCurrentSeasonSalaries(array $result): int;

    /**
     * Get total next season salaries from player result array
     *
     * @param list<PlayerRow> $result Array of player rows
     * @return int Total next season salaries
     */
    public function getTotalNextSeasonSalaries(array $result): int;

    /**
     * Check if team can add contract without going over hard cap
     *
     * @param int $contractValue Contract value to add
     * @return bool True if under hard cap, false otherwise
     */
    public function canAddContractWithoutGoingOverHardCap(int $teamId, int $contractValue): bool;

    /**
     * Check if team can add buyout without exceeding buyout limit
     *
     * @param int $buyoutValue Buyout value to add
     * @return bool True if under buyout limit, false otherwise
     */
    public function canAddBuyoutWithoutExceedingBuyoutLimit(int $teamId, int $buyoutValue): bool;

    /**
     * Convert player result array into Player objects
     *
     * @param list<PlayerRow> $result Array of player rows
     * @return array<int, Player> Array of Player objects indexed by player ID
     */
    public function convertPlrResultIntoPlayerArray(array $result): array;
}
