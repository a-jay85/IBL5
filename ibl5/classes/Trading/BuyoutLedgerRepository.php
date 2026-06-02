<?php

declare(strict_types=1);

namespace Trading;

use BaseMysqliRepository;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;

/**
 * Repository for ibl_cash_considerations table.
 *
 * @see BuyoutLedgerRepositoryInterface For method contracts
 *
 * @phpstan-import-type CashConsiderationRow from BuyoutLedgerRepositoryInterface
 * @phpstan-import-type CashConsiderationInsert from BuyoutLedgerRepositoryInterface
 */
class BuyoutLedgerRepository extends BaseMysqliRepository implements BuyoutLedgerRepositoryInterface
{
    /**
     * @see BuyoutLedgerRepositoryInterface::insertCashConsideration()
     */
    public function insertCashConsideration(array $data): int
    {
        $counterpartyTid = $data['counterparty_teamid'] ?? null;
        $tradeOfferId = $data['trade_offer_id'] ?? null;

        // Build conditional SQL for nullable columns (bind_param has no NULL type)
        $counterpartySql = $counterpartyTid !== null ? '?' : 'NULL';
        $tradeOfferSql = $tradeOfferId !== null ? '?' : 'NULL';

        $sql = "INSERT INTO `ibl_cash_considerations`
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
     * @see BuyoutLedgerRepositoryInterface::getTeamCashConsiderations()
     */
    public function getTeamCashConsiderations(int $teamId): array
    {
        /** @var list<CashConsiderationRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_cash_considerations` WHERE teamid = ? ORDER BY label ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see BuyoutLedgerRepositoryInterface::getTeamBuyouts()
     */
    public function getTeamBuyouts(int $teamId): array
    {
        /** @var list<CashConsiderationRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_cash_considerations` WHERE teamid = ? AND type = 'buyout' ORDER BY label ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see BuyoutLedgerRepositoryInterface::getTeamCashForSalary()
     */
    public function getTeamCashForSalary(int $teamId): array
    {
        /** @var list<array{cy: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int}> */
        return $this->fetchAll(
            "SELECT cy, salary_yr1, salary_yr2, salary_yr3, salary_yr4, salary_yr5, salary_yr6
             FROM `ibl_cash_considerations`
             WHERE teamid = ?",
            "i",
            $teamId
        );
    }

    /**
     * Look up the salary for a given contract year from a cash-consideration
     * row keyed `salary_yr1`..`salary_yr6`.
     *
     * Centralizes the duplicated `match ($cy) { 1 => $row['salary_yr1'] ... }`
     * salary-slot lookup used by the cap/cash walks (TeamQueryRepository,
     * FreeAgencyCapCalculator, and {@see self::sumCurrentSeasonSalaryFromRows()}).
     * Returns 0 for any contract year outside the 1-6 range.
     *
     * @param array<string, mixed> $row Row exposing salary_yr1..salary_yr6
     * @param int $contractYear Contract year to read (1-6)
     */
    public static function salaryForContractYear(array $row, int $contractYear): int
    {
        $value = match ($contractYear) {
            1 => $row['salary_yr1'] ?? 0,
            2 => $row['salary_yr2'] ?? 0,
            3 => $row['salary_yr3'] ?? 0,
            4 => $row['salary_yr4'] ?? 0,
            5 => $row['salary_yr5'] ?? 0,
            6 => $row['salary_yr6'] ?? 0,
            default => 0,
        };

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Sum the current-season salary across cash-consideration rows.
     *
     * For each row the contract year (`cy`) is read, advanced by one when the
     * league's contract years have already rolled over (offseason), clamped up
     * to year 1, and the matching salary slot is added. The offseason predicate
     * is caller-supplied rather than baked in because it differs by context —
     * the Playoffs phase counts as offseason for trade cap math but not for the
     * free-agency cap calculation.
     *
     * @param array<int, array<string, mixed>> $rows Cash-consideration rows (cy + salary_yr1..6)
     * @param bool $advancesContractYears Whether the current contract year has rolled over (offseason)
     */
    public static function sumCurrentSeasonSalaryFromRows(array $rows, bool $advancesContractYears): int
    {
        $total = 0;
        foreach ($rows as $row) {
            $cyRaw = $row['cy'] ?? 1;
            $cy = is_numeric($cyRaw) ? (int) $cyRaw : 1;
            if ($advancesContractYears) {
                $cy++;
            }
            if ($cy === 0) {
                $cy = 1;
            }
            $total += self::salaryForContractYear($row, $cy);
        }

        return $total;
    }

    /**
     * @see BuyoutLedgerRepositoryInterface::deleteExpiredCashConsiderations()
     */
    public function deleteExpiredCashConsiderations(): int
    {
        // Delete entries where no future-year obligations remain.
        // cy is the current contract year (1-indexed). An entry is expired when
        // all years AFTER the current year are zero — the current year's balance
        // (salary_yr1 when cy=1) is irrelevant since it is already being processed.
        // cy >= N means year N is current or in the past; salary_yrN = 0 means no obligation.
        return $this->execute(
            "DELETE FROM `ibl_cash_considerations`
             WHERE (cy >= 2 OR salary_yr2 = 0)
               AND (cy >= 3 OR salary_yr3 = 0)
               AND (cy >= 4 OR salary_yr4 = 0)
               AND (cy >= 5 OR salary_yr5 = 0)
               AND (cy >= 6 OR salary_yr6 = 0)"
        );
    }
}
