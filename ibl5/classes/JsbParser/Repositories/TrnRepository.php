<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class TrnRepository extends \BaseMysqliRepository
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @param array{season_year: int, transaction_month: int, transaction_day: int, transaction_type: int, pid: int, player_name: string|null, from_teamid: int, to_teamid: int, injury_games_missed: int|null, injury_description: string|null, trade_group_id: int|null, is_draft_pick: int, draft_pick_year: int|null, source_file: string|null} $record
     */
    public function upsertTransaction(array $record): int
    {
        return $this->execute(
            "INSERT INTO `ibl_jsb_transactions`
                (season_year, transaction_month, transaction_day, transaction_type,
                 pid, player_name, from_teamid, to_teamid,
                 injury_games_missed, injury_description, trade_group_id,
                 is_draft_pick, draft_pick_year, source_file)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                player_name = VALUES(player_name),
                injury_games_missed = VALUES(injury_games_missed),
                injury_description = VALUES(injury_description),
                trade_group_id = VALUES(trade_group_id),
                is_draft_pick = VALUES(is_draft_pick),
                draft_pick_year = VALUES(draft_pick_year),
                source_file = VALUES(source_file)",
            'iiiiisiiisiiis',
            $record['season_year'],
            $record['transaction_month'],
            $record['transaction_day'],
            $record['transaction_type'],
            $record['pid'],
            $record['player_name'],
            $record['from_teamid'],
            $record['to_teamid'],
            $record['injury_games_missed'],
            $record['injury_description'],
            $record['trade_group_id'],
            $record['is_draft_pick'],
            $record['draft_pick_year'],
            $record['source_file']
        );
    }

    public function fetchMaxTradeGroupId(): int
    {
        $row = $this->fetchOne(
            "SELECT COALESCE(MAX(trade_group_id), 0) AS max_id FROM `ibl_jsb_transactions`",
            ''
        );

        if ($row === null) {
            return 0;
        }

        /** @var int|string $maxId */
        $maxId = $row['max_id'];
        return (int) $maxId;
    }
}
