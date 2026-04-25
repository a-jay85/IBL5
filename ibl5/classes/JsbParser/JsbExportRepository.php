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
            'SELECT pid, name, teamid,
                    COALESCE(bird, 0) AS bird,
                    COALESCE(cy, 0) AS cy,
                    COALESCE(cyt, 0) AS cyt,
                    COALESCE(salary_yr1, 0) AS salary_yr1,
                    COALESCE(salary_yr2, 0) AS salary_yr2,
                    COALESCE(salary_yr3, 0) AS salary_yr3,
                    COALESCE(salary_yr4, 0) AS salary_yr4,
                    COALESCE(salary_yr5, 0) AS salary_yr5,
                    COALESCE(salary_yr6, 0) AS salary_yr6,
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
                'teamid' => is_int($row['teamid']) ? $row['teamid'] : 0,
                'bird' => is_int($row['bird']) ? $row['bird'] : 0,
                'cy' => is_int($row['cy']) ? $row['cy'] : 0,
                'cyt' => is_int($row['cyt']) ? $row['cyt'] : 0,
                'salary_yr1' => is_int($row['salary_yr1']) ? $row['salary_yr1'] : 0,
                'salary_yr2' => is_int($row['salary_yr2']) ? $row['salary_yr2'] : 0,
                'salary_yr3' => is_int($row['salary_yr3']) ? $row['salary_yr3'] : 0,
                'salary_yr4' => is_int($row['salary_yr4']) ? $row['salary_yr4'] : 0,
                'salary_yr5' => is_int($row['salary_yr5']) ? $row['salary_yr5'] : 0,
                'salary_yr6' => is_int($row['salary_yr6']) ? $row['salary_yr6'] : 0,
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
            'SELECT tradeofferid, itemid, itemtype,
                    trade_from, trade_to, created_at
             FROM ibl_trade_info
             WHERE approval = ? AND created_at >= ?
             ORDER BY tradeofferid, id',
            'ss',
            'completed',
            $seasonStartDate,
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'tradeofferid' => is_int($row['tradeofferid']) ? $row['tradeofferid'] : 0,
                'itemid' => is_int($row['itemid']) ? $row['itemid'] : 0,
                'itemtype' => is_string($row['itemtype']) ? $row['itemtype'] : '',
                'trade_from' => is_string($row['trade_from']) ? $row['trade_from'] : '',
                'trade_to' => is_string($row['trade_to']) ? $row['trade_to'] : '',
                'created_at' => is_string($row['created_at']) ? $row['created_at'] : '',
            ];
        }

        return $result;
    }
}
