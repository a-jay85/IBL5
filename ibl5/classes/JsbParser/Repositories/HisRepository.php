<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class HisRepository extends \BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @param array{season_year: int, team_name: string, teamid: int|null, wins: int, losses: int, made_playoffs: int, playoff_result: string|null, playoff_round_reached: string|null, won_championship: int, source_file: string|null} $record
     */
    public function upsertHistoryRecord(array $record): int
    {
        return $this->execute(
            "INSERT INTO `ibl_jsb_history`
                (season_year, team_name, teamid, wins, losses, made_playoffs,
                 playoff_result, playoff_round_reached, won_championship, source_file)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                teamid = VALUES(teamid),
                wins = VALUES(wins),
                losses = VALUES(losses),
                made_playoffs = VALUES(made_playoffs),
                playoff_result = VALUES(playoff_result),
                playoff_round_reached = VALUES(playoff_round_reached),
                won_championship = VALUES(won_championship),
                source_file = VALUES(source_file)",
            'isiiiissss',
            $record['season_year'],
            $record['team_name'],
            $record['teamid'],
            $record['wins'],
            $record['losses'],
            $record['made_playoffs'],
            $record['playoff_result'],
            $record['playoff_round_reached'],
            $record['won_championship'],
            $record['source_file']
        );
    }

    public function hasChampionForSeason(int $seasonYear): bool
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `ibl_jsb_history`
             WHERE season_year = ? AND won_championship = 1",
            'i',
            $seasonYear,
        );

        if ($row === null) {
            return false;
        }

        $cnt = $row['cnt'];

        return is_int($cnt) && $cnt > 0;
    }
}
