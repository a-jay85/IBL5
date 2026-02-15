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
        return $this->fetchAll(
            "SELECT team_name FROM ibl_team_info WHERE teamid BETWEEN 1 AND ? ORDER BY team_name",
            "i",
            \League::MAX_REAL_TEAMID
        );
    }

    /**
     * @see TradingRepositoryInterface::getTradeRows()
     */
    public function getTradeRows(): array
    {
        /** @var list<TradeInfoRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info"
        );
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
    public function updatePlayerTeam(int $playerId, string $newTeamName, int $newTeamId): int
    {
        return $this->execute(
            "UPDATE ibl_plr SET teamname = ?, tid = ? WHERE pid = ?",
            "sii",
            $newTeamName,
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
    public function insertTradeItem(int $tradeOfferId, int $itemId, $itemType, string $fromTeam, string $toTeam, string $approvalTeam): int
    {
        // Determine parameter type string based on itemType type
        $typeString = is_string($itemType) ? "iissss" : "iiisss";

        if ($_SERVER['SERVER_NAME'] === "localhost") {
            $approvalTeam = 'test';
        }

        return $this->execute(
            "INSERT INTO ibl_trade_info (tradeofferid, itemid, itemtype, `from`, `to`, approval) VALUES (?, ?, ?, ?, ?, ?)",
            $typeString,
            $tradeOfferId,
            $itemId,
            $itemType,
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
    public function updateDraftPickOwnerById(int $pickId, string $newOwner): int
    {
        return $this->execute(
            "UPDATE ibl_draft_picks SET ownerofpick = ? WHERE pickid = ?",
            "si",
            $newOwner,
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
    public function getTeamPlayerCount(string $teamName): int
    {
        /** @var array{cnt: int}|null $result */
        $result = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ibl_plr WHERE teamname = ? AND retired = 0 AND ordinal <= 960 AND name NOT LIKE '|%'",
            "s",
            $teamName
        );
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
        return $this->fetchAll(
            "SELECT teamid, team_name, team_city, color1, color2 FROM ibl_team_info WHERE teamid BETWEEN 1 AND ? ORDER BY team_city ASC",
            "i",
            \League::MAX_REAL_TEAMID
        );
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
    public function getTeamDraftPicksForTrading(string $teamName): array
    {
        /** @var list<TradingDraftPickRow> */
        return $this->fetchAll(
            "SELECT dp.*, t.teamid AS teampick_id
             FROM ibl_draft_picks dp
             JOIN ibl_team_info t ON t.team_name = dp.teampick
             WHERE dp.ownerofpick = ?
             ORDER BY dp.year, dp.round ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see TradingRepositoryInterface::getAllTradeOffers()
     */
    public function getAllTradeOffers(): array
    {
        /** @var list<TradeInfoRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info ORDER BY tradeofferid ASC"
        );
    }

    /**
     * @see TradingRepositoryInterface::deleteTradeOffer()
     */
    public function deleteTradeOffer(int $offerId): void
    {
        $this->deleteTradeInfoByOfferId($offerId);
        $this->cashRepository->deleteTradeCashByOfferId($offerId);
        $this->deleteTradeOfferById($offerId);
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
