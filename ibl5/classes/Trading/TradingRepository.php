<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use League\LeagueContext;
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
    private ?LeagueContext $leagueContext;

    /**
     * Constructor - inherits from BaseMysqliRepository
     * 
     * @param object $db Active mysqli connection (or duck-typed mock during migration)
     * @param LeagueContext|null $leagueContext Optional league context for multi-league support
     * @throws \RuntimeException If connection is invalid (error code 1002)
     * 
     * TEMPORARY: Accepts duck-typed objects during mysqli migration for testing.
     * Will be strictly \mysqli once migration completes.
     */
    public function __construct(object $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db);
        $this->leagueContext = $leagueContext;
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
        $teamTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_team_info') : 'ibl_team_info';
        return $this->fetchAll(
            "SELECT team_name FROM {$teamTable} ORDER BY team_name"
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

    /**
     * Get the current trade autocounter value
     * 
     * @return array|null Row with 'counter' column, or null if no rows
     */
    public function getTradeAutocounter(): ?array
    {
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
     * @param array $data Associative array with keys: ordinal, pid, name, tid, teamname, exp, cy, cyt, cy1-cy6, retired
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
     * Get all teams with city and name for trading UI
     * 
     * @return array Array of team rows ordered by city
     */
    public function getAllTeamsWithCity(): array
    {
        $teamTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_team_info') : 'ibl_team_info';
        return $this->fetchAll(
            "SELECT team_name, team_city FROM {$teamTable} ORDER BY team_city ASC"
        );
    }
}
