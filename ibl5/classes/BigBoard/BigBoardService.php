<?php

declare(strict_types=1);

namespace BigBoard;

use BigBoard\Contracts\BigBoardRepositoryInterface;
use BigBoard\Contracts\BigBoardServiceInterface;
use BigBoard\Contracts\MockDraftServiceInterface;
use League\League;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * @see BigBoardServiceInterface
 *
 * @phpstan-import-type BigBoardRow from \BigBoard\Contracts\BigBoardRepositoryInterface
 * @phpstan-import-type AddableProspect from \BigBoard\Contracts\BigBoardRepositoryInterface
 * @phpstan-import-type MockResultRow from \BigBoard\Contracts\MockDraftServiceInterface
 */
class BigBoardService implements BigBoardServiceInterface
{
    /**
     * Defensive cap on stored note length (matches the note varchar(255) column).
     */
    private const MAX_NOTE_LENGTH = 255;

    private TeamIdentityRepositoryInterface $teamIdentityRepo;
    private BigBoardRepositoryInterface $repo;
    private MockDraftServiceInterface $mockDraftService;

    public function __construct(
        TeamIdentityRepositoryInterface $teamIdentityRepo,
        BigBoardRepositoryInterface $repo,
        MockDraftServiceInterface $mockDraftService
    ) {
        $this->teamIdentityRepo = $teamIdentityRepo;
        $this->repo = $repo;
        $this->mockDraftService = $mockDraftService;
    }

    /**
     * @see BigBoardServiceInterface::resolveOwnerTeamid()
     */
    public function resolveOwnerTeamid(string $username): ?int
    {
        $teamName = $this->teamIdentityRepo->getTeamnameFromUsername($username);

        // getTeamnameFromUsername returns FREE_AGENTS_TEAM_NAME for an empty/unknown
        // username, and null when the lookup finds no team. Either way the account
        // owns no franchise — guard on the NAME (not the resulting tid=0).
        if ($teamName === null || $teamName === League::FREE_AGENTS_TEAM_NAME) {
            return null;
        }

        return $this->teamIdentityRepo->getTidFromTeamname($teamName);
    }

    /**
     * @see BigBoardServiceInterface::getBoardView()
     *
     * @return list<BigBoardRow>
     */
    public function getBoardView(string $username): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return [];
        }

        return $this->repo->getBoardForTeam($teamid);
    }

    /**
     * @see BigBoardServiceInterface::getAddableProspects()
     *
     * @return list<AddableProspect>
     */
    public function getAddableProspects(string $username): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return [];
        }

        return $this->repo->getAddableProspects($teamid);
    }

    /**
     * @see BigBoardServiceInterface::getMockDraft()
     *
     * @return list<MockResultRow>
     */
    public function getMockDraft(string $username, int $seasonYear): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return [];
        }

        return $this->mockDraftService->getMockDraftForTeam($teamid, $seasonYear);
    }

    /**
     * @see BigBoardServiceInterface::addEntry()
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function addEntry(string $username, int $prospectId, int $rank, string $note): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return ['success' => false, 'error' => 'no_team'];
        }

        $note = mb_substr($note, 0, self::MAX_NOTE_LENGTH);

        // Plain INSERT in the repo: a duplicate (teamid, prospect_id) returns false,
        // which we surface as a user error rather than a silent no-op.
        if (!$this->repo->addEntry($teamid, $prospectId, $rank, $note)) {
            return ['success' => false, 'error' => 'duplicate'];
        }

        return ['success' => true, 'result' => 'added'];
    }

    /**
     * @see BigBoardServiceInterface::setRank()
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function setRank(string $username, int $entryId, int $rank): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return ['success' => false, 'error' => 'no_team'];
        }

        // The UPDATE's WHERE ... AND teamid = ? scoping means a write against a row
        // this team does not own affects 0 rows — no foreign-row mutation possible.
        $this->repo->setRank($teamid, $entryId, $rank);

        return ['success' => true, 'result' => 'rank_saved'];
    }

    /**
     * @see BigBoardServiceInterface::setNote()
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function setNote(string $username, int $entryId, string $note): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return ['success' => false, 'error' => 'no_team'];
        }

        $note = mb_substr($note, 0, self::MAX_NOTE_LENGTH);
        $this->repo->setNote($teamid, $entryId, $note);

        return ['success' => true, 'result' => 'note_saved'];
    }

    /**
     * @see BigBoardServiceInterface::removeEntry()
     *
     * @return array{success: bool, result?: string, error?: string}
     */
    public function removeEntry(string $username, int $entryId): array
    {
        $teamid = $this->resolveOwnerTeamid($username);

        if ($teamid === null) {
            return ['success' => false, 'error' => 'no_team'];
        }

        $this->repo->removeEntry($teamid, $entryId);

        return ['success' => true, 'result' => 'removed'];
    }
}
