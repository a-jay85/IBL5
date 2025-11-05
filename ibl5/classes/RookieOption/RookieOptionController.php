<?php

namespace RookieOption;

use Player\Player;

/**
 * Main controller for rookie option operations
 */
class RookieOptionController
{
    // Configuration constants
    private const NOTIFICATION_EMAIL_RECIPIENT = 'ibldepthcharts@gmail.com';
    private const NOTIFICATION_EMAIL_SENDER = 'rookieoption@iblhoops.net';
    private const DISCORD_CHANNEL = '#rookie-options';
    private const ROOKIE_EXTENSION_CATEGORY = 'Rookie Extension';
    
    private $db;
    private $repository;
    private $processor;
    private $view;
    private $newsService;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->repository = new RookieOptionRepository($db);
        $this->processor = new RookieOptionProcessor();
        $this->view = new RookieOptionView();
        $this->newsService = new \Services\NewsService($db);
    }
    
    /**
     * Main entry point for processing rookie option exercise
     * 
     * @param string $teamName Team name
     * @param int $playerID Player ID
     * @param int $extensionAmount Rookie option amount
     */
    public function processRookieOption(string $teamName, int $playerID, int $extensionAmount): void
    {
        $sharedFunctions = new \Shared($this->db);
        $season = new \Season($this->db);
        
        // Load player
        $player = Player::withPlayerID($this->db, $playerID);
        
        // Validate player eligibility
        if (!$player->canRookieOption($season->phase)) {
            die("This player's experience doesn't match their rookie status; please let the commish know about this error.");
        }
        
        // Determine which contract year to update based on draft round
        if ($player->draftRound != 1 && $player->draftRound != 2) {
            die("This player's experience doesn't match their rookie status; please let the commish know about this error.");
        }
        
        // Update player's contract
        if (!$this->repository->updatePlayerRookieOption($playerID, $player->draftRound, $extensionAmount)) {
            die("Failed to update player contract. Please contact the commissioner.");
        }
        
        // Get team ID for redirect link
        $teamID = $sharedFunctions->getTidFromTeamname($teamName);
        
        // Send Discord notification
        $discordMessage = $teamName . " exercise the rookie extension option on " . $player->name . " in the amount of " . $extensionAmount . ".";
        \Discord::postToChannel(self::DISCORD_CHANNEL, $discordMessage);
        
        // Send email notification
        $emailSubject = "Rookie Extension Option - " . $player->name;
        $emailBody = $discordMessage;
        $emailSuccess = mail(self::NOTIFICATION_EMAIL_RECIPIENT, $emailSubject, $emailBody, "From: " . self::NOTIFICATION_EMAIL_SENDER);
        
        // Create news story if email succeeded
        if ($emailSuccess) {
            $this->createRookieOptionNewsStory($teamName, $player->name, $extensionAmount);
        }
        
        // Display success page
        $this->view->renderSuccessPage($teamName, $teamID, $season->phase, $emailSuccess);
    }
    
    /**
     * Creates a news story for a rookie option exercise
     * 
     * @param string $teamName Team name
     * @param string $playerName Player name
     * @param int $extensionAmount Extension amount in thousands
     */
    private function createRookieOptionNewsStory(string $teamName, string $playerName, int $extensionAmount): void
    {
        $rookieOptionInMillions = $this->processor->convertToMillions($extensionAmount);
        
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
