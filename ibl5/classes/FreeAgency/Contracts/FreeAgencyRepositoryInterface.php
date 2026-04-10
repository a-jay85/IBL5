<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for FreeAgency repository database operations
 *
 * Defines contracts for all database operations in the FreeAgency module.
 * All methods use prepared statements via BaseMysqliRepository.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @phpstan-type OfferRow array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int}
 * @phpstan-type OfferData array{pid: int, tid: int, teamName: string, playerName: string, offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, modifier: float, random: int, perceivedValue: float, mle: int, lle: int, offerType: int}
 */
interface FreeAgencyRepositoryInterface
{
    /**
     * Get an existing offer for a player from a team
     *
     * @param int $tid Team ID making the offer
     * @param int $pid Player ID receiving the offer
     * @return OfferRow|null Offer data with keys: offer1-6, or null if no offer exists
     */
    public function getExistingOffer(int $tid, int $pid): ?array;

    /**
     * Delete an offer from a team to a player
     *
     * @param int $tid Team ID making the offer
     * @param int $pid Player ID receiving the offer
     * @return int Number of rows deleted (0 or 1)
     */
    public function deleteOffer(int $tid, int $pid): int;

    /**
     * Save a new offer to the database
     *
     * Automatically deletes any existing offer from the same team to the same player
     * before inserting the new offer.
     *
     * @param OfferData $offerData Offer data with keys: teamName, playerName, offer1-6,
     *                             modifier, random, perceivedValue, mle, lle, offerType
     * @return bool True if saved successfully
     */
    public function saveOffer(array $offerData): bool;

    /**
     * Get all players excluding a specific team
     *
     * Returns all non-retired players not on the specified team, ordered by ordinal.
     * Used for displaying "All Other Free Agents" in the free agency interface.
     *
     * @param int $teamId Team ID to exclude
     * @return list<PlayerRow> Array of player rows
     */
    public function getAllPlayersExcludingTeam(int $teamId): array;

    /**
     * Check if a player has already been signed during this free agency period
     *
     * Returns true if player has cy=0 and a non-zero cy1 contract in the database,
     * indicating they've been signed in the current free agency period.
     * Used to prevent duplicate signings of same player.
     *
     * @param int $playerId Player ID to check
     * @return bool True if player was already signed this free agency period
     */
    public function isPlayerAlreadySigned(int $playerId): bool;

    /**
     * Check whether a team already has a pending MLE offer to a different player
     *
     * Returns true if `ibl_fa_offers` contains any row for this team where
     * the MLE column is set and pid differs from `$excludePid`. Used to enforce
     * the rule that a GM may only have one pending Mid-Level Exception offer
     * outstanding at any time. `$excludePid` lets a team overwrite their own
     * existing MLE offer to the same player without tripping the check.
     *
     * @param int $tid Offering team's ID
     * @param int $excludePid Player ID to ignore (the player being offered to now)
     * @return bool True if a pending MLE offer already exists to another player
     */
    public function hasPendingMleOffer(int $tid, int $excludePid): bool;

    /**
     * Check whether a team already has a pending LLE offer to a different player
     *
     * @see self::hasPendingMleOffer() for semantics — same rule, LLE column.
     *
     * @param int $tid Offering team's ID
     * @param int $excludePid Player ID to ignore (the player being offered to now)
     * @return bool True if a pending LLE offer already exists to another player
     */
    public function hasPendingLleOffer(int $tid, int $excludePid): bool;
}
