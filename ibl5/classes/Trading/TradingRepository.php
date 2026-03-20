<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\TradingRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;

/**
 * TradingRepository - Core trade offer CRUD database operations
 *
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Handles core trade offer queries, player/pick lookups, and trade item management.
 *
 * Cash transaction methods are in TradeCashRepository.
 * Queue/execution methods are in TradeExecutionRepository.
 *
 * @see TradingRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type TradeValidationRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TeamNameRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TeamWithCityRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradeInfoRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type DraftPickRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradingDraftPickRow from \Trading\Contracts\TradingRepositoryInterface
 */
class TradingRepository extends BaseMysqliRepository implements TradingRepositoryInterface
{
    private TradeCashRepositoryInterface $cashRepository;

    /**
     * Constructor
     *
     * @param \mysqli $db Active mysqli connection
     * @param TradeCashRepositoryInterface|null $cashRepository Cash repository for deleteTradeOffer coordination
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db, ?TradeCashRepositoryInterface $cashRepository = null)
    {
        parent::__construct($db);
        $this->cashRepository = $cashRepository ?? new TradeCashRepository($db);
    }

    /**
     * @see TradingRepositoryInterface::getPlayerForTradeValidation()
     */
    public function getPlayerForTradeValidation(int $playerId): ?array
    {
        /** @var TradeValidationRow|null */
        return $this->fetchOne(
            "SELECT ordinal, cy FROM ibl_plr WHERE pid = ?",
            "i",
            $playerId
        );
    }

    /**
     * @see TradingRepositoryInterface::getAllTeams()
     */
    public function getAllTeams(): array
    {
        /** @var list<TeamNameRow> */
        return $this->fetchAllRealTeams('team_name ASC');
    }

    /**
     * @see TradingRepositoryInterface::getTradePlayers()
     */
    public function getTradePlayers(string $teamName, int $row): array
    {
        /** @var list<array<string, mixed>> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_players WHERE teamname = ? AND row = ?",
            "si",
            $teamName,
            $row
        );
    }

    /**
     * @see TradingRepositoryInterface::getTradePicks()
     */
    public function getTradePicks(string $teamName, int $row): array
    {
        /** @var list<array<string, mixed>> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_picks WHERE teamname = ? AND row = ?",
            "si",
            $teamName,
            $row
        );
    }

    /**
     * @see TradingRepositoryInterface::updatePlayerTeam()
     */
    public function updatePlayerTeam(int $playerId, int $newTeamId): int
    {
        return $this->execute(
            "UPDATE ibl_plr SET tid = ? WHERE pid = ?",
            "ii",
            $newTeamId,
            $playerId
        );
    }

    /**
     * @see TradingRepositoryInterface::updateDraftPickOwner()
     */
    public function updateDraftPickOwner(int $year, int $pick, string $newOwner): int
    {
        return $this->execute(
            "UPDATE ibl_draft_picks SET currentteam = ? WHERE year = ? AND pick = ?",
            "sii",
            $newOwner,
            $year,
            $pick
        );
    }

    /**
     * @see TradingRepositoryInterface::playerExistsInTrade()
     */
    public function playerExistsInTrade(int $playerId): bool
    {
        $result = $this->fetchOne(
            "SELECT pid FROM ibl_trade_players WHERE pid = ?",
            "i",
            $playerId
        );

        return $result !== null;
    }

    /**
     * @see TradingRepositoryInterface::insertTradeItem()
     */
    public function insertTradeItem(int $tradeOfferId, int $itemId, TradeItemType $itemType, string $fromTeam, string $toTeam, string $approvalTeam): int
    {
        if ($_SERVER['SERVER_NAME'] === "localhost") {
            $approvalTeam = 'test';
        }

        return $this->execute(
            "INSERT INTO ibl_trade_info (tradeofferid, itemid, itemtype, trade_from, trade_to, approval) VALUES (?, ?, ?, ?, ?, ?)",
            "iissss",
            $tradeOfferId,
            $itemId,
            $itemType->value,
            $fromTeam,
            $toTeam,
            $approvalTeam
        );
    }

    /**
     * @see TradingRepositoryInterface::getTradesByOfferId()
     */
    public function getTradesByOfferId(int $offerId): array
    {
        /** @var list<TradeInfoRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info WHERE tradeofferid = ?",
            "i",
            $offerId
        );
    }

    /**
     * @see TradingRepositoryInterface::getTradesByOfferIdForUpdate()
     */
    public function getTradesByOfferIdForUpdate(int $offerId): array
    {
        /** @var list<TradeInfoRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info WHERE tradeofferid = ? FOR UPDATE",
            "i",
            $offerId
        );
    }

    /**
     * @see TradingRepositoryInterface::getDraftPickById()
     */
    public function getDraftPickById(int $pickId): ?array
    {
        /** @var DraftPickRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_draft_picks WHERE pickid = ?",
            "i",
            $pickId
        );
    }

    /**
     * @see TradingRepositoryInterface::getPlayerById()
     */
    public function getPlayerById(int $playerId): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ?",
            "i",
            $playerId
        );
    }

    /**
     * @see TradingRepositoryInterface::getPlayersByIds()
     *
     * @return array<int, PlayerRow>
     */
    public function getPlayersByIds(array $playerIds): array
    {
        if ($playerIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        $types = str_repeat('i', count($playerIds));

        /** @var list<PlayerRow> $rows */
        $rows = $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE pid IN ({$placeholders})",
            $types,
            ...$playerIds
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['pid']] = $row;
        }

        return $result;
    }

    /**
     * @see TradingRepositoryInterface::getDraftPicksByIds()
     *
     * @return array<int, DraftPickRow>
     */
    public function getDraftPicksByIds(array $pickIds): array
    {
        if ($pickIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pickIds), '?'));
        $types = str_repeat('i', count($pickIds));

        /** @var list<DraftPickRow> $rows */
        $rows = $this->fetchAll(
            "SELECT * FROM ibl_draft_picks WHERE pickid IN ({$placeholders})",
            $types,
            ...$pickIds
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['pickid']] = $row;
        }

