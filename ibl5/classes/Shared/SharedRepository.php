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
 * - Draft pick ownership
 * - Contract extension management
 *
 */
class SharedRepository extends \BaseMysqliRepository implements SharedRepositoryInterface
{
    private string $teamInfoTable;

    public function __construct(\mysqli $db, ?\League\LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
    }

    /**
     * Gets the current owner of a specific draft pick
     *
     * Draft picks can be traded, so this method returns the current owner.
     * Returns null if the draft pick is not found.
     *
     * @param int $draftYear Draft year
     * @param int $draftRound Draft round number
     * @param int $teamIdOfDraftPickOrigin Team ID of the original team for the draft pick
     * @return string|null Team name of current draft pick owner, or null if not found
     */
    public function getCurrentOwnerOfDraftPick(int $draftYear, int $draftRound, int $teamIdOfDraftPickOrigin): ?string
    {
        /** @var array{ownerofpick: string}|null $result */
        $result = $this->fetchOne(
            "SELECT ownerofpick FROM ibl_draft_picks WHERE year = ? AND round = ? AND teampick_teamid = ? LIMIT 1",
            "iii",
            $draftYear,
            $draftRound,
            $teamIdOfDraftPickOrigin
        );

        return $result !== null ? $result['ownerofpick'] : null;
    }

    /**
     * Resets the contract extension counter for all teams
     *
     * Sets used_extension_this_chunk to 0 for all teams. This is typically called
     * at the start of a simulation chunk to reset extension attempt counters.
     *
     * @return void
     * @throws \RuntimeException If the database update fails
     */
    public function resetSimContractExtensionAttempts(): void
    {
        try {
            $this->execute(
                "UPDATE {$this->teamInfoTable} SET used_extension_this_chunk = 0",
                ""
            );
        } catch (\Exception $e) {
            $errorMessage = 'Failed to reset sim contract extension attempts: ' . $e->getMessage();
            \Logging\LoggerFactory::getChannel('db')->error('SharedRepository database error', ['error' => $errorMessage]);
            throw new \RuntimeException($errorMessage, 1002);
        }
    }
}
