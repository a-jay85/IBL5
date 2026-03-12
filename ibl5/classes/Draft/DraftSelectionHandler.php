<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftSelectionHandlerInterface;
use Shared\Contracts\SharedRepositoryInterface;

/**
 * @see DraftSelectionHandlerInterface
 */
class DraftSelectionHandler implements DraftSelectionHandlerInterface
{
    private \mysqli $db;
    private DraftValidator $validator;
    private DraftRepository $repository;
    private \Services\CommonMysqliRepository $commonRepository;
    private DraftProcessor $processor;
    private DraftView $view;
    private SharedRepositoryInterface $sharedRepository;
    private \Season $season;

    public function __construct(\mysqli $db, SharedRepositoryInterface $sharedRepository, \Season $season)
    {
        $this->db = $db;
        $this->sharedRepository = $sharedRepository;
        $this->season = $season;

        $this->validator = new DraftValidator();
        $this->repository = new DraftRepository($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
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

        $this->db->begin_transaction();
        try {
            $draftTableUpdated = $this->repository->updateDraftTable($playerName, $date, $draftRound, $draftPick);
            $rookieTableUpdated = $this->repository->updateRookieTable($playerName, $teamName);
            $playerCreated = $this->repository->createPlayerFromDraftClass($playerName, $teamName);

            if (!$draftTableUpdated || !$rookieTableUpdated || !$playerCreated) {
                $this->db->rollback();
                return $this->processor->getDatabaseErrorMessage();
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('Draft selection DB error: ' . $e->getMessage());
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

        $nextPick = $this->repository->getCurrentDraftPick();
        $discordIDOfTeamOnTheClock = null;
        if ($nextPick !== null) {
            $teamOnTheClock = $this->sharedRepository->getCurrentOwnerOfDraftPick(
                $this->season->endingYear,
                $nextPick['round'],
                $nextPick['tid']
            );
            if ($teamOnTheClock !== null) {
                $discordIDOfTeamOnTheClock = $this->commonRepository->getTeamDiscordID($teamOnTheClock);
            }
        }

        try {
            \Discord::postToChannel('#general-chat', $message);
        } catch (\Throwable $e) {
            error_log('Draft Discord #general-chat error: ' . $e->getMessage());
        }

        $messageWithNextTeam = $this->processor->createNextTeamMessage(
            $message,
            $discordIDOfTeamOnTheClock,
            $this->season->endingYear
        );

        try {
            \Discord::postToChannel('#draft-picks', $messageWithNextTeam);
        } catch (\Throwable $e) {
            error_log('Draft Discord #draft-picks error: ' . $e->getMessage());
        }

        return $this->processor->getSuccessMessage($message);
    }
}
