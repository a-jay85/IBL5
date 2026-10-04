<?php

declare(strict_types=1);

namespace LeagueStarters;

use League\League;
use LeagueStarters\Contracts\LeagueStartersRepositoryInterface;
use LeagueStarters\Contracts\LeagueStartersServiceInterface;
use Player\Player;
use Team\Team;

/**
 * LeagueStartersService - Business logic for league starters display
 *
 * Retrieves starting lineups for all teams using batch queries (2-3 total)
 * instead of per-team/per-position individual lookups.
 *
 * @see LeagueStartersServiceInterface For the interface contract
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
class LeagueStartersService implements LeagueStartersServiceInterface
{
    private \mysqli $db;
    private League $league;
    private LeagueStartersRepositoryInterface $repository;
    /** @var array<string, mixed>|null Cached raw row for the placeholder player */
    private ?array $placeholderRow = null;

    /**
     * @param \mysqli $db Database connection
     * @param League $league League object
     * @param LeagueStartersRepositoryInterface|null $repository Optional repository (test seam)
     */
    public function __construct(
        \mysqli $db,
        League $league,
        ?LeagueStartersRepositoryInterface $repository = null
    ) {
        $this->db = $db;
        $this->league = $league;
        $this->repository = $repository ?? new LeagueStartersRepository($db);
    }

    /**
     * @see LeagueStartersServiceInterface::getAllStartersByPosition()
     *
     * @return array<string, array<int, Player>>
     */
    public function getAllStartersByPosition(): array
    {
        $positions = \League\JSB::PLAYER_POSITIONS;

        /** @var array<string, array<int, Player>> $startersByPosition */
        $startersByPosition = [];
        foreach ($positions as $position) {
            $startersByPosition[$position] = [];
        }

        $teams = $this->league->getAllTeamsResult();
        if ($teams === []) {
            return $startersByPosition;
        }

        $starterRows = $this->repository->getAllStartersWithTeamData();

        $depthColumns = [
            'PG' => 'pg_depth',
            'SG' => 'sg_depth',
            'SF' => 'sf_depth',
            'PF' => 'pf_depth',
            'C' => 'c_depth',
        ];

        /** @var array<int, array<string, Player>> $starterMap teamid => [position => Player] */
        $starterMap = [];
        foreach ($starterRows as $row) {
            $teamid = $row['teamid'];
            if (!is_int($teamid)) {
                continue;
            }
            foreach ($depthColumns as $position => $column) {
                if (($row[$column] ?? 0) !== 1) {
                    continue;
                }
                if (isset($starterMap[$teamid][$position])) {
                    continue;
                }
                /** @var PlayerRow $row */
                $player = Player::withPlrRow($this->db, $row);
                $starterMap[$teamid][$position] = $player;
            }
        }

        foreach ($teams as $teamRow) {
            /** @var array<string, mixed> $teamRow */
            $team = Team::initialize($this->db, $teamRow);

            foreach ($positions as $position) {
                if (isset($starterMap[$team->teamid][$position])) {
                    $player = $starterMap[$team->teamid][$position];
                } else {
                    $player = $this->buildPlaceholderForTeam($team);
                }
                $startersByPosition[$position][] = $player;
            }
        }

        return $startersByPosition;
    }

    private function buildPlaceholderForTeam(Team $team): Player
    {
        if ($this->placeholderRow === null) {
            $this->placeholderRow = $this->repository->getPlaceholderRow() ?? self::blankPlaceholderRow();
        }
        $row = $this->placeholderRow;
        $row['teamid'] = $team->teamid;
        $row['teamname'] = $team->name;
        $row['color1'] = $team->color1;
        $row['color2'] = $team->color2;
        /** @var PlayerRow $row */
        return Player::withPlrRow($this->db, $row);
    }

    /**
     * Empty-slot row used when the placeholder player (pid 4040404) is absent
     * from `ibl_plr`, so a team with an unfilled starter slot still renders.
     *
     * @return array<string, mixed>
     */
    private static function blankPlaceholderRow(): array
    {
        $row = [
            'pid' => 4040404,
            'ordinal' => 0,
            'name' => '',
            'nickname' => null,
            'age' => 0,
            'teamid' => 0,
            'pos' => '',
            'htft' => null,
            'htin' => null,
            'wt' => null,
            'draftyear' => 0,
            'draftround' => 0,
            'draftpickno' => 0,
            'injured' => 0,
            'retired' => 0,
            'droptime' => 0,
        ];
        $zeroColumns = [
            'r_fga', 'r_fgp', 'r_fta', 'r_ftp', 'r_3ga', 'r_3gp', 'r_orb', 'r_drb',
            'r_ast', 'r_stl', 'r_tvr', 'r_blk', 'r_foul', 'oo', 'od', 'r_drive_off',
            'dd', 'po', 'pd', 'r_trans_off', 'td', 'clutch', 'consistency', 'talent',
            'skill', 'intangibles', 'loyalty', 'playing_time', 'winner', 'tradition',
            'security', 'exp', 'bird', 'cy', 'cyt', 'salary_yr1', 'salary_yr2',
            'salary_yr3', 'salary_yr4', 'salary_yr5', 'salary_yr6',
        ];
        foreach ($zeroColumns as $column) {
            $row[$column] = 0;
        }
        return $row;
    }
}
