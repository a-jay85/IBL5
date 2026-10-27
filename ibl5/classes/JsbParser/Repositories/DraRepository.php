<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class DraRepository extends \Database\BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @param array{draft_year: int, round: int, pick: int, team_name: string, pos: string, player_name: string, pid: int|null} $record
     */
    public function upsertDraftResult(array $record): int
    {
        return $this->execute(
            "INSERT INTO `ibl_jsb_draft_results`
                (draft_year, round, pick, team_name, pos, player_name, pid)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                team_name = VALUES(team_name),
                pos = VALUES(pos),
                player_name = VALUES(player_name),
                pid = VALUES(pid)",
            'iiisssi',
            $record['draft_year'],
            $record['round'],
            $record['pick'],
            $record['team_name'],
            $record['pos'],
            $record['player_name'],
            $record['pid']
        );
    }

    /**
     * Check if any draft pick is stored for a given draft year.
     */
    public function hasDraftResultsForYear(int $draftYear): bool
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `ibl_jsb_draft_results`
             WHERE draft_year = ?",
            'i',
            $draftYear,
        );

        if ($row === null) {
            return false;
        }

        $cnt = $row['cnt'];

        return is_int($cnt) && $cnt > 0;
    }
}
