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
     *
     * @return list<array{teamid: int, team_city: string, team_name: string, color1: string, color2: string}>
     */
    public function getAllTeams(): array
    {
        /** @var list<array{teamid: int, team_city: string, team_name: string, color1: string, color2: string}> */
        return $this->fetchAll(
            "SELECT teamid, team_city, team_name, color1, color2
             FROM ibl_team_info
             WHERE teamid BETWEEN 1 AND ?
             ORDER BY teamid ASC",
            "i",
            \League::MAX_REAL_TEAMID
        );
    }

    /**
     * @see DraftPickLocatorRepositoryInterface::getDraftPicksForTeam()
     *
     * @return list<array{ownerofpick: string, year: int, round: int}>
     */
    public function getDraftPicksForTeam(int $teamId): array
    {
        /** @var list<array{ownerofpick: string, year: int, round: int}> */
        return $this->fetchAll(
            "SELECT ownerofpick, year, round
             FROM ibl_draft_picks
             WHERE teampick_tid = ?
             ORDER BY year, round ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see DraftPickLocatorRepositoryInterface::getAllDraftPicksGroupedByTeam()
     *
     * @return array<int, list<array{ownerofpick: string, year: int, round: int}>>
     */
    public function getAllDraftPicksGroupedByTeam(): array
    {
        /** @var list<array{teampick_tid: int, ownerofpick: string, year: int, round: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT teampick_tid, ownerofpick, year, round
             FROM ibl_draft_picks
             ORDER BY teampick_tid, year, round ASC"
        );

        /** @var array<int, list<array{ownerofpick: string, year: int, round: int}>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $teamId = $row['teampick_tid'];
            $grouped[$teamId][] = [
                'ownerofpick' => $row['ownerofpick'],
                'year' => $row['year'],
                'round' => $row['round'],
            ];
        }

        return $grouped;
    }
}
