<?php

declare(strict_types=1);

namespace Waivers;

use Player\Player;
use UI\Components\TableViewSwitcher;
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

        // PRG: Process POST submission, then redirect
        if (isset($_POST['Action']) && ($_POST['Action'] === 'add' || $_POST['Action'] === 'waive')) {
            $result = $this->processWaiverSubmission($_POST);
            $postAction = $_POST['Action'];
            if ($result['success']) {
                header('Location: modules.php?name=Waivers&action=' . rawurlencode($postAction) . '&result=' . rawurlencode($result['result']));
            } else {
                header('Location: modules.php?name=Waivers&action=' . rawurlencode($postAction) . '&error=' . rawurlencode($result['error']));
            }
            exit;
        }

        // Display the waiver form (GET request)
        $this->displayWaiverForm($userInfo, $action);
    }
    
    /**
     * @return array{success: bool, result?: string, error?: string}
     */
    private function processWaiverSubmission(array $postData): array
    {
        $teamName = $postData['Team_Name'] ?? '';
        $action = $postData['Action'] ?? '';
        $playerID = isset($postData['Player_ID']) ? (int) $postData['Player_ID'] : null;
        $rosterSlots = isset($postData['rosterslots']) ? (int) $postData['rosterslots'] : 0;
        $healthyRosterSlots = isset($postData['healthyrosterslots']) ? (int) $postData['healthyrosterslots'] : 0;

        if (empty($teamName) || !in_array($action, ['add', 'waive'])) {
            return ['success' => false, 'error' => 'Invalid submission data.'];
        }

        $totalSalary = $this->commonRepository->getTeamTotalSalary($teamName);

        if ($action === 'waive') {
            return $this->processDrop($playerID, $teamName, $rosterSlots, $totalSalary);
        } else {
            return $this->processAdd($playerID, $teamName, $healthyRosterSlots, $totalSalary);
        }
    }
    
    /**
     * @return array{success: bool, result?: string, error?: string}
     */
    private function processDrop(?int $playerID, string $teamName, int $rosterSlots, int $totalSalary): array
    {
        if (!$this->validator->validateDrop($rosterSlots, $totalSalary)) {
            return ['success' => false, 'error' => implode(' ', $this->validator->getErrors())];
        }

        if ($playerID === null || $playerID === 0) {
            return ['success' => false, 'error' => "You didn't select a valid player. Please select a player and try again."];
        }

        $player = $this->commonRepository->getPlayerByID($playerID);
        if (!$player) {
            return ['success' => false, 'error' => 'Player not found.'];
        }

        $timestamp = time();

        if (!$this->repository->dropPlayerToWaivers($playerID, $timestamp)) {
            return ['success' => false, 'error' => 'Failed to drop player to waivers. Please try again.'];
        }

        // Create news story
        $this->createWaiverNewsStory($teamName, $player['name'], 'waive', '');

        // Send Discord notification
        $hometext = "The " . $teamName . " cut " . $player['name'] . " to waivers.";
        \Discord::postToChannel('#waiver-wire', $hometext);

        return ['success' => true, 'result' => 'player_dropped'];
    }
    
    /**
     * @return array{success: bool, result?: string, error?: string}
     */
    private function processAdd(?int $playerID, string $teamName, int $healthyRosterSlots, int $totalSalary): array
    {
        if ($playerID === null || $playerID === 0) {
            return ['success' => false, 'error' => "You didn't select a valid player. Please select a player and try again."];
        }

        $player = $this->commonRepository->getPlayerByID($playerID);
        if (!$player) {
            return ['success' => false, 'error' => 'Player not found.'];
        }

        $season = new \Season($this->db);
        $contractData = $this->processor->determineContractData($player, $season);
        $playerSalary = (int) ($contractData['salary'] ?? 0);

        if (!$this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary)) {
            return ['success' => false, 'error' => implode(' ', $this->validator->getErrors())];
        }

        $team = $this->commonRepository->getTeamByName($teamName);
        if (!$team) {
            return ['success' => false, 'error' => 'Team not found.'];
        }

        if (!$this->repository->signPlayerFromWaivers($playerID, $team, $contractData)) {
            return ['success' => false, 'error' => "Oops, something went wrong. Post what you were trying to do in <A HREF=\"" . self::DISCORD_BUGS_CHANNEL_URL . "\">#site-bugs-and-to-do</A> and we'll fix it asap. Sorry!"];
        }

        // Create news story
        $this->createWaiverNewsStory($teamName, $player['name'], 'add', (string) $contractData['salary']);

        // Send email notification
        $storytitle = $teamName . " make waiver additions";
        $hometext = "The " . $teamName . " sign " . $player['name'] . " from waivers for " . $contractData['salary'] . ".";
        mail(self::NOTIFICATION_EMAIL_RECIPIENT, $storytitle, $hometext, "From: " . self::NOTIFICATION_EMAIL_SENDER);

        // Send Discord notification
        \Discord::postToChannel('#waiver-wire', $hometext);

        return ['success' => true, 'result' => 'player_added'];
    }
    
    private function createWaiverNewsStory(string $teamName, string $playerName, string $action, string $contract): void
    {
        $this->newsService->incrementCategoryCounter(self::WAIVER_POOL_MOVES_CATEGORY);
        
        if ($action === 'waive') {
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
    
    private function displayWaiverForm(array $userInfo, string $action): void
    {
        $display = $_REQUEST['display'] ?? 'ratings';

        \Nuke\Header::header();

        $team = \Team::initialize($this->db, $userInfo['user_ibl_team']);

        $season = new \Season($this->db);
        $players = $this->getPlayersForAction($team, $action);

        $openRosterSpots = 15 - count($team->getHealthyAndInjuredPlayersOrderedByNameResult($season));
        $healthyOpenRosterSpots = 15 - count($team->getHealthyPlayersOrderedByNameResult($season));

        $result = $_GET['result'] ?? null;
        $error = $_GET['error'] ?? null;

        $this->view->renderWaiverForm(
            $team->name,
            $team->teamID,
            $action,
            $players,
            $openRosterSpots,
            $healthyOpenRosterSpots,
            $result,
            $error
        );

        // Display player table with view switcher
        $league = new \League($this->db);

        if ($action === 'waive') {
            $result = $team->getHealthyAndInjuredPlayersOrderedByNameResult($season);
            $styleTeam = $team;
        } elseif ($season->phase === 'Free Agency') {
            $result = $league->getFreeAgentsResult($season);
            $styleTeam = \Team::initialize($this->db, \League::FREE_AGENTS_TEAMID);
        } else {
            $result = $league->getWaivedPlayersResult();
            $styleTeam = \Team::initialize($this->db, \League::FREE_AGENTS_TEAMID);
        }

        $tabDefinitions = [
            'ratings' => 'Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
            'per36mins' => 'Per 36 Minutes',
        ];

        $baseUrl = 'modules.php?name=Waivers&action=' . $action;
        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $styleTeam->color1, $styleTeam->color2);
        $tableHtml = $this->renderTableForDisplay($display, $result, $styleTeam, $season);
        echo $switcher->wrap($tableHtml);

        \Nuke\Footer::footer();
    }

    /**
     * Render the appropriate table HTML based on display type
     */
    private function renderTableForDisplay(string $display, array $result, object $team, object $season): string
    {
        switch ($display) {
            case 'total_s':
                return \UI::seasonTotals($this->db, $result, $team, '');
            case 'avg_s':
                return \UI::seasonAverages($this->db, $result, $team, '');
            case 'per36mins':
                return \UI::per36Minutes($this->db, $result, $team, '');
            default:
                return \UI::ratings($this->db, $result, $team, '', $season);
        }
    }

    private function getPlayersForAction($team, string $action): array
    {
        $league = new \League($this->db);
        $season = new \Season($this->db);
        $timeNow = time();
        $players = [];
        
        if ($action === 'waive') {
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
