<?php

namespace Draft;

/**
 * Orchestrates the draft selection workflow
 * 
 * Responsibilities:
 * - Coordinate validation, database updates, and notifications
 * - Handle draft selection submissions
 * - Manage Discord notifications
 */
class DraftSelectionHandler
{
    private $db;
    private $validator;
    private $repository;
    private $processor;
    private $view;
    private $sharedFunctions;
    private $season;

    public function __construct($db, $sharedFunctions, $season)
    {
        $this->db = $db;
        $this->sharedFunctions = $sharedFunctions;
        $this->season = $season;
        
        $this->validator = new DraftValidator();
        $this->repository = new DraftRepository($db);
        $this->processor = new DraftProcessor();
        $this->view = new DraftView();
    }

    /**
     * Handle a draft selection submission
     * 
     * @param string $teamName The name of the drafting team
     * @param string|null $playerName The name of the player to draft
     * @param int $draftRound The draft round
     * @param int $draftPick The pick number
     * @return string HTML response message
     */
    public function handleDraftSelection($teamName, $playerName, $draftRound, $draftPick)
    {
        // Get current draft selection
        $currentDraftSelection = $this->repository->getCurrentDraftSelection($draftRound, $draftPick);

        // Check if player is already drafted
        $isPlayerAlreadyDrafted = false;
        if ($playerName !== null && $playerName !== '') {
            $isPlayerAlreadyDrafted = $this->repository->isPlayerAlreadyDrafted($playerName);
        }

        // Validate the draft selection
        if (!$this->validator->validateDraftSelection($playerName, $currentDraftSelection, $isPlayerAlreadyDrafted)) {
            $errors = $this->validator->getErrors();
            return $this->view->renderValidationError($errors[0]);
        }

        // Process the draft selection
        return $this->processDraftSelection($teamName, $playerName, $draftRound, $draftPick);
    }

    /**
     * Process a validated draft selection
     * 
     * @param string $teamName The name of the drafting team
     * @param string $playerName The name of the player to draft
     * @param int $draftRound The draft round
     * @param int $draftPick The pick number
     * @return string HTML response message
     */
    private function processDraftSelection($teamName, $playerName, $draftRound, $draftPick)
    {
        $date = date('Y-m-d h:i:s');

        // Update all three database tables
        $draftTableUpdated = $this->repository->updateDraftTable($playerName, $date, $draftRound, $draftPick);
        $rookieTableUpdated = $this->repository->updateRookieTable($playerName, $teamName);
        $playerCreated = $this->repository->createPlayerFromDraftClass($playerName, $teamName);

        // Check if all updates succeeded
        if (!$draftTableUpdated || !$rookieTableUpdated || !$playerCreated) {
            return $this->processor->getDatabaseErrorMessage();
        }

        // Send notifications and return success message
        return $this->sendNotifications($teamName, $playerName, $draftRound, $draftPick);
    }

    /**
     * Send Discord notifications and return success message
     * 
     * @param string $teamName The name of the drafting team
     * @param string $playerName The name of the drafted player
     * @param int $draftRound The draft round
     * @param int $draftPick The pick number
     * @return string HTML response message
     */
    private function sendNotifications($teamName, $playerName, $draftRound, $draftPick)
    {
        // Create base announcement message
        $message = $this->processor->createDraftAnnouncement(
            $draftPick, 
            $draftRound, 
            $this->season->endingYear, 
            $teamName, 
            $playerName
        );

        // Get next team on the clock
        $nextTeamDraftPick = $this->repository->getNextTeamOnClock();
        $teamOnTheClock = null;
        $discordIDOfTeamOnTheClock = null;

        if ($nextTeamDraftPick !== null) {
            $teamOnTheClock = $this->sharedFunctions->getCurrentOwnerOfDraftPick(
                $this->season->endingYear, 
                $draftRound, 
                $nextTeamDraftPick
            );

            if ($teamOnTheClock !== null) {
                $discordIDOfTeamOnTheClock = $this->repository->getTeamDiscordID($teamOnTheClock);
            }
        }

        // Post to general chat
        \Discord::postToChannel('#general-chat', $message);

        // Create message with next team info
        $messageWithNextTeam = $this->processor->createNextTeamMessage(
            $message, 
            $discordIDOfTeamOnTheClock,
            $this->season->endingYear
        );

        // Post to draft-picks channel
        \Discord::postToChannel('#draft-picks', $messageWithNextTeam);

        // Return success message
        return $this->processor->getSuccessMessage($message);
    }
}
