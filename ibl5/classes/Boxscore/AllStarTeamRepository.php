<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\AllStarTeamRepositoryInterface;
use League\League;
use League\LeagueContext;

/**
 * AllStarTeamRepository - Data access for All-Star game team names and rosters
 *
 * Operates on the ibl_box_scores_teams rows whose visitor/home are the All-Star
 * pseudo-teams, plus the matching ibl_box_scores player rows.
 *
 * @see AllStarTeamRepositoryInterface For the interface contract
 * @see \Database\BaseMysqliRepository For base class documentation
 */
class AllStarTeamRepository extends \Database\BaseMysqliRepository implements AllStarTeamRepositoryInterface
{
    /**
     * @param \mysqli $db Active mysqli connection
     */
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see AllStarTeamRepositoryInterface::findAllStarTeamNames()
     */
    public function findAllStarTeamNames(string $date): ?array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT name FROM `ibl_box_scores_teams`
             WHERE game_date = ? AND visitor_teamid = " . League::ALL_STAR_AWAY_TEAMID . " AND home_teamid = " . League::ALL_STAR_HOME_TEAMID . "
             ORDER BY id ASC
             LIMIT 2",
            "s",
            $date
        );

        if (count($rows) < 2) {
            return null;
        }

        return [
            'awayName' => $rows[0]['name'],
            'homeName' => $rows[1]['name'],
        ];
    }

    /**
     * @see AllStarTeamRepositoryInterface::findAllStarGamesWithDefaultNames()
     */
    public function findAllStarGamesWithDefaultNames(): array
    {
        /** @var list<array{id: int, game_date: string, name: string, visitor_teamid: int, home_teamid: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT id, game_date, name, visitor_teamid, home_teamid
             FROM `ibl_box_scores_teams`
             WHERE name IN ('Team Away', 'Team Home')
               AND visitor_teamid = " . League::ALL_STAR_AWAY_TEAMID . " AND home_teamid = " . League::ALL_STAR_HOME_TEAMID . "
             ORDER BY game_date ASC, id ASC",
            ""
        );

        return $rows;
    }

    /**
     * @see AllStarTeamRepositoryInterface::getPlayersForAllStarTeam()
     */
    public function getPlayersForAllStarTeam(string $date, int $teamid): array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT COALESCE(p.name, bs.name) AS name
             FROM `ibl_box_scores` bs
             LEFT JOIN `ibl_plr` p ON bs.pid = p.pid
             WHERE bs.game_date = ? AND bs.visitor_teamid = " . League::ALL_STAR_AWAY_TEAMID . " AND bs.home_teamid = " . League::ALL_STAR_HOME_TEAMID . " AND bs.teamid = ?
             ORDER BY bs.id ASC",
            "si",
            $date,
            $teamid
        );

        $names = [];
        foreach ($rows as $row) {
            $names[] = $row['name'];
        }

        return $names;
    }

    /**
     * @see AllStarTeamRepositoryInterface::renameAllStarTeam()
     */
    public function renameAllStarTeam(int $recordId, string $newName): int
    {
        return $this->execute(
            "UPDATE `ibl_box_scores_teams` SET name = ? WHERE id = ?",
            "si",
            $newName,
            $recordId
        );
    }
}
