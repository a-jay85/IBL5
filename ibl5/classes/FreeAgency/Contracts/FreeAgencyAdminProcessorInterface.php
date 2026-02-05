<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for processing admin free agency operations
 *
 * Handles the administrative workflow for processing free agency day results:
 * - Determining winning offers based on perceived value vs demands
 * - Applying contract signings to player records
 * - Managing MLE/LLE usage
 * - Generating news stories
 * - Clearing offers table between days
 *
 * All database operations use prepared statements.
 */
interface FreeAgencyAdminProcessorInterface
{
    /**
     * Process all offers for a given free agency day
     *
     * Analyzes all pending offers, determines winning bids, and returns
     * structured data for display and confirmation before execution.
     *
     * @param int $day Free agency day number (1-10)
     * @return array{
     *     signings: list<array{
     *         playerName: string,
     *         playerId: int,
     *         teamName: string,
     *         teamId: int,
     *         offers: array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int},
     *         offerYears: int,
     *         offerTotal: float,
     *         usedMle: bool,
     *         usedLle: bool
     *     }>,
     *     rejections: list<array{
     *         playerName: string,
     *         reason: string
     *     }>,
     *     autoRejections: list<array{
     *         playerName: string,
     *         teamName: string,
     *         offers: array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int},
     *         reason: string
     *     }>,
     *     allOffers: list<array{
     *         playerName: string,
     *         teamName: string,
     *         offers: array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int},
     *         birdYears: int,
     *         mle: int,
     *         lle: int,
     *         random: int,
     *         perceivedValue: float
     *     }>,
     *     newsHomeText: string,
     *     newsBodyText: string,
     *     discordText: string
     * }
     */
    public function processDay(int $day): array;

    /**
     * Execute the signings for a processed day
     *
     * Applies all winning signings to the database:
     * - Updates player contracts (cy, cy1-cy6, teamname, tid, cyt)
     * - Marks MLE/LLE as used for teams
     * - Inserts news story
     *
     * @param int $day Free agency day number (1-10)
     * @param list<array{
     *     playerId: int,
     *     teamId: int,
     *     teamName: string,
     *     offers: array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int},
     *     offerYears: int,
     *     usedMle: bool,
     *     usedLle: bool
     * }> $signings Array of signing data to execute
     * @param string $newsTitle News article title
     * @param string $newsHomeText News article home text
     * @param string $newsBodyText News article body text
     * @return array{success: bool, successCount: int, errorCount: int, message: string}
     */
    public function executeSignings(
        int $day,
        array $signings,
        string $newsTitle,
        string $newsHomeText,
        string $newsBodyText
    ): array;

    /**
     * Clear all offers from the offers table
     *
     * Truncates ibl_fa_offers table after a day has been processed.
     *
     * @return array{success: bool, message: string}
     */
    public function clearOffers(): array;
}
