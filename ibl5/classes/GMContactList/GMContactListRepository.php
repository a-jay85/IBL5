<?php

declare(strict_types=1);

namespace GMContactList;

use GMContactList\Contracts\GMContactListRepositoryInterface;

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
        $query = "SELECT teamid, team_city, team_name, color1, color2,
                         owner_name, owner_email, skype, aim
            FROM ibl_team_info
            WHERE teamid > 0
            ORDER BY team_city ASC";

        /** @var array<int, array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, owner_name: string, owner_email: string, skype: string, aim: string}> */
        return $this->fetchAll($query);
    }
}
