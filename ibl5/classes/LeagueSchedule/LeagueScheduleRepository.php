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
        $query = "SELECT s.SchedID, s.Date, s.Visitor, s.VScore, s.Home, s.HScore, s.BoxID,
                  bst.gameOfThatDay
                  FROM {$this->scheduleTable} s
                  LEFT JOIN (
                      SELECT Date, visitorTeamID, homeTeamID, MIN(gameOfThatDay) AS gameOfThatDay
                      FROM {$this->boxScoresTeamsTable}
                      GROUP BY Date, visitorTeamID, homeTeamID
                  ) bst ON bst.Date = s.Date AND bst.visitorTeamID = s.Visitor AND bst.homeTeamID = s.Home
                  ORDER BY s.Date ASC, s.SchedID ASC";

        /** @var list<ScheduleRow> $rows */
        $rows = $this->fetchAll($query);

        // Normalize gameOfThatDay — the LEFT JOIN can return null
        foreach ($rows as $index => $row) {
            $rows[$index]['gameOfThatDay'] = (int)($row['gameOfThatDay'] ?? 0);
        }

        return $rows;
    }

    /**
     * @see LeagueScheduleRepositoryInterface::getTeamRecords()
     */
    public function getTeamRecords(): array
    {
        $rows = $this->fetchAll(
            "SELECT tid, leagueRecord FROM {$this->standingsTable} ORDER BY tid ASC"
        );

        /** @var array<int, string> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var int $tid */
            $tid = $row['tid'];
            /** @var string $leagueRecord */
            $leagueRecord = $row['leagueRecord'];
            $records[$tid] = $leagueRecord;
        }

        return $records;
    }
}
