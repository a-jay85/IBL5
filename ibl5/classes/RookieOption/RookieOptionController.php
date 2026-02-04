<?php

declare(strict_types=1);

namespace RookieOption;

use Player\Player;
use Shared\SalaryConverter;
use RookieOption\Contracts\RookieOptionControllerInterface;

/**
 * @see RookieOptionControllerInterface
 */
class RookieOptionController implements RookieOptionControllerInterface
{
    // Configuration constants
    private const NOTIFICATION_EMAIL_RECIPIENT = 'ibldepthcharts@gmail.com';
    private const NOTIFICATION_EMAIL_SENDER = 'rookieoption@iblhoops.net';
    private const DISCORD_CHANNEL = '#rookie-options';
    private const ROOKIE_EXTENSION_CATEGORY = 'Rookie Extension';

    private \mysqli $db;
    private RookieOptionRepository $repository;
    private \Services\NewsService $newsService;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new RookieOptionRepository($db);
        $this->newsService = new \Services\NewsService($db);
    }

    /**
     * @see RookieOptionControllerInterface::processRookieOption()
     */
    public function processRookieOption(string $teamName, int $playerID, int $extensionAmount): array
    {
        $commonRepository = new \Services\CommonMysqliRepository($this->db);
        $season = new \Season($this->db);
        $player = Player::withPlayerID($this->db, $playerID);

        // Validate player eligibility
        if (!$player->canRookieOption($season->phase)) {
            $errorMessage = "This player's experience doesn't match their rookie status; please let the commish know about this error.";
            error_log("[RookieOption] Validation error for player ID {$playerID}: {$errorMessage}");
            return ['success' => false, 'type' => 'validation_error', 'message' => $errorMessage, 'playerID' => $playerID];
        }

        // Determine which contract year to update based on draft round
        if ($player->draftRound !== 1 && $player->draftRound !== 2) {
            $errorMessage = "This player's experience doesn't match their rookie status; please let the commish know about this error.";
            error_log("[RookieOption] Draft round validation error for player ID {$playerID}: Draft round {$player->draftRound} is invalid");
            return ['success' => false, 'type' => 'validation_error', 'message' => $errorMessage, 'playerID' => $playerID];
        }

        // Update player's contract
        if (!$this->repository->updatePlayerRookieOption($playerID, $player->draftRound, $extensionAmount)) {
            $errorMessage = "Failed to update player contract. Please contact the commissioner.";
            error_log("[RookieOption] Database update failed for player ID {$playerID}, draft round {$player->draftRound}, extension amount {$extensionAmount}");
            return ['success' => false, 'type' => 'database_error', 'message' => $errorMessage, 'playerID' => $playerID];
        }

        // Get team ID for redirect link
        $teamID = $commonRepository->getTidFromTeamname($teamName) ?? 0;

        // Send Discord notification
        $playerName = $player->name ?? 'Unknown';
        $discordMessage = $teamName . " exercise the rookie extension option on " . $playerName . " in the amount of " . $extensionAmount . ".";
        \Discord::postToChannel(self::DISCORD_CHANNEL, $discordMessage);

        // Send email notification
        $emailSubject = "Rookie Extension Option - " . $playerName;
        $emailBody = $discordMessage;
        $emailSuccess = mail(self::NOTIFICATION_EMAIL_RECIPIENT, $emailSubject, $emailBody, "From: " . self::NOTIFICATION_EMAIL_SENDER);

        // Create news story if email succeeded
        if ($emailSuccess) {
            $this->createRookieOptionNewsStory($teamName, $playerName, $extensionAmount);
        }

        return [
            'success' => true,
            'type' => 'rookie_option_success',
            'message' => 'Rookie option exercised successfully.',
            'playerID' => $playerID,
            'teamID' => $teamID,
            'emailSuccess' => $emailSuccess,
        ];
    }

    private function createRookieOptionNewsStory(string $teamName, string $playerName, int $extensionAmount): void
    {
        $rookieOptionInMillions = SalaryConverter::convertToMillions($extensionAmount);

        $storytitle = $playerName . " extends their contract with the " . $teamName;
        $hometext = $teamName . " exercise the rookie extension option on " . $playerName . " in the amount of " . $rookieOptionInMillions . " million dollars.";

        // Get topic ID for the team
        $topicID = $this->newsService->getTopicIDByTeamName($teamName);
        if ($topicID === null) {
            // If no topic found, skip news story creation
            return;
        }

        // Get category ID for rookie extensions
        $categoryID = $this->newsService->getCategoryIDByTitle(self::ROOKIE_EXTENSION_CATEGORY);
        if ($categoryID === null) {
            // If no category found, skip news story creation
            return;
        }

        // Increment counter
        $this->newsService->incrementCategoryCounter(self::ROOKIE_EXTENSION_CATEGORY);

        // Create the news story
        $this->newsService->createNewsStory($categoryID, $topicID, $storytitle, $hometext);
    }
}
