<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\TradeCashRepositoryInterface;

/**
 * TradeCashRepository - Database operations for cash transactions in trades
 *
 * Handles all cash-related database queries including cash transaction records
 * and cash trade offer storage.
 *
 * @see TradeCashRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 * @phpstan-import-type TradeCashRow from \Trading\Contracts\TradeCashRepositoryInterface
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
     * @see TradeCashRepositoryInterface::insertCashTradeOffer()
     */
    public function insertCashTradeOffer(int $tradeOfferId, string $sendingTeam, string $receivingTeam, int $salaryYr1, int $salaryYr2, int $salaryYr3, int $salaryYr4, int $salaryYr5, int $salaryYr6): int
    {
        return $this->execute(
            "INSERT INTO ibl_trade_cash
                (`tradeOfferID`, `sendingTeam`, `receivingTeam`, `salary_yr1`, `salary_yr2`, `salary_yr3`, `salary_yr4`, `salary_yr5`, `salary_yr6`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "issiiiiii",
            $tradeOfferId,
            $sendingTeam,
            $receivingTeam,
            $salaryYr1,
            $salaryYr2,
            $salaryYr3,
            $salaryYr4,
            $salaryYr5,
            $salaryYr6
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
     * @see TradeCashRepositoryInterface::getCashTransactionsByOfferIds()
     *
     * @return array<string, TradeCashRow>
     */
    public function getCashTransactionsByOfferIds(array $offerIds): array
    {
        if ($offerIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($offerIds), '?'));
        $types = str_repeat('i', count($offerIds));

        /** @var list<TradeCashRow> $rows */
        $rows = $this->fetchAll(
            "SELECT * FROM ibl_trade_cash WHERE tradeOfferID IN ({$placeholders})",
            $types,
            ...$offerIds
        );

        $result = [];
        foreach ($rows as $row) {
            $key = $row['tradeOfferID'] . ':' . $row['sendingTeam'];
            $result[$key] = $row;
        }

        return $result;
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
