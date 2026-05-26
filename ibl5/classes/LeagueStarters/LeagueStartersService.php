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
        $positions = \JSB::PLAYER_POSITIONS;

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
            $this->placeholderRow = $this->repository->getPlaceholderRow() ?? [];
        }
        $row = $this->placeholderRow;
        $row['teamid'] = $team->teamid;
        $row['teamname'] = $team->name;
        $row['color1'] = $team->color1;
        $row['color2'] = $team->color2;
        /** @var PlayerRow $row */
        return Player::withPlrRow($this->db, $row);
    }
}
