<?php

declare(strict_types=1);

namespace FreeAgency;

use League\League;
use Player\Player;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Team;
use Season\Season;
use Trading\CashConsiderationRepository;
use Trading\Contracts\CashConsiderationRepositoryInterface;

/**
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class FreeAgencyCapCalculator
{
    private \mysqli $mysqli_db;
    private Team $team;
    private Season $season;
    private TeamQueryRepositoryInterface $teamQueryRepo;
    private CashConsiderationRepositoryInterface $cashConsiderationRepo;

    /**
     * @param \mysqli $mysqli_db Database connection
     * @param Team $team Team entity
     * @param Season $season Season entity
     * @param TeamQueryRepositoryInterface|null $teamQueryRepo Team query repository (created internally if not provided)
     * @param CashConsiderationRepositoryInterface|null $cashConsiderationRepo Cash consideration repository (created internally if not provided)
     */
    public function __construct(\mysqli $mysqli_db, Team $team, Season $season, ?TeamQueryRepositoryInterface $teamQueryRepo = null, ?CashConsiderationRepositoryInterface $cashConsiderationRepo = null)
    {
        $this->mysqli_db = $mysqli_db;
        $this->team = $team;
        $this->season = $season;
        $this->teamQueryRepo = $teamQueryRepo ?? new \Team\TeamQueryRepository($mysqli_db);
        $this->cashConsiderationRepo = $cashConsiderationRepo ?? new CashConsiderationRepository($mysqli_db);
    }

    /**
     * Calculate total salaries from contracts and offers
     * 
     * @param array<int, array<string, mixed>> $rosterData Roster data from getRosterUnderContractOrderedByOrdinalResult()
     * @param array<int, array<string, mixed>> $offersData Offers data from getFreeAgencyOffersResult()
     * @param int|null $excludeOfferPid Player ID to exclude from offer calculations
     * @return array<int, int> Total salaries for years 1-6
     */
    private function calculateTotalSalaries(array $rosterData, array $offersData, ?int $excludeOfferPid = null): array
    {
        $totalSalaries = [0, 0, 0, 0, 0, 0];

        // Add salaries from players under contract
        foreach ($rosterData as $playerRow) {
            /** @var PlayerRow $playerRow */
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if (!$player->isPlayerFreeAgent($this->season)) {
                $futureSalaries = $player->getFutureSalaries();

                for ($year = 0; $year < 6; $year++) {
                    $totalSalaries[$year] += $futureSalaries[$year];
                }
            }
        }

        // Add cash considerations (trades, buyouts) for the team
        // Must apply the same cy-based offset as player contracts
        $isOffseason = $this->season->isOffseasonPhase();
        $cashRows = $this->cashConsiderationRepo->getTeamCashForSalary($this->team->teamid);
        foreach ($cashRows as $cashRow) {
            $cy = $cashRow['cy'] ?? 1;
            if ($isOffseason) {
                $cy++;
            }
            if ($cy === 0) {
                $cy = 1;
            }
            $salaryFields = [1 => $cashRow['cy1'], 2 => $cashRow['cy2'], 3 => $cashRow['cy3'],
                             4 => $cashRow['cy4'], 5 => $cashRow['cy5'], 6 => $cashRow['cy6']];
            $slot = 0;
            while ($cy <= 6 && $slot < 6) {
                $totalSalaries[$slot] += $salaryFields[$cy] ?? 0;
                $cy++;
                $slot++;
            }
        }

        // Add salaries from contract offers
        foreach ($offersData as $offerRow) {
            // Skip excluded player if specified
            if ($excludeOfferPid !== null && ($offerRow['pid'] ?? 0) === $excludeOfferPid) {
                continue;
            }

            for ($year = 0; $year < 6; $year++) {
                $offerKey = 'offer' . ($year + 1);
                /** @var int $offerValue */
                $offerValue = $offerRow[$offerKey] ?? 0;
                $totalSalaries[$year] += $offerValue;
            }
        }

        return $totalSalaries;
    }

    /**
     * Calculate available roster spots for all contract years
     *
     * @param array<int, array<string, mixed>> $rosterData Roster data from getRosterUnderContractOrderedByOrdinalResult()
     * @param array<int, array<string, mixed>> $offersData Offers data from getFreeAgencyOffersResult()
     * @param int|null $excludeOfferPid Player ID to exclude from offer calculations
     * @return array<int, int> Available roster spots for years 1-6 (indexed 0-5)
     */
    private function calculateRosterSpots(array $rosterData, array $offersData, ?int $excludeOfferPid = null): array
    {
        /** @var array<int, int> $rosterSpots */
        $rosterSpots = [
            0 => Team::ROSTER_SPOTS_MAX,
            1 => Team::ROSTER_SPOTS_MAX,
            2 => Team::ROSTER_SPOTS_MAX,
            3 => Team::ROSTER_SPOTS_MAX,
            4 => Team::ROSTER_SPOTS_MAX,
            5 => Team::ROSTER_SPOTS_MAX,
        ];

        // Count players under contract
        foreach ($rosterData as $playerRow) {
            /** @var PlayerRow $playerRow */
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);

            if (!$player->isPlayerFreeAgent($this->season)) {
                $futureSalaries = $player->getFutureSalaries();
                $this->decrementRosterSpotsForSalaries($rosterSpots, $futureSalaries);
            }
        }

        // Count contract offers
        foreach ($offersData as $offerRow) {
            // Skip excluded player if specified
            if ($excludeOfferPid !== null && ($offerRow['pid'] ?? 0) === $excludeOfferPid) {
                continue;
            }

            $this->decrementRosterSpotsForSalaries($rosterSpots, $offerRow);
        }

        return $rosterSpots;
    }

    /**
     * Decrement roster spots based on salary values
     * 
     * Decrements a spot for each year where there is non-zero salary.
     * Works with both player future salaries array and contract offer arrays.
     * 
     * @param array<int, int> $rosterSpots Roster spots array (indexed 0-5), passed by reference
     * @param array<int|string, int|string|mixed> $salaries Salary values (array indices 0-5 or keys offer1-6)
     * @return void
     */
    private function decrementRosterSpotsForSalaries(array &$rosterSpots, array $salaries): void
    {
        for ($year = 0; $year < 6; $year++) {
            // Handle both numeric indices (player salaries) and string keys (offer1-6)
            $key = isset($salaries[$year]) ? $year : ('offer' . ($year + 1));
            /** @var int $salaryValue */
            $salaryValue = $salaries[$key] ?? 0;
            $salary = $salaryValue;
            
            if ($salary !== 0) {
                $rosterSpots[$year]--;
            }
        }
    }

    /**
     * @return array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
     */
    public function calculateTeamCapMetrics(?int $excludeOfferPid = null): array
    {
        $rosterData = $this->teamQueryRepo->getRosterUnderContractOrderedByOrdinal($this->team->teamid);
        $offersData = $this->teamQueryRepo->getFreeAgencyOffers($this->team->teamid);
        
        $totalSalaries = $this->calculateTotalSalaries($rosterData, $offersData, $excludeOfferPid);
        $rosterSpots = $this->calculateRosterSpots($rosterData, $offersData, $excludeOfferPid);
        
        return [
            'totalSalaries' => $totalSalaries,
            'softCapSpace' => array_map(fn($salary) => League::SOFT_CAP_MAX - $salary, $totalSalaries),
            'hardCapSpace' => array_map(fn($salary) => League::HARD_CAP_MAX - $salary, $totalSalaries),
            'rosterSpots' => $rosterSpots,
        ];
    }
}
