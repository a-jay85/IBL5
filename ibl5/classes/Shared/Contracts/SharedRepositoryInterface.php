<?php

declare(strict_types=1);

namespace Shared\Contracts;

/**
 * SharedRepositoryInterface - Data repository for common IBL operations
 *
 * Provides access to shared data operations used across multiple modules:
 * - Team awards and title counts
 * - Draft pick ownership tracking
 * - Contract extension management
 *
 * @see \Shared\SharedRepository
 */
interface SharedRepositoryInterface
{
    /**
     * Gets the number of a specific award won by a team
     *
     * @param string $teamName Team name to look up
     * @param string $titleName Award name to search for
     * @return int Number of awards matching the criteria
     */
    public function getNumberOfTitles(string $teamName, string $titleName): int;

    /**
     * Gets the current owner of a specific draft pick
     *
     * @param int $draftYear Draft year
     * @param int $draftRound Draft round number
     * @param int $teamIdOfDraftPickOrigin Team ID of the original team for the draft pick
     * @return string|null Team name of current draft pick owner, or null if not found
     */
    public function getCurrentOwnerOfDraftPick(int $draftYear, int $draftRound, int $teamIdOfDraftPickOrigin): ?string;

    /**
     * Resets the contract extension counter for all teams
     *
     * Used during simulation chunks to reset the number of extension attempts.
     * This is a mutating operation with side effects.
     *
     * @return void
     * @throws \RuntimeException If database update fails
     */
    public function resetSimContractExtensionAttempts(): void;
}
