<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\TradeCashRepositoryInterface;

/**
 * TradeCashRepository - Database operations for cash transactions in trades
 *
 * Handles all cash-related database queries including cash transaction records,
 * cash player records, and cash trade offer storage.
 *
 * @see TradeCashRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 * @phpstan-import-type TradeCashRow from \Trading\Contracts\TradeCashRepositoryInterface
 * @phpstan-import-type CashTransactionData from \Trading\Contracts\TradeCashRepositoryInterface
 * @phpstan-import-type CashPlayerData from \Trading\Contracts\TradeCashRepositoryInterface
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradeCashRepositoryInterface
 */
class TradeCashRepository extends BaseMysqliRepository implements TradeCashRepositoryInterface
{
    /**
     * Constructor - inherits from BaseMysqliRepository
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see TradeCashRepositoryInterface::getCashDetails()
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
     * @see TradeCashRepositoryInterface::insertPositiveCashTransaction()
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
     * @see TradeCashRepositoryInterface::insertNegativeCashTransaction()
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
     * @see TradeCashRepositoryInterface::deleteCashTransaction()
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
     * @see TradeCashRepositoryInterface::getCashTransactionByOffer()
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
     * @see TradeCashRepositoryInterface::insertCashPlayerRecord()
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
     * @see TradeCashRepositoryInterface::insertCashTradeOffer()
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
     * @see TradeCashRepositoryInterface::getTeamCashRecordsForSalary()
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
     * @see TradeCashRepositoryInterface::clearTradeCash()
     */
    public function clearTradeCash(): int
    {
        return $this->execute("DELETE FROM ibl_trade_cash");
    }

    /**
     * @see TradeCashRepositoryInterface::deleteTradeCashByOfferId()
     */
    public function deleteTradeCashByOfferId(int $offerId): int
    {
        return $this->execute(
            "DELETE FROM ibl_trade_cash WHERE tradeOfferID = ?",
            "i",
            $offerId
        );
    }
}
