<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class AswRepository extends \BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @param array{season_year: int, event_type: string, roster_slot: int, pid: int|null, player_name: string|null} $record
     */
    public function upsertAllStarRoster(array $record): int
    {
        return $this->execute(
            'INSERT INTO `ibl_jsb_allstar_rosters`
                (season_year, event_type, roster_slot, pid, player_name)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pid = VALUES(pid),
                player_name = VALUES(player_name)',
            'isiis',
            $record['season_year'],
            $record['event_type'],
            $record['roster_slot'],
            $record['pid'],
            $record['player_name']
        );
    }

    /**
     * @param array{season_year: int, contest_type: string, round: int, participant_slot: int, pid: int|null, score: int} $record
     */
    public function upsertAllStarScore(array $record): int
    {
        return $this->execute(
            'INSERT INTO `ibl_jsb_allstar_scores`
                (season_year, contest_type, round, participant_slot, pid, score)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pid = VALUES(pid),
                score = VALUES(score)',
            'isiiii',
            $record['season_year'],
            $record['contest_type'],
            $record['round'],
            $record['participant_slot'],
            $record['pid'],
            $record['score']
        );
    }
}
