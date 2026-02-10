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
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type TradeValidationRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TeamNameRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TeamWithCityRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradeInfoRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradeCashRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type DraftPickRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradingDraftPickRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type CashTransactionData from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type CashPlayerData from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradeAutocounterRow from \Trading\Contracts\TradingRepositoryInterface
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
            "SELECT team_name FROM ibl_team_info ORDER BY team_name"
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
     * @see TradingRepositoryInterface::getCashDetails()
     */
    public function getCashDetails(string $teamName, int $row): ?array
    {
        /** @var TradeCashRow|null */
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
            "siiiiiii",
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
            "siiiiiii",
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
     * @see TradingRepositoryInterface::getCashTransactionByOffer()
     */
    public function getCashTransactionByOffer(int $offerId, string $sendingTeam): ?array
    {
        /** @var TradeCashRow|null */
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
     * @see TradingRepositoryInterface::insertTradeQueue()
     */
    public function insertTradeQueue(string $operationType, array $params, string $tradeLine): int
    {
        $paramsJson = json_encode($params, JSON_THROW_ON_ERROR);
        return $this->execute(
            "INSERT INTO ibl_trade_queue (operation_type, params, tradeline) VALUES (?, ?, ?)",
            "sss",
            $operationType,
            $paramsJson,
            $tradeLine
        );
    }

    /**
     * @see TradingRepositoryInterface::getQueuedTrades()
     */
    public function getQueuedTrades(): array
    {
        /** @var list<array{id: int, operation_type: string, params: string, tradeline: string}> */
        return $this->fetchAll(
            "SELECT id, operation_type, params, tradeline FROM ibl_trade_queue ORDER BY id ASC"
        );
    }

    /**
     * @see TradingRepositoryInterface::executeQueuedPlayerTransfer()
     */
    public function executeQueuedPlayerTransfer(int $playerId, string $teamName, int $teamId): int
    {
        return $this->execute(
            "UPDATE ibl_plr SET teamname = ?, tid = ? WHERE pid = ?",
            "sii",
            $teamName,
            $teamId,
            $playerId
        );
    }

    /**
     * @see TradingRepositoryInterface::executeQueuedPickTransfer()
     */
    public function executeQueuedPickTransfer(int $pickId, string $newOwner): int
    {
        return $this->execute(
            "UPDATE ibl_draft_picks SET ownerofpick = ? WHERE pickid = ?",
            "si",
            $newOwner,
            $pickId
        );
    }

    /**
     * @see TradingRepositoryInterface::deleteQueuedTrade()
     */
    public function deleteQueuedTrade(int $queueId): int
    {
        return $this->execute(
            "DELETE FROM ibl_trade_queue WHERE id = ?",
            "i",
            $queueId
        );
    }

    /**
     * @see TradingRepositoryInterface::clearTradeQueue()
     */
    public function clearTradeQueue(): int
    {
        return $this->execute("TRUNCATE TABLE ibl_trade_queue");
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
        /** @var \mysqli $db */
        $db = $this->db;
        $insertId = $db->insert_id;
        return is_int($insertId) ? $insertId : (int) $insertId;
    }

    /**
     * Get the current trade autocounter value
     *
     * @return TradeAutocounterRow|null Row with 'counter' column, or null if no rows
     */
    public function getTradeAutocounter(): ?array
    {
        /** @var TradeAutocounterRow|null */
        return $this->fetchOne(
            "SELECT counter FROM ibl_trade_autocounter ORDER BY counter DESC LIMIT 1"
        );
    }

    /**
     * Insert a new trade autocounter value
     * 
     * @param int $counter Counter value to insert
     * @return int Number of affected rows
     */
    public function insertTradeAutocounter(int $counter): int
    {
        return $this->execute(
            "INSERT INTO ibl_trade_autocounter (counter) VALUES (?)",
            "i",
            $counter
        );
    }

    /**
     * Insert a cash player record (positive or negative cash transaction)
     *
     * @param CashPlayerData $data Associative array with keys: ordinal, pid, name, tid, teamname, exp, cy, cyt, cy1-cy6, retired
     * @return int Number of affected rows
     */
    public function insertCashPlayerRecord(array $data): int
    {
        return $this->execute(
            "INSERT INTO `ibl_plr` 
                (`ordinal`, `pid`, `name`, `tid`, `teamname`, `exp`, `cy`, `cyt`, `cy1`, `cy2`, `cy3`, `cy4`, `cy5`, `cy6`, `retired`) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "iisisissiiiiiii",
            $data['ordinal'],
            $data['pid'],
            $data['name'],
            $data['tid'],
            $data['teamname'],
            $data['exp'],
            $data['cy'],
            $data['cyt'],
            $data['cy1'],
            $data['cy2'],
            $data['cy3'],
            $data['cy4'],
            $data['cy5'],
            $data['cy6'],
            $data['retired']
        );
    }

    /**
     * Insert cash trade offer into ibl_trade_cash
     * 
     * @param int $tradeOfferId Trade offer ID
     * @param string $sendingTeam Sending team name
     * @param string $receivingTeam Receiving team name
     * @param int $cy1 Cash year 1
     * @param int $cy2 Cash year 2
     * @param int $cy3 Cash year 3
     * @param int $cy4 Cash year 4
     * @param int $cy5 Cash year 5
     * @param int $cy6 Cash year 6
     * @return int Number of affected rows
     */
    public function insertCashTradeOffer(int $tradeOfferId, string $sendingTeam, string $receivingTeam, int $cy1, int $cy2, int $cy3, int $cy4, int $cy5, int $cy6): int
    {
        return $this->execute(
            "INSERT INTO ibl_trade_cash 
                (`tradeOfferID`, `sendingTeam`, `receivingTeam`, `cy1`, `cy2`, `cy3`, `cy4`, `cy5`, `cy6`) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "issiiiiii",
            $tradeOfferId,
            $sendingTeam,
            $receivingTeam,
            $cy1,
            $cy2,
            $cy3,
            $cy4,
            $cy5,
            $cy6
        );
    }

    /**
     * @see TradingRepositoryInterface::getTeamPlayerCount()
     */
    public function getTeamPlayerCount(string $teamName): int
    {
        /** @var array{cnt: int}|null $result */
        $result = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ibl_plr WHERE teamname = ? AND retired = 0 AND ordinal < 100000 AND name NOT LIKE '|%'",
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
            "SELECT teamid, team_name, team_city, color1, color2 FROM ibl_team_info ORDER BY team_city ASC"
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
     * @see TradingRepositoryInterface::getTeamCashRecordsForSalary()
     */
    public function getTeamCashRecordsForSalary(int $teamId): array
    {
        /** @var list<TradingPlayerRow> */
        return $this->fetchAll(
            "SELECT pos, name, pid, ordinal, cy, cy1, cy2, cy3, cy4, cy5, cy6
             FROM ibl_plr
             WHERE tid = ? AND retired = 0 AND name LIKE '|%'
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
        $this->deleteTradeCashByOfferId($offerId);
    }
}
