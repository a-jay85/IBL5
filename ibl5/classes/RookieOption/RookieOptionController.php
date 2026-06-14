<?php

declare(strict_types=1);

namespace RookieOption;

use Player\Player;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use BasketballStats\SalaryConverter;
use RookieOption\Contracts\RookieOptionControllerInterface;
use Season\Season;
use Discord\Discord;

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
    private \Topics\News\NewsRepository $newsService;
    private TeamIdentityRepositoryInterface $commonRepository;
    /** Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('app'). */
    private \Psr\Log\LoggerInterface $appLogger;
    /** Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit'). */
    private \Psr\Log\LoggerInterface $auditLogger;
    /**
     * Optional injected Season. When null, methods fall back to new Season($db) (timing identical to today).
     */
    private ?Season $season = null;

    public function __construct(
        \mysqli $db,
        TeamIdentityRepositoryInterface $commonRepository,
        ?\Psr\Log\LoggerInterface $appLogger = null,
        ?\Psr\Log\LoggerInterface $auditLogger = null,
        ?Season $season = null
    ) {
        $this->db = $db;
        $this->season = $season;
        $this->repository = new RookieOptionRepository($db);
        $this->newsService = new \Topics\News\NewsRepository($db);
        $this->commonRepository = $commonRepository;
        $this->appLogger = $appLogger ?? \Logging\LoggerFactory::getChannel('app');
        $this->auditLogger = $auditLogger ?? \Logging\LoggerFactory::getChannel('audit');
    }

    /**
     * @see RookieOptionControllerInterface::processRookieOption()
     */
    public function processRookieOption(string $teamName, int $playerID, int $extensionAmount): array
    {
        $season = $this->season ?? new Season($this->db);
        $player = Player::withPlayerID($this->db, $playerID);

        // Validate player eligibility
        if (!$player->canRookieOption($season->phase)) {
            $errorMessage = "This player's experience doesn't match their rookie status; please let the commish know about this error.";
            $this->appLogger->warning('RookieOption validation error', ['player_id' => $playerID, 'error' => $errorMessage]);
            return ['success' => false, 'type' => 'validation_error', 'message' => $errorMessage, 'playerID' => $playerID];
        }

        // Determine which contract year to update based on draft round
        if ($player->getDraftRound() !== 1 && $player->getDraftRound() !== 2) {
            $errorMessage = "This player's experience doesn't match their rookie status; please let the commish know about this error.";
            $this->appLogger->warning('RookieOption draft round validation error', ['player_id' => $playerID, 'draft_round' => $player->getDraftRound()]);
            return ['success' => false, 'type' => 'validation_error', 'message' => $errorMessage, 'playerID' => $playerID];
        }

        // Update player's contract
        if (!$this->repository->updatePlayerRookieOption($playerID, $player->getDraftRound(), $extensionAmount)) {
            $errorMessage = "Failed to update player contract. Please contact the commissioner.";
            $this->appLogger->error('RookieOption database update failed', ['player_id' => $playerID, 'draft_round' => $player->getDraftRound(), 'extension_amount' => $extensionAmount]);
            return ['success' => false, 'type' => 'database_error', 'message' => $errorMessage, 'playerID' => $playerID];
        }

        // Get team ID for redirect link
        $teamid = $this->commonRepository->getTidFromTeamname($teamName) ?? 0;

        // Send Discord notification
        $playerName = $player->getName() ?? 'Unknown';
        $discordMessage = $teamName . " exercise the rookie extension option on " . $playerName . " in the amount of " . $extensionAmount . ".";
        try {
            Discord::postToChannel(self::DISCORD_CHANNEL, $discordMessage);
        } catch (\Exception $e) {
            $this->appLogger->warning('RookieOption Discord notification failed', ['error' => $e->getMessage()]);
        }

        // Send email notification
        $emailSubject = "Rookie Extension Option - " . $playerName;
        $emailBody = $discordMessage;
        $emailSuccess = \Mail\MailService::fromConfig()->send(self::NOTIFICATION_EMAIL_RECIPIENT, $emailSubject, $emailBody, self::NOTIFICATION_EMAIL_SENDER);

        // Create news story if email succeeded
        if ($emailSuccess) {
            $this->createRookieOptionNewsStory($teamName, $playerName, $extensionAmount);
        }

        $this->auditLogger->info('rookie_option_exercised', [
            'action' => 'rookie_option_exercised',
            'player_id' => $playerID,
            'player_name' => $playerName,
            'team_name' => $teamName,
            'extension_amount' => $extensionAmount,
            'draft_round' => $player->getDraftRound(),
        ]);

        return [
            'success' => true,
            'type' => 'rookie_option_success',
            'message' => 'Rookie option exercised successfully.',
            'playerID' => $playerID,
            'teamid' => $teamid,
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
