<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class HofRepository extends \Database\BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @param array{jsb_pid: int, player_name: string, pos: string, induction_year: int, pid: int|null} $record
     */
    public function upsertHofInductee(array $record): int
    {
        return $this->execute(
            "INSERT INTO `ibl_jsb_hall_of_fame`
                (jsb_pid, player_name, pos, induction_year, pid)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                player_name = VALUES(player_name),
                pos = VALUES(pos),
                induction_year = VALUES(induction_year),
                pid = VALUES(pid)",
            'issii',
            $record['jsb_pid'],
            $record['player_name'],
            $record['pos'],
            $record['induction_year'],
            $record['pid']
        );
    }

    /**
     * Check if any Hall of Fame inductee is stored for a given induction year.
     */
    public function hasHofInducteesForYear(int $inductionYear): bool
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `ibl_jsb_hall_of_fame`
             WHERE induction_year = ?",
            'i',
            $inductionYear,
        );

        if ($row === null) {
            return false;
        }

        $cnt = $row['cnt'];

        return is_int($cnt) && $cnt > 0;
    }
}
