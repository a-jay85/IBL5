<?php

declare(strict_types=1);

namespace LeagueSchedule;

use League\LeagueContext;
use LeagueSchedule\Contracts\LeagueScheduleRepositoryInterface;

/**
 * LeagueScheduleRepository - Database operations for league schedule
 *
 * @phpstan-import-type ScheduleRow from LeagueScheduleRepositoryInterface
 *
 * @see LeagueScheduleRepositoryInterface For the interface contract
 */
class LeagueScheduleRepository extends \BaseMysqliRepository implements LeagueScheduleRepositoryInterface
{
    private string $scheduleTable;
    private string $boxScoresTeamsTable;
    private string $standingsTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->scheduleTable = $this->resolveTable('ibl_schedule');
        $this->boxScoresTeamsTable = $this->resolveTable('ibl_box_scores_teams');
        $this->standingsTable = $this->resolveTable('ibl_standings');
    }

    /**
     * @see LeagueScheduleRepositoryInterface::getAllGamesWithBoxScoreInfo()
     *
     * @return list<ScheduleRow>
     */
    public function getAllGamesWithBoxScoreInfo(): array
    {
        $query = "SELECT s.id, s.game_date, s.visitor_teamid, s.visitor_score, s.home_teamid, s.home_score, s.box_id,
                  bst.game_of_that_day
                  FROM {$this->scheduleTable} s
                  LEFT JOIN (
                      SELECT game_date, visitor_teamid, home_teamid, MIN(game_of_that_day) AS game_of_that_day
                      FROM {$this->boxScoresTeamsTable}
                      GROUP BY game_date, visitor_teamid, home_teamid
                  ) bst ON bst.game_date = s.game_date AND bst.visitor_teamid = s.visitor_teamid AND bst.home_teamid = s.home_teamid
                  ORDER BY s.game_date ASC, s.id ASC";

        /** @var list<ScheduleRow> $rows */
        $rows = $this->fetchAll($query);

        // Normalize game_of_that_day — the LEFT JOIN can return null
        foreach ($rows as $index => $row) {
            $rows[$index]['game_of_that_day'] = (int)($row['game_of_that_day'] ?? 0);
        }

        return $rows;
    }

    /**
     * @see LeagueScheduleRepositoryInterface::getTeamRecords()
     */
    public function getTeamRecords(): array
    {
        $rows = $this->fetchAll(
            "SELECT teamid, league_record FROM {$this->standingsTable} ORDER BY teamid ASC"
        );

        /** @var array<int, string> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var int $teamid */
            $teamid = $row['teamid'];
            /** @var string $league_record */
            $league_record = $row['league_record'];
            $records[$teamid] = $league_record;
        }

        return $records;
    }
}
