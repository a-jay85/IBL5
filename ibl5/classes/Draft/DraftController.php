<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftSelectionHandlerInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Discord\Discord;

/**
 * @see DraftSelectionHandlerInterface
 */
class DraftSelectionHandler implements DraftSelectionHandlerInterface
{
    private \mysqli $db;
    private DraftValidator $validator;
    private DraftRepository $repository;
    private TeamIdentityRepositoryInterface $commonRepository;
    private DraftProcessor $processor;
    private DraftView $view;
    private Season $season;
    /** Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit'). */
    private \Psr\Log\LoggerInterface $auditLogger;
    /** Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('draft'). */
    private \Psr\Log\LoggerInterface $draftLogger;

    public function __construct(
        \mysqli $db,
        TeamIdentityRepositoryInterface $commonRepository,
        Season $season,
        ?\Psr\Log\LoggerInterface $auditLogger = null,
        ?\Psr\Log\LoggerInterface $draftLogger = null
    ) {
        $this->db = $db;
        $this->commonRepository = $commonRepository;
        $this->season = $season;

        $this->validator = new DraftValidator();
        $this->repository = new DraftRepository($db, $commonRepository);
        $this->processor = new DraftProcessor();
        $this->view = new DraftView();
        $this->auditLogger = $auditLogger ?? \Logging\LoggerFactory::getChannel('audit');
        $this->draftLogger = $draftLogger ?? \Logging\LoggerFactory::getChannel('draft');
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
        $selectionValidation = $this->validator->validateDraftSelection($playerName, $currentDraftSelection, $isPlayerAlreadyDrafted);
        if (!$selectionValidation->isValid()) {
            $errors = $selectionValidation->getErrors();
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
            $this->draftLogger->error('Draft selection DB error', ['error' => $e->getMessage()]);
            return $this->processor->getDatabaseErrorMessage();
        }

        $this->auditLogger->info('player_drafted', [
            'action' => 'player_drafted',
            'player_name' => $playerName,
            'team_name' => $teamName,
            'round' => $draftRound,
            'pick' => $draftPick,
        ]);

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

        // Look up next team on the clock for the #draft-picks message.
        // Wrapped in try-catch so a lookup failure can't prevent webhooks from firing.
        $discordIdOfTeamOnTheClock = null;
        try {
            $nextPick = $this->repository->getCurrentDraftPick();
            if ($nextPick !== null) {
                $teamOnTheClock = $this->repository->getCurrentOwnerOfDraftPick(
                    $this->season->endingYear,
                    $nextPick['round'],
                    $nextPick['teamid']
                );
                if ($teamOnTheClock !== null) {
                    $discordIdOfTeamOnTheClock = $this->commonRepository->getTeamDiscordID($teamOnTheClock);
                }
            }
        } catch (\Throwable $e) {
            $this->draftLogger->warning('Draft next-pick lookup error', ['error' => $e->getMessage()]);
        }

        try {
            Discord::postToChannel('#general-chat', $message);
        } catch (\Throwable $e) {
            $this->draftLogger->error('Draft Discord #general-chat error', ['error' => $e->getMessage()]);
        }

        $messageWithNextTeam = $this->processor->createNextTeamMessage(
            $message,
            $discordIdOfTeamOnTheClock,
            $this->season->endingYear
        );

        try {
            Discord::postToChannel('#draft-picks', $messageWithNextTeam);
        } catch (\Throwable $e) {
            $this->draftLogger->error('Draft Discord #draft-picks error', ['error' => $e->getMessage()]);
        }

        return $this->processor->getSuccessMessage($message);
    }
}
