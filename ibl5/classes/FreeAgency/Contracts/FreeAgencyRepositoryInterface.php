<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for FreeAgency repository database operations
 * 
 * Defines contracts for all database operations in the FreeAgency module.
 * All methods use prepared statements via BaseMysqliRepository.
 */
interface FreeAgencyRepositoryInterface
{
    /**
     * Get an existing offer for a player from a team
     * 
     * @param string $teamName Team making the offer
     * @param string $playerName Player receiving the offer
     * @return array|null Offer data with keys: offer1-6, or null if no offer exists
     */
    public function getExistingOffer(string $teamName, string $playerName): ?array;

    /**
     * Delete an offer from a team to a player
     * 
     * @param string $teamName Team making the offer
     * @param string $playerName Player receiving the offer
     * @return int Number of rows deleted (0 or 1)
     */
    public function deleteOffer(string $teamName, string $playerName): int;

    /**
     * Save a new offer to the database
     * 
     * Automatically deletes any existing offer from the same team to the same player
     * before inserting the new offer.
     * 
     * @param array $offerData Offer data with keys: teamName, playerName, offer1-6,
     *                         modifier, random, perceivedValue, mle, lle, offerType
     * @return bool True if saved successfully
     */
    public function saveOffer(array $offerData): bool;

    /**
     * Get all players excluding a specific team
     * 
     * Returns all non-retired players not on the specified team, ordered by ordinal.
     * Used for displaying "All Other Free Agents" in the free agency interface.
     * 
     * @param string $teamName Team to exclude
     * @return array Array of player rows
     */
    public function getAllPlayersExcludingTeam(string $teamName): array;
}
