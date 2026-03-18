<?php

declare(strict_types=1);

namespace TransactionHistory;

use TransactionHistory\Contracts\TransactionHistoryRepositoryInterface;

/**
 * Repository for querying transaction history from nuke_stories.
 *
 * @phpstan-import-type TransactionRow from \TransactionHistory\Contracts\TransactionHistoryViewInterface
 *
 * @see TransactionHistoryRepositoryInterface
 */
class TransactionHistoryRepository extends \BaseMysqliRepository implements TransactionHistoryRepositoryInterface
{
    /** @var string Comma-separated transaction category IDs */
    private const CATEGORY_IDS = '1, 2, 3, 8, 10, 14';

    /**
     * @see TransactionHistoryRepositoryInterface::getAvailableYears()
     */
    public function getAvailableYears(): array
    {
        $rows = $this->fetchAll(
            "SELECT DISTINCT YEAR(time) AS year FROM nuke_stories WHERE catid IN (" . self::CATEGORY_IDS . ") ORDER BY year DESC",
            ""
        );

        $years = [];
        foreach ($rows as $row) {
            /** @var int|string $yearValue */
            $yearValue = $row['year'];
            $years[] = (int) $yearValue;
        }

        return $years;
    }

    /**
     * @see TransactionHistoryRepositoryInterface::getTransactions()
     */
    public function getTransactions(?int $categoryId, ?int $year, ?int $month): array
    {
        $where = new \Services\QueryConditions(["catid IN (" . self::CATEGORY_IDS . ")"]);
        $where->addIfNotNull('catid = ?', 'i', $categoryId);

        // Use sargable date range instead of YEAR()/MONTH() to allow index usage
        if ($year !== null && $month !== null) {
            $startDate = sprintf('%04d-%02d-01 00:00:00', $year, $month);
            if ($month === 12) {
                $endDate = sprintf('%04d-01-01 00:00:00', $year + 1);
            } else {
                $endDate = sprintf('%04d-%02d-01 00:00:00', $year, $month + 1);
            }
            $where->add('time >= ?', 's', $startDate);
            $where->add('time < ?', 's', $endDate);
        } elseif ($year !== null) {
            $startDate = sprintf('%04d-01-01 00:00:00', $year);
            $endDate = sprintf('%04d-01-01 00:00:00', $year + 1);
            $where->add('time >= ?', 's', $startDate);
            $where->add('time < ?', 's', $endDate);
        } elseif ($month !== null) {
            $where->add('MONTH(time) = ?', 'i', $month);
        }

        $query = "SELECT sid, catid, title, time FROM nuke_stories WHERE {$where->toWhereClause()} ORDER BY time DESC LIMIT 500";

        /** @var array<int, array{sid: string, catid: string, title: string, time: string}> */
        return $this->fetchAll($query, $where->getTypes(), ...$where->getParams());
    }
}
