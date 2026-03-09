<?php

declare(strict_types=1);

namespace Navigation;

use League\LeagueContext;
use Navigation\Contracts\NavigationRepositoryInterface;

/**
 * Database operations for the Navigation module.
 *
 * @phpstan-import-type NavTeamsData from NavigationConfig
 */
class NavigationRepository extends \BaseMysqliRepository implements NavigationRepositoryInterface
{
    private string $teamInfoTable;
    private string $standingsTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
        $this->standingsTable = $this->resolveTable('ibl_standings');
    }

    /** @see NavigationRepositoryInterface::resolveTeamId() */
    public function resolveTeamId(string $username): ?int
    {
        $row = $this->fetchOne(
            "SELECT teamid
             FROM {$this->teamInfoTable}
             WHERE gm_username = ?
             LIMIT 1",
            's',
            $username
        );

        if ($row === null) {
            return null;
        }

        return is_int($row['teamid']) ? $row['teamid'] : (int) (is_string($row['teamid']) ? $row['teamid'] : 0);
    }

    /**
     * @see NavigationRepositoryInterface::getTeamsData()
     *
     * @return NavTeamsData|null
     */
    public function getTeamsData(): ?array
    {
        $rows = $this->fetchAll(
            "SELECT ti.teamid, ti.team_name, ti.team_city, s.division, s.conference
             FROM {$this->teamInfoTable} ti
             JOIN {$this->standingsTable} s ON ti.team_name = s.team_name
             ORDER BY s.conference, s.division, ti.team_city",
            ''
        );

        if ($rows === []) {
            return null;
        }

        /** @var NavTeamsData $teamsData */
        $teamsData = [];
        foreach ($rows as $row) {
            $conf = is_string($row['conference']) ? $row['conference'] : '';
            $div = is_string($row['division']) ? $row['division'] : '';
            $teamsData[$conf][$div][] = [
                'teamid' => is_int($row['teamid']) ? $row['teamid'] : (int) (is_string($row['teamid']) ? $row['teamid'] : 0),
                'team_name' => is_string($row['team_name']) ? $row['team_name'] : '',
                'team_city' => is_string($row['team_city']) ? $row['team_city'] : '',
            ];
        }

        return $teamsData;
    }
}
