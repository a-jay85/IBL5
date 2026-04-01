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
        $counterpartyTid = $data['counterparty_tid'] ?? null;
        $tradeOfferId = $data['trade_offer_id'] ?? null;

        // Build conditional SQL for nullable columns (bind_param has no NULL type)
        $counterpartySql = $counterpartyTid !== null ? '?' : 'NULL';
        $tradeOfferSql = $tradeOfferId !== null ? '?' : 'NULL';

        $sql = "INSERT INTO ibl_cash_considerations
                    (tid, type, label, counterparty_tid, trade_offer_id, cy, cyt, cy1, cy2, cy3, cy4, cy5, cy6)
                 VALUES (?, ?, ?, {$counterpartySql}, {$tradeOfferSql}, ?, ?, ?, ?, ?, ?, ?, ?)";

        $types = 'iss';
        $params = [$data['tid'], $data['type'], $data['label']];

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
            $data['cy1'], $data['cy2'], $data['cy3'],
            $data['cy4'], $data['cy5'], $data['cy6'],
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
            "SELECT * FROM ibl_cash_considerations WHERE tid = ? ORDER BY label ASC",
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
            "SELECT * FROM ibl_cash_considerations WHERE tid = ? AND type = 'buyout' ORDER BY label ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see CashConsiderationRepositoryInterface::getTeamCashForSalary()
     */
    public function getTeamCashForSalary(int $teamId): array
    {
        /** @var list<array{cy: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int}> */
        return $this->fetchAll(
            "SELECT cy, cy1, cy2, cy3, cy4, cy5, cy6
             FROM ibl_cash_considerations
             WHERE tid = ?",
            "i",
            $teamId
        );
    }

    /**
     * @see CashConsiderationRepositoryInterface::deleteExpiredCashConsiderations()
     */
    public function deleteExpiredCashConsiderations(): int
    {
        return $this->execute(
            "DELETE FROM ibl_cash_considerations
             WHERE (cy >= 1 OR cy1 = 0)
               AND (cy >= 2 OR cy2 = 0)
               AND (cy >= 3 OR cy3 = 0)
               AND (cy >= 4 OR cy4 = 0)
               AND (cy >= 5 OR cy5 = 0)
               AND (cy >= 6 OR cy6 = 0)"
        );
    }
}
