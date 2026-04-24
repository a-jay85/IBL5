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
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class LeagueStartersService implements LeagueStartersServiceInterface
{
    private \mysqli $db;
    private League $league;
    private LeagueStartersRepositoryInterface $repository;
    private ?Player $placeholderPlayer = null;

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
            $teamname = is_string($row['teamname']) ? $row['teamname'] : '';
            $color1 = is_string($row['color1']) ? $row['color1'] : '';
            $color2 = is_string($row['color2']) ? $row['color2'] : '';

            foreach ($depthColumns as $position => $column) {
                if (($row[$column] ?? 0) !== 1) {
                    continue;
                }
                if (isset($starterMap[$teamid][$position])) {
                    continue;
                }
                /** @var PlayerRow $row */
                $player = Player::withPlrRow($this->db, $row);
                $player->teamName = $teamname;
                $player->teamColor1 = $color1;
                $player->teamColor2 = $color2;
                $starterMap[$teamid][$position] = $player;
            }
        }

        foreach ($teams as $teamRow) {
            /** @var array<string, mixed> $teamRow */
            $team = Team::initialize($this->db, $teamRow);

            foreach ($positions as $position) {
                if (isset($starterMap[$team->teamid][$position])) {
                    $player = $starterMap[$team->teamid][$position];
                    $player->teamCity = $team->city;
                } else {
                    $player = clone $this->getOrLoadPlaceholder();
                    $player->teamName = $team->name;
                    $player->teamCity = $team->city;
                    $player->teamColor1 = $team->color1;
                    $player->teamColor2 = $team->color2;
                }
                $startersByPosition[$position][] = $player;
            }
        }

        return $startersByPosition;
    }

    private function getOrLoadPlaceholder(): Player
    {
        if ($this->placeholderPlayer === null) {
            $this->placeholderPlayer = Player::withPlayerID($this->db, 4040404);
        }
        return $this->placeholderPlayer;
    }
}
