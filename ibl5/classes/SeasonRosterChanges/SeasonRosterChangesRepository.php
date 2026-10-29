<?php

declare(strict_types=1);

namespace SeasonRosterChanges;

use SeasonRosterChanges\Contracts\SeasonRosterChangesRepositoryInterface;

/**
 * SeasonRosterChangesRepository - Database operations for player movement
 *
 * @phpstan-import-type MovementRow from SeasonRosterChangesRepositoryInterface
 *
 * @see SeasonRosterChangesRepositoryInterface For the interface contract
 */
class SeasonRosterChangesRepository extends \Database\BaseMysqliRepository implements SeasonRosterChangesRepositoryInterface
{
    /**
     * @see SeasonRosterChangesRepositoryInterface::getSeasonRosterChanges()
     *
     * @return list<MovementRow>
     */
    public function getSeasonRosterChanges(int $previousSeasonYear): array
    {
        /** @var list<MovementRow> */
        return $this->fetchAll(
            "SELECT
                a.pid,
                a.name,
                a.teamid AS old_teamid,
                a.team AS old_team,
                b.teamid AS new_teamid,
                new_info.team_name AS new_team,
                COALESCE(old_fs.team_city, old_hist.team_city) AS old_city,
                COALESCE(NULLIF(old_eb.color1, ''), old_hist.color1) AS old_color1,
                COALESCE(NULLIF(old_eb.color2, ''), old_hist.color2) AS old_color2,
                new_info.team_city AS new_city,
                new_info.color1 AS new_color1,
                new_info.color2 AS new_color2
            FROM `ibl_hist` a
            JOIN `ibl_plr` b ON a.pid = b.pid
            LEFT JOIN `ibl_team_info` old_hist ON a.teamid = old_hist.teamid
            LEFT JOIN `ibl_franchise_seasons` old_fs
                   ON old_fs.franchise_id = a.teamid AND old_fs.season_ending_year = a.year
            LEFT JOIN `ibl_franchise_era_branding` old_eb
                   ON old_eb.franchise_id = old_fs.franchise_id
                  AND old_eb.team_city COLLATE utf8mb4_unicode_ci = old_fs.team_city
                  AND old_eb.team_name COLLATE utf8mb4_unicode_ci = old_fs.team_name
            LEFT JOIN `ibl_team_info` new_info ON b.teamid = new_info.teamid
            WHERE a.year = ?
            AND a.teamid != b.teamid
            ORDER BY new_info.team_name, a.pid ASC",
            'i',
            $previousSeasonYear
        );
    }
}
