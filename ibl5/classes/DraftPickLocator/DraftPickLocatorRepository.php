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
             WHERE teamid != ?
             ORDER BY teamid ASC",
            "i",
            \League::FREE_AGENTS_TEAMID
        );
    }

    /**
     * @see DraftPickLocatorRepositoryInterface::getDraftPicksForTeam()
     *
     * @return list<array{ownerofpick: string, year: int, round: int}>
     */
    public function getDraftPicksForTeam(string $teamName): array
    {
        /** @var list<array{ownerofpick: string, year: int, round: int}> */
        return $this->fetchAll(
            "SELECT ownerofpick, year, round
             FROM ibl_draft_picks
             WHERE teampick = ?
             ORDER BY year, round ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see DraftPickLocatorRepositoryInterface::getAllDraftPicksGroupedByTeam()
     *
     * @return array<string, list<array{ownerofpick: string, year: int, round: int}>>
     */
    public function getAllDraftPicksGroupedByTeam(): array
    {
        /** @var list<array{teampick: string, ownerofpick: string, year: int, round: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT teampick, ownerofpick, year, round
             FROM ibl_draft_picks
             ORDER BY teampick, year, round ASC"
        );

        /** @var array<string, list<array{ownerofpick: string, year: int, round: int}>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $teamPick = $row['teampick'];
            $grouped[$teamPick][] = [
                'ownerofpick' => $row['ownerofpick'],
                'year' => $row['year'],
                'round' => $row['round'],
            ];
        }

        return $grouped;
    }
}
