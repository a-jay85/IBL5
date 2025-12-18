<?php

declare(strict_types=1);

use Shared\Contracts\SharedRepositoryInterface;
use Shared\SharedRepository;

/**
 * Shared - Service class for common IBL operations
 *
 * Provides utility methods for operations used across multiple modules.
 * Uses repository pattern with mysqli prepared statements for data access.
 *
 * DESIGN:
 * - Constructor injection of mysqli connection and repository
 * - Type hints for all public methods
 * - Delegates database operations to SharedRepository
 * - Maintains backward compatibility with existing consumers
 */
class Shared
{
    protected $db;
    protected SharedRepositoryInterface $sharedRepository;
    protected \Services\CommonMysqliRepository $commonRepository;

    /**
     * Creates a new Shared instance
     *
     * @param mixed $db Legacy database object (deprecated, kept for backward compatibility)
     * @param SharedRepositoryInterface|null $sharedRepository Optional repository injection (for testing)
     */
    public function __construct($db, ?SharedRepositoryInterface $sharedRepository = null)
    {
        global $mysqli_db;
        $this->db = $db;
        $this->sharedRepository = $sharedRepository ?? new SharedRepository($mysqli_db);
        $this->commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    }

    /**
     * Gets the number of a specific award won by a team
     *
     * @param string $teamname Team name to look up
     * @param string $titleName Award name to search for
     * @return int Number of awards matching the criteria
     */
    public function getNumberOfTitles(string $teamname, string $titleName): int
    {
        return $this->sharedRepository->getNumberOfTitles($teamname, $titleName);
    }

    /**
     * Gets the current owner of a specific draft pick
     *
     * @param int|string $draftYear Draft year (accepts string for backward compatibility)
     * @param int|string $draftRound Draft round number (accepts string for backward compatibility)
     * @param string $teamNameOfDraftPickOrigin Original team name for the draft pick
     * @return string|null Team name of current draft pick owner, or null if not found
     */
    public function getCurrentOwnerOfDraftPick($draftYear, $draftRound, string $teamNameOfDraftPickOrigin): ?string
    {
        // Cast to int with type checking - accepts both int and string parameters
        $year = is_string($draftYear) ? (int) $draftYear : $draftYear;
        $round = is_string($draftRound) ? (int) $draftRound : $draftRound;
        
        return $this->sharedRepository->getCurrentOwnerOfDraftPick(
            $year,
            $round,
            $teamNameOfDraftPickOrigin
        );
    }

    /**
     * Checks if the Free Agency module is active in the system
     *
     * @return int|null Active status (typically 1 for active, 0 for inactive), or null if module not found
     */
    public function isFreeAgencyModuleActive(): ?int
    {
        return $this->sharedRepository->isFreeAgencyModuleActive();
    }

    /**
     * Resets the contract extension counter for all teams
     *
     * Outputs debug information to the browser during execution.
     *
     * @return void
     * @throws \RuntimeException If the database update fails
     */
    public function resetSimContractExtensionAttempts(): void
    {
        echo '<p>Resetting sim contract extension attempts...<p>';

        try {
            $this->sharedRepository->resetSimContractExtensionAttempts();
            \UI::displayDebugOutput(
                "UPDATE ibl_team_info SET Used_Extension_This_Chunk = 0",
                'Reset Sim Contract Extension Attempts SQL Query'
            );
            echo '<p>Sim contract extension attempts have been reset.<p>';
        } catch (\RuntimeException $e) {
            error_log("[Shared] " . $e->getMessage());
            throw $e;
        }
    }
}
