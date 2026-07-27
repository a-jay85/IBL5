<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class PlbRepository extends \BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @param array{season_year: int, sim_number: int, source_archive: string, teamid: int, slot_index: int, pid: int|null, player_name: string|null, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int} $record
     */
    public function upsertPlbSnapshot(array $record): int
    {
        return $this->execute(
            "INSERT INTO `ibl_plb_snapshots`
                (season_year, sim_number, source_archive, teamid, slot_index,
                 pid, player_name, dc_minutes, dc_of, dc_df, dc_oi, dc_di, dc_bh)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                season_year = VALUES(season_year),
                sim_number = VALUES(sim_number),
                pid = VALUES(pid),
                player_name = VALUES(player_name),
                dc_minutes = VALUES(dc_minutes),
                dc_of = VALUES(dc_of),
                dc_df = VALUES(dc_df),
                dc_oi = VALUES(dc_oi),
                dc_di = VALUES(dc_di),
                dc_bh = VALUES(dc_bh)",
            'iisiiisiiiiii',
            $record['season_year'],
            $record['sim_number'],
            $record['source_archive'],
            $record['teamid'],
            $record['slot_index'],
            $record['pid'],
            $record['player_name'],
            $record['dc_minutes'],
            $record['dc_of'],
            $record['dc_df'],
            $record['dc_oi'],
            $record['dc_di'],
            $record['dc_bh']
        );
    }
}
