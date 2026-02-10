<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftSelectionHandlerInterface;

/**
 * @see DraftSelectionHandlerInterface
 */
class DraftSelectionHandler implements DraftSelectionHandlerInterface
{
    private DraftValidator $validator;
    private DraftRepository $repository;
    private \Services\CommonMysqliRepository $commonRepository;
    private DraftProcessor $processor;
    private DraftView $view;
    private object $sharedFunctions;
    /** @var \Season */
    private object $season;

    /** @param \Season $season */
    public function __construct(object $db, object $sharedFunctions, object $season)
    {
        global $mysqli_db;
        $this->sharedFunctions = $sharedFunctions;
        $this->season = $season;

        $this->validator = new DraftValidator();
        $this->repository = new DraftRepository($db);
        /** @var object $mysqliDb */
        $mysqliDb = $mysqli_db;
        $this->commonRepository = new \Services\CommonMysqliRepository($mysqliDb);
        $this->processor = new DraftProcessor();
        $this->view = new DraftView();
    }

    /**
     * @see DraftSelectionHandlerInterface::handleDraftSelection()
     */
    public function handleDraftSelection(string $teamName, ?string $playerName, int $draftRound, int $draftPick): string
    {
        $currentDraftSelection = $this->repository->getCurrentDraftSelection($draftRound, $draftPick);
        $isPlayerAlreadyDrafted = false;
        if ($playerName !== null && $playerName !== '') {
            $isPlayerAlreadyDrafted = $this->repository->isPlayerAlreadyDrafted($playerName);
        }
        if (!$this->validator->validateDraftSelection($playerName, $currentDraftSelection, $isPlayerAlreadyDrafted)) {
            $errors = $this->validator->getErrors();
            return $this->view->renderValidationError($errors[0]);
        }

        // Process the draft selection
        return $this->processDraftSelection($teamName, $playerName ?? '', $draftRound, $draftPick);
    }

    private function processDraftSelection(string $teamName, string $playerName, int $draftRound, int $draftPick): string
    {
        $date = date('Y-m-d h:i:s');
        $draftTableUpdated = $this->repository->updateDraftTable($playerName, $date, $draftRound, $draftPick);
        $rookieTableUpdated = $this->repository->updateRookieTable($playerName, $teamName);
        $playerCreated = $this->repository->createPlayerFromDraftClass($playerName, $teamName);
        if (!$draftTableUpdated || !$rookieTableUpdated || !$playerCreated) {
            return $this->processor->getDatabaseErrorMessage();
        }
        return $this->sendNotifications($teamName, $playerName, $draftRound, $draftPick);
    }

    private function sendNotifications(string $teamName, string $playerName, int $draftRound, int $draftPick): string
    {
        $message = $this->processor->createDraftAnnouncement(
            $draftPick,
            $draftRound,
            $this->season->endingYear,
            $teamName,
            $playerName
        );
        $nextTeamDraftPick = $this->repository->getNextTeamOnClock();
        $teamOnTheClock = null;
        $discordIDOfTeamOnTheClock = null;
        if ($nextTeamDraftPick !== null) {
            /** @var \Shared $sharedFuncs */
            $sharedFuncs = $this->sharedFunctions;
            $teamOnTheClock = $sharedFuncs->getCurrentOwnerOfDraftPick(
                $this->season->endingYear,
                $draftRound,
                $nextTeamDraftPick
            );
            if ($teamOnTheClock !== null) {
                $discordIDOfTeamOnTheClock = $this->commonRepository->getTeamDiscordID((string) $teamOnTheClock);
            }
        }
        \Discord::postToChannel('#general-chat', $message);
        $messageWithNextTeam = $this->processor->createNextTeamMessage(
            $message,
            $discordIDOfTeamOnTheClock,
            $this->season->endingYear
        );
        \Discord::postToChannel('#draft-picks', $messageWithNextTeam);
        return $this->processor->getSuccessMessage($message);
    }
}
