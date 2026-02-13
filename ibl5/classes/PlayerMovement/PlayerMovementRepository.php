<?php

declare(strict_types=1);

namespace PlayerMovement;

use PlayerMovement\Contracts\PlayerMovementRepositoryInterface;

/**
 * PlayerMovementRepository - Database operations for player movement
 *
 * @phpstan-import-type MovementRow from PlayerMovementRepositoryInterface
 *
 * @see PlayerMovementRepositoryInterface For the interface contract
 */
class PlayerMovementRepository extends \BaseMysqliRepository implements PlayerMovementRepositoryInterface
{
    /**
     * @see PlayerMovementRepositoryInterface::getPlayerMovements()
     *
     * @return list<MovementRow>
     */
    public function getPlayerMovements(int $previousSeasonYear): array
    {
        /** @var list<MovementRow> */
        return $this->fetchAll(
            "SELECT
                a.pid,
                a.name,
                a.teamid AS old_teamid,
                a.team AS old_team,
                b.tid AS new_teamid,
                b.teamname AS new_team,
                old_hist.team_city AS old_city,
                old_hist.color1 AS old_color1,
                old_hist.color2 AS old_color2,
                new_info.team_city AS new_city,
                new_info.color1 AS new_color1,
                new_info.color2 AS new_color2
            FROM ibl_hist a
            JOIN ibl_plr b ON a.pid = b.pid
            LEFT JOIN ibl_team_info old_hist ON a.teamid = old_hist.teamid
            LEFT JOIN ibl_team_info new_info ON b.tid = new_info.teamid
            WHERE a.year = ?
            AND a.teamid != b.tid
            ORDER BY b.teamname",
            'i',
            $previousSeasonYear
        );
    }
}
