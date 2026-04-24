<?php

declare(strict_types=1);

namespace GMContactList;

use GMContactList\Contracts\GMContactListRepositoryInterface;
use League\League;

/**
 * GMContactListRepository - Data access layer for GM contact information
 *
 * Retrieves team and GM contact info from the ibl_team_info table.
 *
 * @see GMContactListRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class GMContactListRepository extends \BaseMysqliRepository implements GMContactListRepositoryInterface
{
    /**
     * @see GMContactListRepositoryInterface::getAllTeamContacts()
     */
    public function getAllTeamContacts(): array
    {
        $query = "SELECT ti.teamid, ti.team_city, ti.team_name, ti.color1, ti.color2,
                         ti.owner_name, ti.discord_id
            FROM ibl_team_info ti
            WHERE ti.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ORDER BY ti.team_city ASC";

        /** @var array<int, array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, owner_name: string, discord_id: int|null}> */
        return $this->fetchAll($query);
    }
}
