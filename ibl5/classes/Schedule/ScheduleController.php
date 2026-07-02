<?php

declare(strict_types=1);

namespace Schedule;

use LeagueSchedule\LeagueScheduleRepository;
use LeagueSchedule\LeagueScheduleService;
use LeagueSchedule\LeagueScheduleView;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Schedule\Contracts\ScheduleControllerInterface;
use Standings\StandingsRepository;
use TeamSchedule\TeamScheduleRepository;
use TeamSchedule\TeamScheduleService;
use TeamSchedule\TeamScheduleView;

/**
 * ScheduleController - Page controller for the Schedule module
 *
 * @see \Schedule\Contracts\ScheduleControllerInterface
 */
class ScheduleController implements ScheduleControllerInterface
{
    public function __construct(
        private readonly \mysqli $db,
        private readonly \League\LeagueContext $leagueContext,
        private readonly TeamIdentityRepositoryInterface $commonRepository,
    ) {
    }

    public function render(int $teamid): string
    {
        $db = $this->db;
        $leagueContext = $this->leagueContext;

        $commonRepository = $this->commonRepository;
        $season = new \Season\Season($db, $leagueContext);
        $league = new \League\League($db);

        // Load power rankings for SOS tier indicators
        $standingsRepo = new StandingsRepository($db, $leagueContext);
        $allStreakData = $standingsRepo->getAllStreakData();
        /** @var array<int, float> $teamPowerRankings */
        $teamPowerRankings = [];
        foreach ($allStreakData as $streakTeamId => $data) {
            $teamPowerRankings[$streakTeamId] = (float)$data['ranking'];
        }

        // Validate team ID exists (if provided)
        $team = null;
        if ($teamid > 0) {
            $team = \Team\Team::initialize($db, $teamid);
        }

        if ($team !== null) {
            $teamScheduleRepository = new TeamScheduleRepository($db, $leagueContext);
            $service = new TeamScheduleService($db, $teamScheduleRepository, $teamPowerRankings);
            $view = new TeamScheduleView();

            $teamStreakData = $allStreakData[$teamid] ?? null;
            if ($teamStreakData !== null) {
                $view->setSosSummary([
                    'remaining_sos' => $teamStreakData['remaining_sos'],
                    'remaining_sos_rank' => $teamStreakData['remaining_sos_rank'],
                ]);
            }

            $games = $service->getProcessedSchedule($teamid, $season);
            return $view->render($team, $games, $league->getSimLengthInDays(), $season->phase);
        }

        $repository = new LeagueScheduleRepository($db, $leagueContext);
        $service = new LeagueScheduleService($repository, $teamPowerRankings);
        $view = new LeagueScheduleView();
        $pageData = $service->getSchedulePageData($season, $league, $commonRepository);
        return $view->render($pageData);
    }
}
