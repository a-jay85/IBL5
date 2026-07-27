<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class DraRepository extends \BaseMysqliRepository
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
}
