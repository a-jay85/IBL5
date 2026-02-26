<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for FreeAgency admin repository database operations
 *
 * Defines contracts for all database operations used by the admin
 * free agency processor. All methods use prepared statements via BaseMysqliRepository.
 *
 * @phpstan-type OfferRow array{
 *     name: string,
 *     team: string,
 *     pid: int,
 *     offer1: int,
 *     offer2: int,
 *     offer3: int,
 *     offer4: int,
 *     offer5: int,
 *     offer6: int,
 *     bird: int,
 *     MLE: int,
 *     LLE: int,
 *     random: int,
 *     perceivedvalue: float
 * }
 *
 * @phpstan-type DemandRow array{
 *     dem1: int,
 *     dem2: int,
 *     dem3: int,
 *     dem4: int,
 *     dem5: int,
 *     dem6: int
 * }
 */
interface FreeAgencyAdminRepositoryInterface
{
    /**
     * Get all offers joined with player bird years, ordered by player then perceived value
     *
     * @return list<OfferRow>
     */
    public function getAllOffersWithBirdYears(): array;

    /**
     * Get player demand values from the demands table
     *
     * @param int $playerID Player ID to look up
     * @return DemandRow|null Demand row with dem1-dem6 fields, or null if not found
     */
    public function getPlayerDemands(int $playerID): ?array;

    /**
     * Update a player's contract after signing
     *
     * Sets cy=0, assigns contract year salaries, team name, contract years total, and team ID.
     *
     * @param int $pid Player ID
     * @param string $teamName Team name to assign
     * @param int $tid Team ID to assign
     * @param int $offerYears Total contract years
     * @param int $offer1 Year 1 salary
     * @param int $offer2 Year 2 salary
     * @param int $offer3 Year 3 salary
     * @param int $offer4 Year 4 salary
     * @param int $offer5 Year 5 salary
     * @param int $offer6 Year 6 salary
     * @return int Number of affected rows
     */
    public function updatePlayerContract(
        int $pid,
        string $teamName,
        int $tid,
        int $offerYears,
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6
    ): int;

    /**
     * Mark a team's Mid-Level Exception as used
     *
     * @param string $teamName Team name
     */
    public function markMleUsed(string $teamName): void;

    /**
     * Mark a team's Low-Level Exception as used
     *
     * @param string $teamName Team name
     */
    public function markLleUsed(string $teamName): void;

    /**
     * Insert a news story for free agency signings
     *
     * @param string $title News article title
     * @param string $homeText News article home/summary text
     * @param string $bodyText News article full body text
     * @return int Number of affected rows
     */
    public function insertNewsStory(string $title, string $homeText, string $bodyText): int;

    /**
     * Clear all offers from the free agency offers table
     */
    public function clearAllOffers(): void;
}
