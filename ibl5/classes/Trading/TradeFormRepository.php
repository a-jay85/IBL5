<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\TradeFormRepositoryInterface;

/**
 * TradeFormRepository - Trade form UI data database operations
 *
 * Handles queries needed by the trade offer and review forms:
 * team rosters, draft picks, team lists, and roster counts.
 * Extracted from TradingRepository to follow single-responsibility principle.
 *
 * @see TradeFormRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradeFormRepositoryInterface
 * @phpstan-import-type TradingDraftPickRow from \Trading\Contracts\TradeFormRepositoryInterface
 * @phpstan-import-type TeamWithCityRow from \Trading\Contracts\TradeFormRepositoryInterface
 */
class TradeFormRepository extends BaseMysqliRepository implements TradeFormRepositoryInterface
{
    /**
     * Constructor
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see TradeFormRepositoryInterface::getTeamPlayersForTrading()
     */
    public function getTeamPlayersForTrading(int $teamId): array
    {
        /** @var list<TradingPlayerRow> */
        return $this->fetchAll(
            "SELECT pos, name, pid, ordinal, cy, cy1, cy2, cy3, cy4, cy5, cy6
             FROM ibl_plr
             WHERE tid = ? AND retired = 0 AND name NOT LIKE '|%'
             ORDER BY ordinal ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TradeFormRepositoryInterface::getTeamDraftPicksForTrading()
     */
    public function getTeamDraftPicksForTrading(int $teamId): array
    {
        /** @var list<TradingDraftPickRow> */
        return $this->fetchAll(
            "SELECT dp.*, dp.teampick_tid AS teampick_id
             FROM ibl_draft_picks dp
             WHERE dp.owner_tid = ?
             ORDER BY dp.year, dp.round ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TradeFormRepositoryInterface::getAllTeamsWithCity()
     */
    public function getAllTeamsWithCity(): array
    {
        /** @var list<TeamWithCityRow> */
        return $this->fetchAllRealTeams('team_city ASC');
    }

    /**
     * @see TradeFormRepositoryInterface::getTeamPlayerCount()
     */
    public function getTeamPlayerCount(int $teamId, bool $isOffseason = false): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM ibl_plr WHERE tid = ? AND retired = 0 AND ordinal <= 960 AND name NOT LIKE '|%'";

        if ($isOffseason) {
            // During offseason, exclude players whose contracts have expired.
            // The effective contract year is cy + 1; if that year's salary is $0,
            // the player is effectively a free agent.
            $sql .= " AND CASE COALESCE(cy, 0) + 1"
                . " WHEN 1 THEN cy1"
                . " WHEN 2 THEN cy2"
                . " WHEN 3 THEN cy3"
                . " WHEN 4 THEN cy4"
                . " WHEN 5 THEN cy5"
                . " WHEN 6 THEN cy6"
                . " ELSE 0"
                . " END != 0";
        }

        /** @var array{cnt: int}|null $result */
        $result = $this->fetchOne($sql, "i", $teamId);
        if ($result === null) {
            return 0;
        }
        return $result['cnt'];
    }
}
