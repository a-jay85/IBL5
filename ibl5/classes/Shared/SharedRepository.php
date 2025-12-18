<?php

declare(strict_types=1);

namespace Shared;

use Shared\Contracts\SharedRepositoryInterface;

/**
 * SharedRepository - Data repository for common IBL operations
 *
 * Provides access to shared data operations used across multiple modules using mysqli
 * prepared statements for security and reliability.
 *
 * Responsibilities:
 * - Team awards and title tracking
 * - Draft pick ownership
 * - Module status queries
 * - Contract extension management
 *
 * @implements SharedRepositoryInterface
 */
class SharedRepository extends \BaseMysqliRepository implements SharedRepositoryInterface
{
    /**
     * Gets the number of a specific award won by a team
     *
     * Uses COUNT aggregation to efficiently get the number of matching awards.
     *
     * @param string $teamName Team name to look up
     * @param string $titleName Award name to search for (uses LIKE pattern)
     * @return int Number of awards matching the criteria
     */
    public function getNumberOfTitles(string $teamName, string $titleName): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(name) as count FROM ibl_team_awards WHERE name = ? AND Award LIKE ?",
            "ss",
            $teamName,
            "%{$titleName}%"
        );

        return $result ? (int) ($result['count'] ?? 0) : 0;
    }

    /**
     * Gets the current owner of a specific draft pick
     *
     * Draft picks can be traded, so this method returns the current owner.
     * Returns null if the draft pick is not found.
     *
     * @param int $draftYear Draft year
     * @param int $draftRound Draft round number
     * @param string $teamNameOfDraftPickOrigin Original team name for the draft pick
     * @return string|null Team name of current draft pick owner, or null if not found
     */
    public function getCurrentOwnerOfDraftPick(int $draftYear, int $draftRound, string $teamNameOfDraftPickOrigin): ?string
    {
        $result = $this->fetchOne(
            "SELECT ownerofpick FROM ibl_draft_picks WHERE year = ? AND round = ? AND teampick = ? LIMIT 1",
            "iis",
            $draftYear,
            $draftRound,
            $teamNameOfDraftPickOrigin
        );

        return $result ? ($result['ownerofpick'] ?? null) : null;
    }

    /**
     * Checks if the Free Agency module is active in the system
     *
     * Queries the nuke_modules table to check if the Free_Agency module is enabled.
     * Returns null if the module is not found.
     *
     * @return int|null Active status (typically 1 for active, 0 for inactive), or null if module not found
     */
    public function isFreeAgencyModuleActive(): ?int
    {
        $result = $this->fetchOne(
            "SELECT active FROM nuke_modules WHERE title = 'Free_Agency' LIMIT 1",
            "",
        );

        return $result ? (int) ($result['active'] ?? null) : null;
    }

    /**
     * Resets the contract extension counter for all teams
     *
     * Sets Used_Extension_This_Chunk to 0 for all teams. This is typically called
     * at the start of a simulation chunk to reset extension attempt counters.
     *
     * @return void
     * @throws \RuntimeException If the database update fails
     */
    public function resetSimContractExtensionAttempts(): void
    {
        try {
            $this->execute(
                "UPDATE ibl_team_info SET Used_Extension_This_Chunk = 0",
                ""
            );
        } catch (\Exception $e) {
            $errorMessage = 'Failed to reset sim contract extension attempts: ' . $e->getMessage();
            error_log("[Shared] Database error: {$errorMessage}");
            throw new \RuntimeException($errorMessage, 1002);
        }
    }
}
