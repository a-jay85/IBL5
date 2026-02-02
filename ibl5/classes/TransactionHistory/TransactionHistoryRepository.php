<?php

declare(strict_types=1);

namespace TransactionHistory;

use TransactionHistory\Contracts\TransactionHistoryRepositoryInterface;

/**
 * Repository for querying transaction history from nuke_stories.
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
            $years[] = (int) $row['year'];
        }

        return $years;
    }

    /**
     * @see TransactionHistoryRepositoryInterface::getTransactions()
     */
    public function getTransactions(?int $categoryId, ?int $year, ?int $month): array
    {
        $conditions = ["catid IN (" . self::CATEGORY_IDS . ")"];
        $params = [];
        $types = '';

        if ($categoryId !== null) {
            $conditions[] = "catid = ?";
            $params[] = $categoryId;
            $types .= 'i';
        }

        if ($year !== null) {
            $conditions[] = "YEAR(time) = ?";
            $params[] = $year;
            $types .= 'i';
        }

        if ($month !== null) {
            $conditions[] = "MONTH(time) = ?";
            $params[] = $month;
            $types .= 'i';
        }

        $whereClause = implode(' AND ', $conditions);
        $query = "SELECT sid, catid, title, time FROM nuke_stories WHERE {$whereClause} ORDER BY time DESC LIMIT 500";

        return $this->fetchAll($query, $types, ...$params);
    }
}
