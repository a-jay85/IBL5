<?php

declare(strict_types=1);

namespace NextSim;

use Standings\StandingsRepository;
use TeamSchedule\TeamScheduleRepository;
use Season\Season;
use Team\Team;

/**
 * HTMX endpoint handler for NextSim position tab switching
 *
 * Returns the tabbed position table HTML for a given position without the full page layout.
 */
class NextSimTabApiHandler
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $teamid = isset($_GET['teamid']) && is_string($_GET['teamid']) ? (int) $_GET['teamid'] : 0;

        $position = 'PG';
        if (isset($_GET['position']) && is_string($_GET['position'])) {
            $rawPosition = $_GET['position'];
            if (in_array($rawPosition, \JSB::PLAYER_POSITIONS, true)) {
                $position = $rawPosition;
            }
        }

        $season = new Season($this->db);
        $team = Team::initialize($this->db, $teamid);

        // Load power rankings for SOS tier indicators
        $standingsRepo = new StandingsRepository($this->db);
        $allStreakData = $standingsRepo->getAllStreakData();
        /** @var array<int, float> $teamPowerRankings */
        $teamPowerRankings = [];
        foreach ($allStreakData as $rankedTeamid => $data) {
            $teamPowerRankings[$rankedTeamid] = (float) $data['ranking'];
        }

        $teamScheduleRepository = new TeamScheduleRepository($this->db);
        $service = new NextSimService($this->db, $teamScheduleRepository, $teamPowerRankings);
        $view = new NextSimView($season);

        $games = $service->getNextSimGames($teamid, $season);
        $userStarters = $service->getUserStartingLineup($team);

        echo $view->renderTabbedPositionTable($games, $position, $team, $userStarters);
    }
}
