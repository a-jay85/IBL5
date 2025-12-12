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
     * Get player data for trade validation
     * 
     * @param int $playerId Player ID
     * @return array|null Player data with 'ordinal' and 'cy' fields, or null if not found
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
     * Get all teams for UI display
     * 
     * @return array<array> List of teams with 'team_name' field
     */
    public function getAllTeams(): array
    {
        return $this->fetchAll(
            "SELECT team_name FROM ibl_team_info ORDER BY team_name"
        );
    }

    /**
     * Get trade rows from trade info table
     * 
     * @return array<array> Trade rows
     */
    public function getTradeRows(): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_trade_info"
        );
    }

    /**
     * Get cash transaction details for a specific team and row
     * 
     * @param string $teamName Team name
     * @param int $row Row number
     * @return array|null Cash details or null if not found
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
     * Get players involved in a trade
     * 
     * @param string $teamName Team name
     * @param int $row Row number
     * @return array<array> Player data
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
     * Get draft picks involved in a trade
     * 
     * @param string $teamName Team name
     * @param int $row Row number
     * @return array<array> Draft pick data
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
     * Update player's team after trade
     * 
     * @param int $playerId Player ID
     * @param string $newTeamName New team name
     * @param int $newTeamId New team ID
     * @return int Number of rows affected
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
     * Update draft pick ownership after trade
     * 
     * @param int $year Draft year
     * @param int $pick Draft pick number
     * @param string $newOwner New owner team name
     * @return int Number of rows affected
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
     * Clear all trade info
     * 
     * @return int Number of rows affected
     */
    public function clearTradeInfo(): int
    {
        return $this->execute("DELETE FROM ibl_trade_info");
    }

    /**
     * Clear all trade cash data
     * 
     * @return int Number of rows affected
     */
    public function clearTradeCash(): int
    {
        return $this->execute("DELETE FROM ibl_trade_cash");
    }

    /**
     * Check if a player ID exists in trade players table
     * 
     * @param int $playerId Player ID
     * @return bool True if exists
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
     * Insert positive cash transaction (team receiving cash)
     * 
     * @param array $data Cash transaction data
     * @return int Number of rows affected
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
     * Insert negative cash transaction (team sending cash)
     * 
     * @param array $data Cash transaction data
     * @return int Number of rows affected
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
     * Delete cash transaction for a team and row
     * 
     * @param string $teamName Team name
     * @param int $row Row number
     * @return int Number of rows affected
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
     * Insert a trade item (player, pick, or cash consideration)
     * 
     * @param int $tradeOfferId Trade offer ID
     * @param int $itemId Item ID (player pid, pick pickid, or composite for cash)
     * @param int|string $itemType Item type (1=player, 0=pick, 'cash'=cash)
     * @param string $fromTeam Offering team name
     * @param string $toTeam Receiving team name
     * @param string $approvalTeam Team that must approve (typically listening team)
     * @return int Number of rows affected
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
     * Get trade items by offer ID
     * 
     * @param int $offerId Trade offer ID
     * @return array<array> Trade items
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
     * Get cash transaction by offer ID and sending team
     * 
     * @param int $offerId Trade offer ID
     * @param string $sendingTeam Sending team name
     * @return array|null Cash details or null if not found
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
     * Get draft pick by pick ID
     * 
     * @param int $pickId Pick ID
     * @return array|null Pick data or null if not found
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
     * Get player by player ID
     * 
     * @param int $playerId Player ID
     * @return array|null Player data or null if not found
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
     * Check if a player ID exists in the database
     * 
     * @param int $playerId Player ID to check
     * @return bool True if player exists, false otherwise
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
     * Update draft pick owner by pick ID
     * 
     * @param int $pickId Pick ID
     * @param string $newOwner New owner team name
     * @return int Number of rows affected
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
     * Insert trade into queue for deferred execution
     * 
     * @param string $query SQL query to execute later
     * @param string $tradeLine Trade description text
     * @return int Number of rows affected
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
     * Delete trade info by offer ID
     * 
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
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
     * Delete trade cash by offer ID
     * 
     * @param int $offerId Trade offer ID
     * @return int Number of rows affected
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
     * Get the last inserted ID
     * 
     * @return int Last insert ID
     */
    public function getLastInsertId(): int
    {
        return $this->db->insert_id;
    }
}
