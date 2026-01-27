<?php

declare(strict_types=1);

namespace SeriesRecords;

use SeriesRecords\Contracts\SeriesRecordsControllerInterface;
use Services\CommonMysqliRepository;

/**
 * SeriesRecordsController - Main controller for Series Records module
 * 
 * Coordinates between Repository, Service, and View components following
 * the MVC pattern used in other refactored modules.
 * 
 * @see SeriesRecordsControllerInterface
 */
class SeriesRecordsController implements SeriesRecordsControllerInterface
{
    private object $db;
    private SeriesRecordsRepository $repository;
    private SeriesRecordsService $service;
    private SeriesRecordsView $view;
    private CommonMysqliRepository $commonRepository;

    public function __construct(object $db)
    {
        $this->db = $db;
        $this->repository = new SeriesRecordsRepository($db);
        $this->service = new SeriesRecordsService();
        $this->view = new SeriesRecordsView($this->service);
        $this->commonRepository = new CommonMysqliRepository($db);
    }

    /**
     * @see SeriesRecordsControllerInterface::displaySeriesRecords()
     */
    public function displaySeriesRecords(int $userTeamId): void
    {
        \Nuke\Header::header();
        \UI::displaytopmenu($this->db, $userTeamId);

        // Get all teams and series records
        $teams = $this->repository->getTeamsForSeriesRecords();
        $seriesRecords = $this->repository->getSeriesRecords();
        $numTeams = $this->repository->getMaxTeamId();

        // Build matrix for efficient lookup
        $seriesMatrix = $this->service->buildSeriesMatrix($seriesRecords);

        // Render the table
        echo $this->view->renderSeriesRecordsTable($teams, $seriesMatrix, $userTeamId, $numTeams);

        \Nuke\Footer::footer();
    }

    /**
     * @see SeriesRecordsControllerInterface::displayLoginPrompt()
     */
    public function displayLoginPrompt(): void
    {
        global $stop;

        \Nuke\Header::header();
        echo '<div style="text-align: center;"><strong class="title">'
            . ($stop ? _LOGININCOR : _USERREGLOGIN)
            . '</strong></div>';
        echo '<br>';
        loginbox();
        \Nuke\Footer::footer();
    }

    /**
     * @see SeriesRecordsControllerInterface::displayForUser()
     */
    public function displayForUser(string $username): void
    {
        global $user_prefix;

        // Get user's team from user table
        $userInfo = $this->fetchUserInfo($username, $user_prefix);

        if (!$userInfo) {
            $this->displayLoginPrompt();
            return;
        }

        $teamName = $userInfo['user_ibl_team'] ?? '';
        $teamId = $this->commonRepository->getTidFromTeamname($teamName) ?? 0;

        $this->displaySeriesRecords($teamId);
    }

    /**
     * Fetch user information from the users table
     * 
     * @param string $username The username to look up
     * @param string $userPrefix The table prefix for users table
     * @return array<string, mixed>|null User info or null if not found
     */
    private function fetchUserInfo(string $username, string $userPrefix): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM " . $userPrefix . "_users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $userInfo = $result->fetch_assoc();
        $stmt->close();

        return $userInfo ?: null;
    }

    /**
     * Main entry point - handles authentication and routing
     * 
     * @param mixed $user The global $user cookie array
     * @return void
     */
    public function main($user): void
    {
        global $cookie;

        if (!is_user($user)) {
            $this->displayLoginPrompt();
            return;
        }

        cookiedecode($user);
        $username = $cookie[1] ?? '';

        if (empty($username)) {
            $this->displayLoginPrompt();
            return;
        }

        $this->displayForUser($username);
    }
}
