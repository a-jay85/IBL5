<?php

declare(strict_types=1);

namespace Waivers;

use Player\Player;
use Waivers\Contracts\WaiversControllerInterface;

/**
 * @see WaiversControllerInterface
 */
class WaiversController implements WaiversControllerInterface
{
    // Configuration constants
    private const DISCORD_BUGS_CHANNEL_URL = 'https://discord.com/channels/666986450889474053/671435182502576169';
    private const NOTIFICATION_EMAIL_RECIPIENT = 'ibldepthcharts@gmail.com';
    private const NOTIFICATION_EMAIL_SENDER = 'waivers@iblhoops.net';
    public const WAIVER_POOL_MOVES_CATEGORY_ID = 1;
    private const WAIVER_POOL_MOVES_CATEGORY = 'Waiver Pool Moves';
    
    private \mysqli $db;
    private WaiversRepository $repository;
    private \Services\CommonMysqliRepository $commonRepository;
    private WaiversProcessor $processor;
    private WaiversValidator $validator;
    private WaiversView $view;
    private $newsService;
    
    /**
     * Constructor
     * 
     * @param \mysqli $mysqli_db Modern mysqli connection
     */
    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new WaiversRepository($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
        $this->processor = new WaiversProcessor();
        $this->validator = new WaiversValidator();
        $this->view = new WaiversView();
        $this->newsService = new \Services\NewsService($db);
    }
    
    /**
     * @see WaiversControllerInterface::handleWaiverRequest()
     */
    public function handleWaiverRequest($user, string $action): void
    {
        $season = new \Season($this->db);
        
        if (!is_user($user)) {
            $this->handleNotLoggedIn();
            return;
        }
        
        if ($season->allowWaivers !== "Yes") {
            $this->view->renderWaiversClosed();
            return;
        }
        
        global $cookie;
        $this->executeWaiverOperation($cookie[1], $action);
    }
    
    private function handleNotLoggedIn(): void
    {
        global $stop;
        
        $message = $stop ? _LOGININCOR : _USERREGLOGIN;
        $this->view->renderNotLoggedIn($message);
    }
    
    /**
     * @see WaiversControllerInterface::executeWaiverOperation()
     */
    public function executeWaiverOperation(string $username, string $action): void
    {
        $userInfo = $this->commonRepository->getUserByUsername($username);
        
        if (!$userInfo) {
            $this->view->renderNotLoggedIn(_USERREGLOGIN);
            return;
        }
        
        $errorMessage = '';
        
        // Process submission if data was posted
        if (isset($_POST['Action']) && ($_POST['Action'] === 'add' || $_POST['Action'] === 'drop')) {
            $errorMessage = $this->processWaiverSubmission($_POST);
        }
        
        // Display the waiver form
        $this->displayWaiverForm($userInfo, $action, $errorMessage);
    }
    
    private function processWaiverSubmission(array $postData): string
    {
        $teamName = $postData['Team_Name'] ?? '';
        $action = $postData['Action'] ?? '';
        $playerID = isset($postData['Player_ID']) ? (int) $postData['Player_ID'] : null;
        $rosterSlots = isset($postData['rosterslots']) ? (int) $postData['rosterslots'] : 0;
        $healthyRosterSlots = isset($postData['healthyrosterslots']) ? (int) $postData['healthyrosterslots'] : 0;
        
        if (empty($teamName) || !in_array($action, ['add', 'drop'])) {
            return "Invalid submission data.";
        }
        
        $totalSalary = $this->commonRepository->getTeamTotalSalary($teamName);
        
        if ($action === 'drop') {
            return $this->processDrop($playerID, $teamName, $rosterSlots, $totalSalary);
        } else {
            return $this->processAdd($playerID, $teamName, $healthyRosterSlots, $totalSalary);
        }
    }
    
    private function processDrop(?int $playerID, string $teamName, int $rosterSlots, int $totalSalary): string
    {
        if (!$this->validator->validateDrop($rosterSlots, $totalSalary)) {
            return implode(' ', $this->validator->getErrors());
        }
        
        if ($playerID === null || $playerID === 0) {
            return "You didn't select a valid player. Please select a player and try again.";
        }
        
        $player = $this->commonRepository->getPlayerByID($playerID);
        if (!$player) {
            return "Player not found.";
        }
        
        $timestamp = time();
        
        if (!$this->repository->dropPlayerToWaivers($playerID, $timestamp)) {
            return "Failed to drop player to waivers. Please try again.";
        }
        
        // Create news story
        $this->createWaiverNewsStory($teamName, $player['name'], 'drop', '');
        
        // Send Discord notification
        $hometext = "The " . $teamName . " cut " . $player['name'] . " to waivers.";
        \Discord::postToChannel('#waiver-wire', $hometext);
        
        return "Your waiver move should now be processed. " . $player['name'] . " has been cut to waivers.";
    }
    
