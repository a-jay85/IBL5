<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftServiceInterface;
use Draft\Dto\DraftBoardData;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;

class DraftService implements DraftServiceInterface
{
    private DraftRepository $repository;
    private TeamIdentityRepositoryInterface $commonRepository;
    private Season $season;

    public function __construct(\mysqli $db, TeamIdentityRepositoryInterface $commonRepository, Season $season)
    {
        $this->repository = new DraftRepository($db, $commonRepository);
        $this->commonRepository = $commonRepository;
        $this->season = $season;
    }

    public function getDraftBoardData(string $username): DraftBoardData
    {
        $teamLogo = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $teamId = $this->commonRepository->getTidFromTeamname($teamLogo) ?? 0;

        $draftRound = null;
        $draftPick = null;
        $draftTid = 0;
        $currentPick = $this->repository->getCurrentDraftPick();
        if ($currentPick !== null) {
            $draftRound = $currentPick['round'];
            $draftPick = $currentPick['pick'];
            $draftTid = $currentPick['teamid'];
        }

        $pickOwner = null;
        if ($draftRound !== null && $draftTid !== 0) {
            $pickOwner = $this->repository->getCurrentOwnerOfDraftPick(
                $this->season->endingYear, $draftRound, $draftTid);
        }

        $players = $this->repository->getAllDraftClassPlayers();

        return new DraftBoardData(
            $players, $teamLogo, $pickOwner, $draftRound, $draftPick,
            $this->season->endingYear, $teamId);
    }
}
