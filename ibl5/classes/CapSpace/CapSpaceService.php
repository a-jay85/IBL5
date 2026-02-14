<?php

declare(strict_types=1);

namespace CapSpace;

use CapSpace\Contracts\CapSpaceRepositoryInterface;
use Team\Contracts\TeamQueryRepositoryInterface;

/**
 * CapSpaceService - Business logic for salary cap information
 *
 * Processes team salary data and calculates available cap space.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-type AvailableSalary array{year1: int, year2: int, year3: int, year4: int, year5: int, year6: int}
 * @phpstan-type PositionSalaries array<string, int>
 * @phpstan-type CapSpaceTeamData array{team: \Team, teamId: int, teamName: string, teamCity: string, color1: string, color2: string, availableSalary: AvailableSalary, positionSalaries: PositionSalaries, freeAgencySlots: int, hasMLE: bool, hasLLE: bool}
 * @phpstan-type DisplayYears array{beginningYear: int, endingYear: int}
 *
 * @see CapSpaceRepositoryInterface For data access
 */
class CapSpaceService
{
    private CapSpaceRepositoryInterface $repository;
    private \mysqli $db;
    private TeamQueryRepositoryInterface $teamQueryRepo;

    /**
     * Constructor
     *
     * @param CapSpaceRepositoryInterface $repository Data repository
     * @param \mysqli $db Database connection for Team initialization
     * @param TeamQueryRepositoryInterface|null $teamQueryRepo Team query repository (created internally if not provided)
     */
    public function __construct(CapSpaceRepositoryInterface $repository, \mysqli $db, ?TeamQueryRepositoryInterface $teamQueryRepo = null)
    {
        $this->repository = $repository;
        $this->db = $db;
        $this->teamQueryRepo = $teamQueryRepo ?? new \Team\TeamQueryRepository($db);
    }

    /**
     * Get processed cap data for all teams
     *
     * @param \Season $season Current season
     * @return list<CapSpaceTeamData> Processed team cap data
     */
    public function getTeamsCapData(\Season $season): array
    {
        $teams = $this->repository->getAllTeams();
        $teamsData = [];

        foreach ($teams as $teamRow) {
            $team = \Team::initialize($this->db, $teamRow);
            $teamsData[] = $this->processTeamCapData($team, $season);
        }

        return $teamsData;
    }

    /**
     * Process salary cap data for a single team
     *
     * @param \Team $team Team object
     * @param \Season $season Current season
     * @return CapSpaceTeamData Processed cap data for the team
     */
    protected function processTeamCapData(\Team $team, \Season $season): array
    {
        $salaryCapSpent = $this->teamQueryRepo->getSalaryCapArray($team->name, $team->teamID, $season);
        $freeAgencySlots = 15;

        // Calculate available salary for each year
        $availableSalary = [
            'year1' => \League::HARD_CAP_MAX - $salaryCapSpent['year1'],
            'year2' => \League::HARD_CAP_MAX - $salaryCapSpent['year2'],
            'year3' => \League::HARD_CAP_MAX - $salaryCapSpent['year3'],
            'year4' => \League::HARD_CAP_MAX - $salaryCapSpent['year4'],
            'year5' => \League::HARD_CAP_MAX - $salaryCapSpent['year5'],
            'year6' => \League::HARD_CAP_MAX - $salaryCapSpent['year6'],
        ];

        // Get salary by position — fetch all players under contract once, partition by position
        $allPlayers = $this->teamQueryRepo->getAllPlayersUnderContract($team->name);
        /** @var array<string, list<PlayerRow>> $playersByPosition */
        $playersByPosition = [];
        foreach ($allPlayers as $playerRow) {
            /** @var string $pos */
            $pos = $playerRow['pos'] ?? '';
            $playersByPosition[$pos][] = $playerRow;
        }
        $positionSalaries = [];
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $positionPlayers = $playersByPosition[$position] ?? [];
            $positionSalaries[$position] = $this->teamQueryRepo->getTotalNextSeasonSalaries($positionPlayers);
        }

        // Calculate roster slots used
        $contractData = $this->repository->getPlayersUnderContractAfterSeason($team->teamID);

        // Calculate FA slots from roster - players with contracts beyond current season take up slots
        $freeAgencySlots = $freeAgencySlots - count($contractData);

        return [
            'team' => $team,
            'teamId' => $team->teamID,
            'teamName' => $team->name,
            'teamCity' => $team->city,
            'color1' => $team->color1,
            'color2' => $team->color2,
            'availableSalary' => $availableSalary,
            'positionSalaries' => $positionSalaries,
            'freeAgencySlots' => $freeAgencySlots,
            'hasMLE' => $team->hasMLE === 1,
            'hasLLE' => $team->hasLLE === 1,
        ];
    }

    /**
     * Get adjusted years for header display based on season phase
     *
     * @param \Season $season Current season
     * @return DisplayYears Beginning and ending years
     */
    public function getDisplayYears(\Season $season): array
    {
        $beginningYear = ($season->phase === 'Free Agency')
            ? $season->beginningYear + 1
            : $season->beginningYear;
        $endingYear = ($season->phase === 'Free Agency')
            ? $season->endingYear + 1
            : $season->endingYear;

        return [
            'beginningYear' => $beginningYear,
            'endingYear' => $endingYear,
        ];
    }
}
