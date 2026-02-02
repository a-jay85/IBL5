<?php

declare(strict_types=1);

namespace ContactList;

use ContactList\Contracts\ContactListRepositoryInterface;

/**
 * ContactListRepository - Data access layer for GM contact information
 *
 * Retrieves team and GM contact info from the ibl_team_info table.
 *
 * @see ContactListRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class ContactListRepository extends \BaseMysqliRepository implements ContactListRepositoryInterface
{
    /**
     * @see ContactListRepositoryInterface::getAllTeamContacts()
     */
    public function getAllTeamContacts(): array
    {
        $query = "SELECT teamid, team_city, team_name, color1, color2,
                         owner_name, owner_email, skype, aim
            FROM ibl_team_info
            WHERE teamid > 0
            ORDER BY team_city ASC";

        return $this->fetchAll($query);
    }
}
