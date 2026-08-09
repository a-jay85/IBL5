<?php

declare(strict_types=1);

namespace Schedule;

use LeagueSchedule\Contracts\LeagueScheduleRepositoryInterface;
use LeagueSchedule\LeagueScheduleService;
use LeagueSchedule\LeagueScheduleView;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Schedule\Contracts\ScheduleControllerInterface;
use Standings\Contracts\StandingsRepositoryInterface;
use TeamSchedule\Contracts\TeamScheduleRepositoryInterface;
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
        private readonly StandingsRepositoryInterface $standingsRepository,
        private readonly TeamScheduleRepositoryInterface $teamScheduleRepository,
        private readonly LeagueScheduleRepositoryInterface $leagueScheduleRepository,
    ) {
    }

    public function render(int $teamid): string
    {
        $db = $this->db;
        $commonRepository = $this->commonRepository;
        $season = new \Season\Season($db, $this->leagueContext);
        $league = new \League\League($db);

        // Load power rankings for SOS tier indicators
        $allStreakData = $this->standingsRepository->getAllStreakData();
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
            $service = new TeamScheduleService($db, $this->teamScheduleRepository, $teamPowerRankings);
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

        $service = new LeagueScheduleService($this->leagueScheduleRepository, $teamPowerRankings);
        $view = new LeagueScheduleView();
        $pageData = $service->getSchedulePageData($season, $league, $commonRepository);
        return $view->render($pageData);
    }
}
