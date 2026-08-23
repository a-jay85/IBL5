<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class RetRepository extends \BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @param array{jsb_pid: int, retirement_year: int, player_name: string, pid: int|null} $record
     */
    public function upsertRetiredPlayer(array $record): int
    {
        return $this->execute(
            "INSERT INTO `ibl_jsb_retired_players`
                (jsb_pid, retirement_year, player_name, pid)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                player_name = VALUES(player_name),
                pid = VALUES(pid)",
            'iisi',
            $record['jsb_pid'],
            $record['retirement_year'],
            $record['player_name'],
            $record['pid']
        );
    }

    /**
     * Flag a player as retired in `ibl_plr`. Additive only: never writes 0.
     *
     * @return int Affected rows (1 = flag flipped, 0 = already retired)
     */
    public function markPlayerRetired(int $pid): int
    {
        return $this->execute(
            "UPDATE `ibl_plr` SET `retired` = 1 WHERE `pid` = ? AND (`retired` IS NULL OR `retired` = 0)",
            'i',
            $pid
        );
    }
}
