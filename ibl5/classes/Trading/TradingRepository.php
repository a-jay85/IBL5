<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\TradingRepositoryInterface;

/**
 * TradingRepository - Database operations for trading system
 * 
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Centralizes all database queries for the Trading module.
 * 
 * @see TradingRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 */
class TradingRepository extends BaseMysqliRepository implements TradingRepositoryInterface
{
    /**
     * Constructor - inherits from BaseMysqliRepository
     * 
     * @param object $db Active mysqli connection (or duck-typed mock during migration)
     * @throws \RuntimeException If connection is invalid (error code 1002)
     * 
     * TEMPORARY: Accepts duck-typed objects during mysqli migration for testing.
     * Will be strictly \mysqli once migration completes.
     */
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see TradingRepositoryInterface::getPlayerForTradeValidation()
     */
    public function getPlayerForTradeValidation(int $playerId): ?array
    {
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
        return $this->fetchAll(
            "SELECT team_name FROM ibl_team_info ORDER BY team_name"
        );
    }

    /**
     * @see TradingRepositoryInterface::getTradeRows()
     */
    public function getTradeRows(): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info"
        );
    }

    /**
     * @see TradingRepositoryInterface::getCashDetails()
     */
    public function getCashDetails(string $teamName, int $row): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_trade_cash WHERE teamname = ? AND row = ?",
            "si",
            $teamName,
            $row
        );
    }

    /**
     * @see TradingRepositoryInterface::getTradePlayers()
     */
    public function getTradePlayers(string $teamName, int $row): array
    {
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
     * @see TradingRepositoryInterface::clearTradeInfo()
     */
    public function clearTradeInfo(): int
    {
        return $this->execute("DELETE FROM ibl_trade_info");
    }

    /**
     * @see TradingRepositoryInterface::clearTradeCash()
     */
    public function clearTradeCash(): int
    {
        return $this->execute("DELETE FROM ibl_trade_cash");
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
     * @see TradingRepositoryInterface::insertPositiveCashTransaction()
     */
    public function insertPositiveCashTransaction(array $data): int
    {
        return $this->execute(
            "INSERT INTO ibl_trade_cash (teamname, year1, year2, year3, year4, year5, year6, row) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            "siiiiii",
            $data['teamname'],
            $data['year1'],
            $data['year2'],
            $data['year3'],
            $data['year4'],
            $data['year5'],
            $data['year6'],
            $data['row']
        );
    }

    /**
     * @see TradingRepositoryInterface::insertNegativeCashTransaction()
     */
    public function insertNegativeCashTransaction(array $data): int
    {
        return $this->execute(
            "INSERT INTO ibl_trade_cash (teamname, year1, year2, year3, year4, year5, year6, row) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            "siiiiii",
            $data['teamname'],
            -$data['year1'],
            -$data['year2'],
            -$data['year3'],
            -$data['year4'],
            -$data['year5'],
            -$data['year6'],
            $data['row']
        );
    }

    /**
     * @see TradingRepositoryInterface::deleteCashTransaction()
     */
    public function deleteCashTransaction(string $teamName, int $row): int
    {
        return $this->execute(
            "DELETE FROM ibl_trade_cash WHERE teamname = ? AND row = ?",
            "si",
            $teamName,
            $row
        );
    }

    /**
     * @see TradingRepositoryInterface::insertTradeItem()
     */
    public function insertTradeItem(int $tradeOfferId, int $itemId, $itemType, string $fromTeam, string $toTeam, string $approvalTeam): int
    {
        // Determine parameter type string based on itemType type
        $typeString = is_string($itemType) ? "isssss" : "isssss";
        
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
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info WHERE tradeofferid = ?",
            "i",
            $offerId
        );
    }

    /**
     * @see TradingRepositoryInterface::getCashTransactionByOffer()
     */
    public function getCashTransactionByOffer(int $offerId, string $sendingTeam): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_trade_cash WHERE tradeOfferID = ? AND sendingTeam = ?",
            "is",
            $offerId,
            $sendingTeam
        );
    }

    /**
     * @see TradingRepositoryInterface::getDraftPickById()
     */
    public function getDraftPickById(int $pickId): ?array
    {
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
     * @see TradingRepositoryInterface::insertTradeQueue()
     */
    public function insertTradeQueue(string $query, string $tradeLine): int
    {
        return $this->execute(
            "INSERT INTO ibl_trade_queue (query, tradeline) VALUES (?, ?)",
            "ss",
            $query,
            $tradeLine
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
     * @see TradingRepositoryInterface::deleteTradeCashByOfferId()
     */
    public function deleteTradeCashByOfferId(int $offerId): int
    {
        return $this->execute(
            "DELETE FROM ibl_trade_cash WHERE tradeOfferID = ?",
            "i",
            $offerId
        );
    }

    /**
     * @see TradingRepositoryInterface::getLastInsertId()
     */
    public function getLastInsertId(): int
    {
        return $this->db->insert_id;
    }
}
