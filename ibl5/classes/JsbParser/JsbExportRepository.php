<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\JsbExportRepositoryInterface;

/**
 * Repository for database queries needed for JSB file export.
 *
 * Gathers player data and transaction data from the database for writing
 * back to .plr and .trn files.
 */
class JsbExportRepository extends \BaseMysqliRepository implements JsbExportRepositoryInterface
{
    /**
     * @see JsbExportRepositoryInterface::getAllPlayerChangeableFields()
     */
    public function getAllPlayerChangeableFields(): array
    {
        $rows = $this->fetchAll(
            'SELECT pid, name, tid,
                    COALESCE(bird, 0) AS bird,
                    COALESCE(cy, 0) AS cy,
                    COALESCE(cyt, 0) AS cyt,
                    COALESCE(cy1, 0) AS cy1,
                    COALESCE(cy2, 0) AS cy2,
                    COALESCE(cy3, 0) AS cy3,
                    COALESCE(cy4, 0) AS cy4,
                    COALESCE(cy5, 0) AS cy5,
                    COALESCE(cy6, 0) AS cy6,
                    COALESCE(fa_signing_flag, 0) AS fa_signing_flag
             FROM ibl_plr
             WHERE ordinal <= 1440 AND pid <> 0
             ORDER BY pid',
        );

        $result = [];
        foreach ($rows as $row) {
            $pid = is_int($row['pid']) ? $row['pid'] : 0;
            $result[$pid] = [
                'pid' => $pid,
                'name' => is_string($row['name']) ? $row['name'] : '',
                'tid' => is_int($row['tid']) ? $row['tid'] : 0,
                'bird' => is_int($row['bird']) ? $row['bird'] : 0,
                'cy' => is_int($row['cy']) ? $row['cy'] : 0,
                'cyt' => is_int($row['cyt']) ? $row['cyt'] : 0,
                'cy1' => is_int($row['cy1']) ? $row['cy1'] : 0,
                'cy2' => is_int($row['cy2']) ? $row['cy2'] : 0,
                'cy3' => is_int($row['cy3']) ? $row['cy3'] : 0,
                'cy4' => is_int($row['cy4']) ? $row['cy4'] : 0,
                'cy5' => is_int($row['cy5']) ? $row['cy5'] : 0,
                'cy6' => is_int($row['cy6']) ? $row['cy6'] : 0,
                'fa_signing_flag' => is_int($row['fa_signing_flag']) ? $row['fa_signing_flag'] : 0,
            ];
        }

        return $result;
    }

    /**
     * @see JsbExportRepositoryInterface::getCompletedTradeItems()
     */
    public function getCompletedTradeItems(string $seasonStartDate): array
    {
        $rows = $this->fetchAll(
            'SELECT ti.tradeofferid, ti.itemid, ti.itemtype,
                    ti.`from`, ti.`to`, ti.created_at
             FROM ibl_trade_info ti
             INNER JOIN ibl_trade_offers to2 ON ti.tradeofferid = to2.id
             WHERE ti.approval = ? AND ti.created_at >= ?
             ORDER BY ti.tradeofferid, ti.id',
            'ss',
            'approved',
            $seasonStartDate,
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'tradeofferid' => is_int($row['tradeofferid']) ? $row['tradeofferid'] : 0,
                'itemid' => is_int($row['itemid']) ? $row['itemid'] : 0,
                'itemtype' => is_string($row['itemtype']) ? $row['itemtype'] : '',
                'from' => is_string($row['from']) ? $row['from'] : '',
                'to' => is_string($row['to']) ? $row['to'] : '',
                'created_at' => is_string($row['created_at']) ? $row['created_at'] : '',
            ];
        }

        return $result;
    }
}
