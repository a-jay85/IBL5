<?php

declare(strict_types=1);

namespace Waivers;

use Player\Player;
use Team\Contracts\TeamQueryRepositoryInterface;
use UI\Components\TableViewSwitcher;
use Waivers\Contracts\WaiversControllerInterface;

/**
 * @see WaiversControllerInterface
 *
 * @phpstan-import-type UserRow from \Services\CommonMysqliRepository
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type TeamInfoRow from \Services\CommonMysqliRepository
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
    private \Services\NewsService $newsService;
    private TeamQueryRepositoryInterface $teamQueryRepo;

    /**
     * Constructor
     *
     * @param \mysqli $db Modern mysqli connection
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
        $this->teamQueryRepo = new \Team\TeamQueryRepository($db);
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

        /** @var array<int, string> $cookie */
        global $cookie;
        $username = (string) ($cookie[1] ?? '');
        $this->executeWaiverOperation($username, $action);
    }

    private function handleNotLoggedIn(): void
    {
        /** @var mixed $stop */
        global $stop;

        /** @var string $message */
        $message = ($stop !== null && $stop !== false && $stop !== 0 && $stop !== '' && $stop !== '0') ? _LOGININCOR : _USERREGLOGIN;
        $this->view->renderNotLoggedIn($message);
    }
    
    /**
     * @see WaiversControllerInterface::executeWaiverOperation()
     */
    public function executeWaiverOperation(string $username, string $action): void
    {
        $userInfo = $this->commonRepository->getUserByUsername($username);

        if ($userInfo === null) {
            /** @var string $loginMessage */
            $loginMessage = _USERREGLOGIN;
            $this->view->renderNotLoggedIn($loginMessage);
            return;
        }

        // PRG: Process POST submission, then redirect
        if (isset($_POST['Action']) && ($_POST['Action'] === 'add' || $_POST['Action'] === 'waive')) {
            /** @var array<string, string> $postData */
            $postData = $_POST;
            $result = $this->processWaiverSubmission($postData);
            $postAction = $_POST['Action'];
            if ($result['success'] === true) {
                $resultParam = $result['result'] ?? '';
                header('Location: modules.php?name=Waivers&action=' . rawurlencode($postAction) . '&result=' . rawurlencode($resultParam));
            } else {
                $errorParam = $result['error'] ?? '';
                header('Location: modules.php?name=Waivers&action=' . rawurlencode($postAction) . '&error=' . rawurlencode($errorParam));
            }
            exit;
        }

        // Display the waiver form (GET request)
        $this->displayWaiverForm($userInfo, $action);
    }
    
    /**
     * @param array<string, string> $postData
     * @return array{success: bool, result?: string, error?: string}
     */
    private function processWaiverSubmission(array $postData): array
    {
        $teamName = $postData['Team_Name'] ?? '';
        $action = $postData['Action'] ?? '';
        $playerID = isset($postData['Player_ID']) ? (int) $postData['Player_ID'] : null;
        $rosterSlots = isset($postData['rosterslots']) ? (int) $postData['rosterslots'] : 0;
        $healthyRosterSlots = isset($postData['healthyrosterslots']) ? (int) $postData['healthyrosterslots'] : 0;

        if ($teamName === '' || !in_array($action, ['add', 'waive'], true)) {
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
        if ($player === null) {
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
        if ($player === null) {
            return ['success' => false, 'error' => 'Player not found.'];
        }

        $season = new \Season($this->db);
        /** @var array{hasExistingContract: bool, salary: int} $contractData */
        $contractData = $this->processor->determineContractData($player, $season); /** @phpstan-ignore argument.type (PlayerRow has all contract fields) */
        $playerSalary = $contractData['salary'];

        if (!$this->validator->validateAdd($playerID, $healthyRosterSlots, $totalSalary, $playerSalary)) {
            return ['success' => false, 'error' => implode(' ', $this->validator->getErrors())];
        }

        $team = $this->commonRepository->getTeamByName($teamName);
        if ($team === null) {
            return ['success' => false, 'error' => 'Team not found.'];
        }

        if (!$this->repository->signPlayerFromWaivers($playerID, $team, $contractData)) {
            return ['success' => false, 'error' => "Oops, something went wrong. Post what you were trying to do in <A HREF=\"" . self::DISCORD_BUGS_CHANNEL_URL . "\">#site-bugs-and-to-do</A> and we'll fix it asap. Sorry!"];
        }

        // Create news story
        $salaryStr = (string) $contractData['salary'];
        $this->createWaiverNewsStory($teamName, $player['name'], 'add', $salaryStr);

        // Send email notification
        $storytitle = $teamName . " make waiver additions";
        $hometext = "The " . $teamName . " sign " . $player['name'] . " from waivers for " . $salaryStr . ".";
        \Mail\MailService::fromConfig()->send(self::NOTIFICATION_EMAIL_RECIPIENT, $storytitle, $hometext, self::NOTIFICATION_EMAIL_SENDER);

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
    
    /**
     * @param UserRow $userInfo
     */
    private function displayWaiverForm(array $userInfo, string $action): void
    {
        $display = isset($_REQUEST['display']) && is_string($_REQUEST['display']) ? $_REQUEST['display'] : 'ratings';

        \Nuke\Header::header();

        $team = \Team::initialize($this->db, $userInfo['user_ibl_team']);

        $season = new \Season($this->db);
        $players = $this->getPlayersForAction($team, $action);

        $openRosterSpots = 15 - count($this->teamQueryRepo->getHealthyAndInjuredPlayersOrderedByName($team->name, $team->teamID, $season));
        $healthyOpenRosterSpots = 15 - count($this->teamQueryRepo->getHealthyPlayersOrderedByName($team->name, $team->teamID, $season));

        $resultParam = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
        $errorParam = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;

        $this->view->renderWaiverForm(
            $team->name,
            $team->teamID,
            $action,
            $players,
            $openRosterSpots,
            $healthyOpenRosterSpots,
            $resultParam,
            $errorParam
        );

        // Display player table with view switcher
        $league = new \League($this->db);

        if ($action === 'waive') {
            $tableResult = $this->teamQueryRepo->getHealthyAndInjuredPlayersOrderedByName($team->name, $team->teamID, $season);
            $styleTeam = $team;
        } elseif ($season->phase === 'Free Agency') {
            $tableResult = $league->getFreeAgentsResult($season);
            $styleTeam = \Team::initialize($this->db, \League::FREE_AGENTS_TEAMID);
        } else {
            $tableResult = $league->getWaivedPlayersResult();
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
        $tableHtml = $this->renderTableForDisplay($display, $tableResult, $styleTeam, $season);
        echo $switcher->wrap($tableHtml);

        \Nuke\Footer::footer();
    }

    /**
     * Render the appropriate table HTML based on display type
     *
     * @param array<int, array<string, mixed>|Player> $result
     */
    private function renderTableForDisplay(string $display, array $result, \Team $team, \Season $season): string
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

    /**
     * @return list<string>
     */
    private function getPlayersForAction(\Team $team, string $action): array
    {
        $league = new \League($this->db);
        $season = new \Season($this->db);
        $timeNow = time();
        /** @var list<string> $players */
        $players = [];

        if ($action === 'waive') {
            $result = $this->teamQueryRepo->getHealthyAndInjuredPlayersOrderedByName($team->name, $team->teamID);
        } elseif ($season->phase === 'Free Agency') {
            $result = $league->getFreeAgentsResult($season);
        } else {
            $result = $league->getWaivedPlayersResult();
        }

        foreach ($result as $playerRow) {
            /** @phpstan-ignore argument.type (PlayerRow from SELECT * matches withPlrRow expectation) */
            $player = Player::withPlrRow($this->db, $playerRow);
            $contract = $this->processor->getPlayerContractDisplay($player, $season);
            $waitTime = '';

            if ($action === 'add' && $player->timeDroppedOnWaivers !== null && $player->timeDroppedOnWaivers > 0) {
                $waitTime = $this->processor->getWaiverWaitTime($player->timeDroppedOnWaivers, $timeNow);
            }

            $playerID = $player->playerID ?? 0;
            $playerName = $player->name ?? '';
            $players[] = $this->view->buildPlayerOption(
                $playerID,
                $playerName,
                $contract,
                $waitTime
            );
        }

        return $players;
    }
}
