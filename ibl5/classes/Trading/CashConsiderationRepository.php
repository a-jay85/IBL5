<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\CashConsiderationRepositoryInterface;

/**
 * Repository for ibl_cash_considerations table.
 *
 * @see CashConsiderationRepositoryInterface For method contracts
 *
 * @phpstan-import-type CashConsiderationRow from CashConsiderationRepositoryInterface
 * @phpstan-import-type CashConsiderationInsert from CashConsiderationRepositoryInterface
 */
class CashConsiderationRepository extends BaseMysqliRepository implements CashConsiderationRepositoryInterface
{
    /**
     * @see CashConsiderationRepositoryInterface::insertCashConsideration()
     */
    public function insertCashConsideration(array $data): int
    {
        $counterpartyTid = $data['counterparty_teamid'] ?? null;
        $tradeOfferId = $data['trade_offer_id'] ?? null;

        // Build conditional SQL for nullable columns (bind_param has no NULL type)
        $counterpartySql = $counterpartyTid !== null ? '?' : 'NULL';
        $tradeOfferSql = $tradeOfferId !== null ? '?' : 'NULL';

        $sql = "INSERT INTO ibl_cash_considerations
                    (teamid, type, label, counterparty_teamid, trade_offer_id, cy, cyt, salary_yr1, salary_yr2, salary_yr3, salary_yr4, salary_yr5, salary_yr6)
                 VALUES (?, ?, ?, {$counterpartySql}, {$tradeOfferSql}, ?, ?, ?, ?, ?, ?, ?, ?)";

        $types = 'iss';
        $params = [$data['teamid'], $data['type'], $data['label']];

        if ($counterpartyTid !== null) {
            $types .= 'i';
            $params[] = $counterpartyTid;
        }
        if ($tradeOfferId !== null) {
            $types .= 'i';
            $params[] = $tradeOfferId;
        }

        $types .= 'iiiiiiii';
        $params = array_merge($params, [
            $data['cy'], $data['cyt'],
            $data['salary_yr1'], $data['salary_yr2'], $data['salary_yr3'],
            $data['salary_yr4'], $data['salary_yr5'], $data['salary_yr6'],
        ]);

        return $this->execute($sql, $types, ...$params);
    }

    /**
     * @see CashConsiderationRepositoryInterface::getTeamCashConsiderations()
     */
    public function getTeamCashConsiderations(int $teamId): array
    {
        /** @var list<CashConsiderationRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_cash_considerations WHERE teamid = ? ORDER BY label ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see CashConsiderationRepositoryInterface::getTeamBuyouts()
     */
    public function getTeamBuyouts(int $teamId): array
    {
        /** @var list<CashConsiderationRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_cash_considerations WHERE teamid = ? AND type = 'buyout' ORDER BY label ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see CashConsiderationRepositoryInterface::getTeamCashForSalary()
     */
    public function getTeamCashForSalary(int $teamId): array
    {
        /** @var list<array{cy: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int}> */
        return $this->fetchAll(
            "SELECT cy, salary_yr1, salary_yr2, salary_yr3, salary_yr4, salary_yr5, salary_yr6
             FROM ibl_cash_considerations
             WHERE teamid = ?",
            "i",
            $teamId
        );
    }

    /**
     * @see CashConsiderationRepositoryInterface::deleteExpiredCashConsiderations()
     */
    public function deleteExpiredCashConsiderations(): int
    {
        // Delete entries where no future-year obligations remain.
        // cy is the current contract year (1-indexed). An entry is expired when
        // all years AFTER the current year are zero — the current year's balance
        // (salary_yr1 when cy=1) is irrelevant since it is already being processed.
        // cy >= N means year N is current or in the past; salary_yrN = 0 means no obligation.
        return $this->execute(
            "DELETE FROM ibl_cash_considerations
             WHERE (cy >= 2 OR salary_yr2 = 0)
               AND (cy >= 3 OR salary_yr3 = 0)
               AND (cy >= 4 OR salary_yr4 = 0)
               AND (cy >= 5 OR salary_yr5 = 0)
               AND (cy >= 6 OR salary_yr6 = 0)"
        );
    }
}
