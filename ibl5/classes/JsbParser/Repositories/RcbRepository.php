<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class RcbRepository extends \BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @param list<array{scope: string, teamid: int, record_type: string, stat_category: string, ranking: int, player_name: string, car_block_id: int, pid: int|null, stat_value: float, stat_raw: int, team_of_record: int|null, season_year: int|null, career_total: int|null, source_file: string|null}> $records
     */
    public function replaceRcbAlltimeRecords(array $records): int
    {
        return $this->transactional(function () use ($records): int {
            $this->execute("DELETE FROM `ibl_rcb_alltime_records`");

            $total = 0;
            $columns = '(scope, teamid, record_type, stat_category, ranking,'
                . ' player_name, car_block_id, pid, stat_value, stat_raw,'
                . ' team_of_record, season_year, career_total, source_file)';
            $rowTypes = 'sissisiidiiiis';

            foreach (array_chunk($records, 500) as $chunk) {
                $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));
                $types = str_repeat($rowTypes, count($chunk));
                $params = [];
                foreach ($chunk as $r) {
                    $params[] = $r['scope'];
                    $params[] = $r['teamid'];
                    $params[] = $r['record_type'];
                    $params[] = $r['stat_category'];
                    $params[] = $r['ranking'];
                    $params[] = $r['player_name'];
                    $params[] = $r['car_block_id'];
                    $params[] = $r['pid'];
                    $params[] = $r['stat_value'];
                    $params[] = $r['stat_raw'];
                    $params[] = $r['team_of_record'];
                    $params[] = $r['season_year'];
                    $params[] = $r['career_total'];
                    $params[] = $r['source_file'];
                }
                $total += $this->execute(
                    "INSERT INTO `ibl_rcb_alltime_records` " . $columns . " VALUES " . $placeholders, // @phpstan-ignore ibl.sqlStringConcatenation
                    $types,
                    ...$params
                );
            }

            return $total;
        });
    }

    /**
     * @param list<array{season_year: int, scope: string, teamid: int, context: string, stat_category: string, ranking: int, player_name: string, player_position: string|null, car_block_id: int, pid: int|null, stat_value: int, record_season_year: int, source_file: string|null}> $records
     */
    public function replaceRcbSeasonRecords(int $seasonYear, array $records): int
    {
        return $this->transactional(function () use ($seasonYear, $records): int {
            $this->execute(
                "DELETE FROM `ibl_rcb_season_records` WHERE season_year = ?",
                'i',
                $seasonYear
            );

            $total = 0;
            $columns = '(season_year, scope, teamid, context, stat_category, ranking,'
                . ' player_name, player_position, car_block_id, pid,'
                . ' stat_value, record_season_year, source_file)';
            $rowTypes = 'isississiiiis';

            foreach (array_chunk($records, 500) as $chunk) {
                $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));
                $types = str_repeat($rowTypes, count($chunk));
                $params = [];
                foreach ($chunk as $r) {
                    $params[] = $r['season_year'];
                    $params[] = $r['scope'];
                    $params[] = $r['teamid'];
                    $params[] = $r['context'];
                    $params[] = $r['stat_category'];
                    $params[] = $r['ranking'];
                    $params[] = $r['player_name'];
                    $params[] = $r['player_position'];
                    $params[] = $r['car_block_id'];
                    $params[] = $r['pid'];
                    $params[] = $r['stat_value'];
                    $params[] = $r['record_season_year'];
                    $params[] = $r['source_file'];
                }
                $total += $this->execute(
                    "INSERT INTO `ibl_rcb_season_records` " . $columns . " VALUES " . $placeholders, // @phpstan-ignore ibl.sqlStringConcatenation
                    $types,
                    ...$params
                );
            }

            return $total;
        });
    }
}
