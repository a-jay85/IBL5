<?php

declare(strict_types=1);

namespace CapInfo;

use CapInfo\Contracts\CapInfoRepositoryInterface;

/**
 * CapInfoService - Business logic for salary cap information
 *
 * Processes team salary data and calculates available cap space.
 *
 * @see CapInfoRepositoryInterface For data access
 */
class CapInfoService
{
    private CapInfoRepositoryInterface $repository;
    private object $db;

    /**
     * Constructor
     *
     * @param CapInfoRepositoryInterface $repository Data repository
     * @param object $db Database connection for Team initialization
     */
    public function __construct(CapInfoRepositoryInterface $repository, object $db)
    {
        $this->repository = $repository;
        $this->db = $db;
    }

    /**
     * Get processed cap data for all teams
     *
     * @param \Season $season Current season
     * @return array Processed team cap data
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
     * @return array Processed cap data for the team
     */
    private function processTeamCapData(\Team $team, \Season $season): array
    {
        $salaryCapSpent = $team->getSalaryCapArray($season);
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

        // Get salary by position
        $positionSalaries = [];
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $playersResult = $team->getPlayersUnderContractByPositionResult($position);
            $positionSalaries[$position] = $team->getTotalNextSeasonSalariesFromPlrResult($playersResult);
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
            'hasMLE' => $team->hasMLE === '1',
            'hasLLE' => $team->hasLLE === '1',
        ];
    }

    /**
     * Get adjusted years for header display based on season phase
     *
     * @param \Season $season Current season
     * @return array Beginning and ending years
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