    private function processAdd(?int $playerID, string $teamName, int $healthyRosterSlots, int $totalSalary): string
    {
        if ($playerID === null || $playerID === 0) {
            return "You didn't select a valid player. Please select a player and try again.";
        }
        
        $player = $this->commonRepository->getPlayerByID($playerID);
        if (!$player) {
            return "Player not found.";
        }
        
        $season = new \Season($this->db);
        $contractData = $this->processor->determineContractData($player, $season);
        $playerSalary = (int) ($contractData['salary'] ?? 0);
        
        if (!$this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary)) {
            return implode(' ', $this->validator->getErrors());
        }
        
        $team = $this->commonRepository->getTeamByName($teamName);
        if (!$team) {
            return "Team not found.";
        }
        
        if (!$this->repository->signPlayerFromWaivers($playerID, $team, $contractData)) {
            return "Oops, something went wrong. Post what you were trying to do in <A HREF=\"" . self::DISCORD_BUGS_CHANNEL_URL . "\">#site-bugs-and-to-do</A> and we'll fix it asap. Sorry!";
        }
        
        // Create news story
        $this->createWaiverNewsStory($teamName, $player['name'], 'add', (string) $contractData['salary']);
        
        // Send email notification
        $storytitle = $teamName . " make waiver additions";
        $hometext = "The " . $teamName . " sign " . $player['name'] . " from waivers for " . $contractData['salary'] . ".";
        mail(self::NOTIFICATION_EMAIL_RECIPIENT, $storytitle, $hometext, "From: " . self::NOTIFICATION_EMAIL_SENDER);
        
        // Send Discord notification
        \Discord::postToChannel('#waiver-wire', $hometext);
        
        return "Your waiver move should now be processed. " . $player['name'] . " has been signed from waivers and added to your roster.";
    }
    
    private function createWaiverNewsStory(string $teamName, string $playerName, string $action, string $contract): void
    {
        $this->newsService->incrementCategoryCounter(self::WAIVER_POOL_MOVES_CATEGORY);
        
        if ($action === 'drop') {
            $topicID = 32;
            $storytitle = $teamName . " make waiver cuts";
            $hometext = "The " . $teamName . " cut " . $playerName . " to waivers.";
        } else {
            $topicID = 33;
            $storytitle = $teamName . " make waiver additions";
            $hometext = "The " . $teamName . " sign " . $playerName . " from waivers for " . $contract . ".";
        }
        
        $categoryID = $this->newsService->getCategoryIDByTitle(self::WAIVER_POOL_MOVES_CATEGORY);
        if ($categoryID !== null) {
            $this->newsService->createNewsStory($categoryID, $topicID, $storytitle, $hometext);
        }
    }
    
    private function displayWaiverForm(array $userInfo, string $action, string $errorMessage): void
    {
        \Nuke\Header::header();
        OpenTable();
        
        $team = \Team::initialize($this->db, $userInfo['user_ibl_team']);
        \UI::displaytopmenu($this->db, $team->teamID);
        
        $season = new \Season($this->db);
        $players = $this->getPlayersForAction($team, $action);
        
        $openRosterSpots = 15 - count($team->getHealthyAndInjuredPlayersOrderedByNameResult($season));
        $healthyOpenRosterSpots = 15 - count($team->getHealthyPlayersOrderedByNameResult($season));
        
        $this->view->renderWaiverForm(
            $team->name,
            $team->teamID,
            $action,
            $players,
            $openRosterSpots,
            $healthyOpenRosterSpots,
            $errorMessage
        );
        
        // Display player ratings table
        $league = new \League($this->db);
        
        if ($action === 'drop') {
            $result = $team->getHealthyAndInjuredPlayersOrderedByNameResult($season);
        } elseif ($season->phase === 'Free Agency') {
            $result = $league->getFreeAgentsResult($season);
        } else {
            $result = $league->getWaivedPlayersResult();
        }
        
        $teamFreeAgency = \Team::initialize($this->db, \League::FREE_AGENTS_TEAMID);
        $tableRatings = \UI::ratings($this->db, $result, $teamFreeAgency, "", $season);
        echo $tableRatings;
        
        CloseTable();
        \Nuke\Footer::footer();
    }
    
    private function getPlayersForAction($team, string $action): array
    {
        $league = new \League($this->db);
        $season = new \Season($this->db);
        $timeNow = time();
        $players = [];
        
        if ($action === 'drop') {
            $result = $team->getHealthyAndInjuredPlayersOrderedByNameResult();
        } elseif ($season->phase === 'Free Agency') {
            $result = $league->getFreeAgentsResult($season);
        } else {
            $result = $league->getWaivedPlayersResult();
        }
        
        foreach ($result as $playerRow) {
            $player = Player::withPlrRow($this->db, $playerRow);
            $contract = $this->processor->getPlayerContractDisplay($player, $season);
            $waitTime = '';
            
            if ($action === 'add' && $player->timeDroppedOnWaivers > 0) {
                $waitTime = $this->processor->getWaiverWaitTime((int) $player->timeDroppedOnWaivers, $timeNow);
            }
            
            $players[] = $this->view->buildPlayerOption(
                (int) $player->playerID,
                $player->name,
                $contract,
                $waitTime
            );
        }
        
        return $players;
    }
}
