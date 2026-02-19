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
    private \mysqli $db;
    private SeriesRecordsRepository $repository;
    private SeriesRecordsService $service;
    private SeriesRecordsView $view;
    private CommonMysqliRepository $commonRepository;

    public function __construct(\mysqli $db)
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
     * @see SeriesRecordsControllerInterface::displayForUser()
     */
    public function displayForUser(string $username): void
    {
        /** @var string $user_prefix */
        global $user_prefix;

        // Get user's team from user table
        $userInfo = $this->fetchUserInfo($username, $user_prefix);

        if ($userInfo === null) {
            $this->displaySeriesRecords(0);
            return;
        }

        /** @var string $teamName */
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
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return null;
        }
        $userInfo = $result->fetch_assoc();
        $stmt->close();

        if ($userInfo === null || $userInfo === false) {
            return null;
        }

        return $userInfo;
    }

    /**
     * Main entry point - display series records for all users (no login required)
     *
     * @param mixed $user The global $user cookie array
     * @return void
     */
    public function main(mixed $user): void
    {
        if (is_user($user)) {
            /** @var array<int, string> $cookie */
            global $cookie;
            cookiedecode($user);
            $username = $cookie[1] ?? '';

            if ($username !== '') {
                $this->displayForUser($username);
                return;
            }
        }

        $this->displaySeriesRecords(0);
    }
}