        return $result;
    }

    /**
     * @see TradingRepositoryInterface::playerIdExists()
     */
    public function playerIdExists(int $playerId): bool
    {
        $result = $this->fetchOne(
            "SELECT 1 FROM ibl_plr WHERE pid = ? LIMIT 1",
            "i",
            $playerId
        );
        return $result !== null;
    }

    /**
     * @see TradingRepositoryInterface::updateDraftPickOwnerById()
     */
    public function updateDraftPickOwnerById(int $pickId, string $newOwner, int $newOwnerId): int
    {
        return $this->execute(
            "UPDATE ibl_draft_picks SET ownerofpick = ?, owner_tid = ? WHERE pickid = ?",
            "sii",
            $newOwner,
            $newOwnerId,
            $pickId
        );
    }

    /**
     * @see TradingRepositoryInterface::deleteTradeInfoByOfferId()
     */
    public function deleteTradeInfoByOfferId(int $offerId): int
    {
        return $this->execute(
            "DELETE FROM ibl_trade_info WHERE tradeofferid = ?",
            "i",
            $offerId
        );
    }

    /**
     * @see TradingRepositoryInterface::markTradeInfoCompleted()
     */
    public function markTradeInfoCompleted(int $offerId): int
    {
        return $this->execute(
            "UPDATE ibl_trade_info SET approval = 'completed' WHERE tradeofferid = ?",
            "i",
            $offerId
        );
    }

    /**
     * @see TradingRepositoryInterface::getLastInsertId()
     */
    public function getLastInsertId(): int
    {
        /** @var \mysqli $db */
        $db = $this->db;
        $insertId = $db->insert_id;
        return is_int($insertId) ? $insertId : (int) $insertId;
    }

    /**
     * Generate the next trade offer ID using AUTO_INCREMENT
     *
     * Inserts a row into ibl_trade_offers and returns the generated ID.
     * This is atomic and race-condition-free unlike the previous read-then-increment pattern.
     *
     * @return int New trade offer ID
     * @throws \RuntimeException If ID generation fails
     */
    public function generateNextTradeOfferId(): int
    {
        $this->execute("INSERT INTO ibl_trade_offers () VALUES ()");
        /** @var array{id: int}|null $row */
        $row = $this->fetchOne("SELECT LAST_INSERT_ID() AS id");
        if ($row === null || $row['id'] === 0) {
            throw new \RuntimeException('Failed to generate trade offer ID');
        }
        return $row['id'];
    }

    /**
     * @see TradingRepositoryInterface::getTeamPlayerCount()
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

    /**
     * Get all teams with city, name, colors and ID for trading UI
     *
     * @return list<TeamWithCityRow> Team rows ordered by city
     */
    public function getAllTeamsWithCity(): array
    {
        /** @var list<TeamWithCityRow> */
        return $this->fetchAllRealTeams('team_city ASC');
    }

    /**
     * @see TradingRepositoryInterface::getTeamPlayersForTrading()
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
     * @see TradingRepositoryInterface::getTeamDraftPicksForTrading()
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
     * @see TradingRepositoryInterface::getAllTradeOffers()
     */
    public function getAllTradeOffers(): array
    {
        /** @var list<TradeInfoRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info WHERE approval != 'completed' ORDER BY tradeofferid ASC"
        );
    }

    /**
     * @see TradingRepositoryInterface::deleteTradeOffer()
     */
    public function deleteTradeOffer(int $offerId): void
    {
        $this->db->begin_transaction();
        try {
            $this->deleteTradeInfoByOfferId($offerId);
            $this->cashRepository->deleteTradeCashByOfferId($offerId);
            $this->deleteTradeOfferById($offerId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * @see TradingRepositoryInterface::deleteTradeOfferById()
     */
    public function deleteTradeOfferById(int $offerId): int
    {
        return $this->execute(
            "DELETE FROM ibl_trade_offers WHERE id = ?",
            "i",
            $offerId
        );
    }
}
