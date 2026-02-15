<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyCapCalculatorInterface;
use Player\Player;
use Team\Contracts\TeamQueryRepositoryInterface;

/**
 * @see FreeAgencyCapCalculatorInterface
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class FreeAgencyCapCalculator implements FreeAgencyCapCalculatorInterface
{
    private \mysqli $mysqli_db;
    private \Team $team;
    private \Season $season;
    private TeamQueryRepositoryInterface $teamQueryRepo;

    /**
     * @param \mysqli $mysqli_db Database connection
     * @param \Team $team Team entity
     * @param \Season $season Season entity
     * @param TeamQueryRepositoryInterface|null $teamQueryRepo Team query repository (created internally if not provided)
     */
    public function __construct(\mysqli $mysqli_db, \Team $team, \Season $season, ?TeamQueryRepositoryInterface $teamQueryRepo = null)
    {
        $this->mysqli_db = $mysqli_db;
        $this->team = $team;
        $this->season = $season;
        $this->teamQueryRepo = $teamQueryRepo ?? new \Team\TeamQueryRepository($mysqli_db);
    }

    /**
     * Calculate total salaries from contracts and offers
     * 
     * @param array<int, array<string, mixed>> $rosterData Roster data from getRosterUnderContractOrderedByOrdinalResult()
     * @param array<int, array<string, mixed>> $offersData Offers data from getFreeAgencyOffersResult()
     * @param string|null $excludeOfferPlayerName Player name to exclude from offer calculations
     * @return array<int, int> Total salaries for years 1-6
     */
    private function calculateTotalSalaries(array $rosterData, array $offersData, ?string $excludeOfferPlayerName = null): array
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

        // Add salaries from contract offers
        foreach ($offersData as $offerRow) {
            // Skip excluded player if specified
            if ($excludeOfferPlayerName !== null && $offerRow['name'] === $excludeOfferPlayerName) {
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
     * @param string|null $excludeOfferPlayerName Player name to exclude from offer calculations
     * @return array<int, int> Available roster spots for years 1-6 (indexed 0-5)
     */
    private function calculateRosterSpots(array $rosterData, array $offersData, ?string $excludeOfferPlayerName = null): array
    {
        /** @var array<int, int> $rosterSpots */
        $rosterSpots = [
            0 => \Team::ROSTER_SPOTS_MAX,
            1 => \Team::ROSTER_SPOTS_MAX,
            2 => \Team::ROSTER_SPOTS_MAX,
            3 => \Team::ROSTER_SPOTS_MAX,
            4 => \Team::ROSTER_SPOTS_MAX,
            5 => \Team::ROSTER_SPOTS_MAX,
        ];

        // Count players under contract
        foreach ($rosterData as $playerRow) {
            /** @var PlayerRow $playerRow */
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);
            
            if (!$player->isPlayerFreeAgent($this->season)) {
                // Exclude players whose name starts with '|'
                $firstChar = substr($player->name ?? '', 0, 1);
                if ($player->teamName === $this->team->name && $firstChar !== '|') {
                    $futureSalaries = $player->getFutureSalaries();
                    $this->decrementRosterSpotsForSalaries($rosterSpots, $futureSalaries);
                }
            }
        }

        // Count contract offers
        foreach ($offersData as $offerRow) {
            // Skip excluded player if specified
            if ($excludeOfferPlayerName !== null && $offerRow['name'] === $excludeOfferPlayerName) {
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
     * @see FreeAgencyCapCalculatorInterface::calculateTeamCapMetrics()
     */
    public function calculateTeamCapMetrics(?string $excludeOfferPlayerName = null): array
    {
        // Fetch roster and offers data once, convert results to arrays
        $rosterResult = $this->teamQueryRepo->getRosterUnderContractOrderedByOrdinal($this->team->teamID);
        $offersResult = $this->teamQueryRepo->getFreeAgencyOffers($this->team->name);
        
        // Convert mysqli_result to arrays
        $rosterData = [];
        foreach ($rosterResult as $row) {
            $rosterData[] = $row;
        }
        
        $offersData = [];
        foreach ($offersResult as $row) {
            $offersData[] = $row;
        }
        
        $totalSalaries = $this->calculateTotalSalaries($rosterData, $offersData, $excludeOfferPlayerName);
        $rosterSpots = $this->calculateRosterSpots($rosterData, $offersData, $excludeOfferPlayerName);
        
        return [
            'totalSalaries' => $totalSalaries,
            'softCapSpace' => array_map(fn($salary) => \League::SOFT_CAP_MAX - $salary, $totalSalaries),
            'hardCapSpace' => array_map(fn($salary) => \League::HARD_CAP_MAX - $salary, $totalSalaries),
            'rosterSpots' => $rosterSpots,
        ];
    }
}
