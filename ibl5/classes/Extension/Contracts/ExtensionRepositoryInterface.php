<?php

declare(strict_types=1);

namespace Extension\Contracts;

/**
 * ExtensionRepositoryInterface - Contract for extension data access
 *
 * Defines the data access layer for contract extension transactions. Handles all
 * database operations related to updating player contracts, managing extension
 * usage flags, creating news stories, and reading team/player data for evaluation.
 *
 * @phpstan-type ExtensionOffer array{year1: int, year2: int, year3: int, year4: int, year5: int}
 * @phpstan-type TraditionData array{currentSeasonWins: int, currentSeasonLosses: int, tradition_wins: int, tradition_losses: int}
 *
 * @package Extension\Contracts
 */
interface ExtensionRepositoryInterface
{
    /**
     * Updates a player's contract with the new extension terms
     *
     * @param string $playerName Player name for lookup
     * @param ExtensionOffer $offer Offer array with yearly salary amounts in thousands
     * @param int $currentSalary Player's current year salary (becomes salary_yr1)
     * @return bool True if update succeeded, false on database error
     */
    public function updatePlayerContract(string $playerName, array $offer, int $currentSalary): bool;

    /**
     * Marks that a team has used their extension attempt for this sim
     *
     * @param string $teamName Team name for lookup
     * @return bool True if update succeeded, false on database error
     */
    public function markExtensionUsedThisSim(string $teamName): bool;

    /**
     * Marks that a team has used their extension for this season
     *
     * @param string $teamName Team name for lookup
     * @return bool True if update succeeded, false on database error
     */
    public function markExtensionUsedThisSeason(string $teamName): bool;

    /**
     * Creates a news story for an accepted extension
     *
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param float $offerInMillions Offer amount in millions
     * @param int $offerYears Number of years (3, 4, or 5)
     * @param string $offerDetails Year-by-year breakdown
     * @return bool True if story created, false on error
     */
    public function createAcceptedExtensionStory(string $playerName, string $teamName, float $offerInMillions, int $offerYears, string $offerDetails): bool;

    /**
     * Creates a news story for a rejected extension
     *
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param float $offerInMillions Offer amount in millions
     * @param int $offerYears Number of years (3, 4, or 5)
     * @return bool True if story created, false on error
     */
    public function createRejectedExtensionStory(string $playerName, string $teamName, float $offerInMillions, int $offerYears): bool;

    /**
     * Retrieves team tradition data for extension evaluation
     *
     * Returns current season wins/losses and franchise tradition averages.
     * Falls back to 41/41 defaults if data not found.
     *
     * @param string $teamName Team name for lookup
     * @return TraditionData Tradition data array
     */
    public function getTeamTraditionData(string $teamName): array;

    /**
     * Retrieves money committed at a player's position from cached column
     *
     * @param string $teamName Team name for lookup
     * @return int Money committed (0 if not found or no data)
     */
    public function getMoneyCommittedAtPosition(string $teamName): int;

    /**
     * Saves an accepted extension in a single transaction
     *
     * Wraps updatePlayerContract + markExtensionUsedThisSeason +
     * createAcceptedExtensionStory in a transaction.
     *
     * @param string $playerName Player name
     * @param string $teamName Team name
     * @param ExtensionOffer $offer Offer array
     * @param int $currentSalary Current salary
     * @param float $offerInMillions Offer in millions for news story
     * @param int $offerYears Number of years
     * @param string $offerDetails Year-by-year breakdown for news story
     */
    public function saveAcceptedExtension(
        string $playerName,
        string $teamName,
        array $offer,
        int $currentSalary,
        float $offerInMillions,
        int $offerYears,
        string $offerDetails
    ): void;
}
