<?php

declare(strict_types=1);

namespace JsbParser\Repositories;

use League\LeagueContext;

class JsbLookupRepository extends \BaseMysqliRepository
{
    /** @var array<int, string> */
    public const JSB_TEAM_NAMES = [
        0 => 'Free Agents',
        1 => 'Celtics',
        2 => 'Heat',
        3 => 'Knicks',
        4 => 'Nets',
        5 => 'Magic',
        6 => 'Bucks',
        7 => 'Bulls',
        8 => 'Pelicans',
        9 => 'Hawks',
        10 => 'Hornets',
        11 => 'Pacers',
        12 => 'Raptors',
        13 => 'Jazz',
        14 => 'Timberwolves',
        15 => 'Nuggets',
        16 => 'Thunder',
        17 => 'Spurs',
        18 => 'Trailblazers',
        19 => 'Clippers',
        20 => 'Grizzlies',
        21 => 'Lakers',
        22 => 'Supersonics',
        23 => 'Suns',
        24 => 'Warriors',
        25 => 'Pistons',
        26 => 'Kings',
        27 => 'Bullets',
        28 => 'Mavericks',
    ];

    /** @var array<string, string> */
    public const TEAM_NAME_ALIASES = [
        'Hornets' => 'Sting',
        'Thunder' => 'Aces',
        'Spurs' => 'Rockets',
        'Supersonics' => 'Braves',
    ];

    /** @var array<string, int|null> */
    private array $teamIdCache = [];

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    public function resolveTeamIdByName(string $teamName): ?int
    {
        if (array_key_exists($teamName, $this->teamIdCache)) {
            return $this->teamIdCache[$teamName];
        }

        $row = $this->fetchOne(
            "SELECT teamid FROM `ibl_team_info` WHERE team_name = ? LIMIT 1",
            's',
            $teamName
        );

        if ($row !== null) {
            /** @var int $teamId */
            $teamId = $row['teamid'];
            $this->teamIdCache[$teamName] = $teamId;
            return $teamId;
        }

        if (isset(self::TEAM_NAME_ALIASES[$teamName])) {
            $aliasName = self::TEAM_NAME_ALIASES[$teamName];
            $row = $this->fetchOne(
                "SELECT teamid FROM `ibl_team_info` WHERE team_name = ? LIMIT 1",
                's',
                $aliasName
            );

            if ($row !== null) {
                /** @var int $teamId */
                $teamId = $row['teamid'];
                $this->teamIdCache[$teamName] = $teamId;
                return $teamId;
            }
        }

        $row = $this->fetchOne(
            "SELECT teamid FROM `ibl_hist` WHERE team = ? AND teamid > 0 LIMIT 1",
            's',
            $teamName
        );

        if ($row !== null) {
            /** @var int $teamId */
            $teamId = $row['teamid'];
            $this->teamIdCache[$teamName] = $teamId;
            return $teamId;
        }

        $this->teamIdCache[$teamName] = null;
        return null;
    }

    public function getPlayerName(int $pid): ?string
    {
        $row = $this->fetchOne(
            "SELECT name FROM `ibl_plr` WHERE pid = ? LIMIT 1",
            'i',
            $pid
        );

        if ($row !== null) {
            return is_string($row['name']) ? $row['name'] : null;
        }

        return null;
    }
}
