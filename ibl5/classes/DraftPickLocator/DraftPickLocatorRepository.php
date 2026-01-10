<?php

declare(strict_types=1);

namespace DraftPickLocator;

use DraftPickLocator\Contracts\DraftPickLocatorRepositoryInterface;

/**
 * DraftPickLocatorRepository - Data access layer for draft pick locator
 *
 * Retrieves draft pick ownership data from the database.
 *
 * @see DraftPickLocatorRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class DraftPickLocatorRepository extends \BaseMysqliRepository implements DraftPickLocatorRepositoryInterface
{
    /**
     * @see DraftPickLocatorRepositoryInterface::getAllTeams()
     */
    public function getAllTeams(): array
    {
        return $this->fetchAll(
            "SELECT teamid, team_city, team_name, color1, color2 
             FROM ibl_team_info 
             WHERE teamid != ? 
             ORDER BY teamid ASC",
            "i",
            \League::FREE_AGENTS_TEAMID
        );
    }

    /**
     * @see DraftPickLocatorRepositoryInterface::getDraftPicksForTeam()
     */
    public function getDraftPicksForTeam(string $teamName): array
    {
        return $this->fetchAll(
            "SELECT ownerofpick, year, round 
             FROM ibl_draft_picks 
             WHERE teampick = ? 
             ORDER BY year, round ASC",
            "s",
            $teamName
        );
    }
}
