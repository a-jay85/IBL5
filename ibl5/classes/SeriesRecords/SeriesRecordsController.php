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
    private SeriesRecordsRepository $repository;
    private SeriesRecordsService $service;
    private SeriesRecordsView $view;
    private CommonMysqliRepository $commonRepository;
    private \Utilities\NukeCompat $nukeCompat;

    public function __construct(\mysqli $db, ?\Utilities\NukeCompat $nukeCompat = null)
    {
        $this->repository = new SeriesRecordsRepository($db);
        $this->service = new SeriesRecordsService();
        $this->view = new SeriesRecordsView($this->service);
        $this->commonRepository = new CommonMysqliRepository($db);
        $this->nukeCompat = $nukeCompat ?? new \Utilities\NukeCompat();
    }

    /**
     * @see SeriesRecordsControllerInterface::displaySeriesRecords()
     */
    public function displaySeriesRecords(int $userTeamId): void
    {
        \PageLayout\PageLayout::header();

        // Get all teams and series records
        $teams = $this->repository->getTeamsForSeriesRecords();
        $seriesRecords = $this->repository->getSeriesRecords();
        $numTeams = $this->repository->getMaxTeamId();

        // Build matrix for efficient lookup
        $seriesMatrix = $this->service->buildSeriesMatrix($seriesRecords);

        // Render the table
        echo $this->view->renderSeriesRecordsTable($teams, $seriesMatrix, $userTeamId, $numTeams);

        \PageLayout\PageLayout::footer();
    }

    /**
     * @see SeriesRecordsControllerInterface::displayForUser()
     */
    public function displayForUser(string $username): void
    {
        $teamName = $this->commonRepository->getTeamnameFromUsername($username);
        $teamId = ($teamName !== null && $teamName !== \League::FREE_AGENTS_TEAM_NAME)
            ? ($this->commonRepository->getTidFromTeamname($teamName) ?? 0)
            : 0;

        $this->displaySeriesRecords($teamId);
    }

    /**
     * Main entry point - display series records for all users (no login required)
     *
     * @param mixed $user The global $user cookie array
     * @return void
     */
    public function main(mixed $user): void
    {
        if ($this->nukeCompat->isUser($user)) {
            $cookie = $this->nukeCompat->cookieDecode($user);
            $username = $cookie[1] ?? '';

            if ($username !== '') {
                $this->displayForUser($username);
                return;
            }
        }

        $this->displaySeriesRecords(0);
    }
}
