<?php

namespace FreeAgency;

use Player\Player;

/**
 * Calculates cap space and roster spots for Free Agency
 * 
 * Handles:
 * - Soft cap and hard cap calculations
 * - Roster spot tracking
 * - Future year projections
 */
class FreeAgencyCapCalculator
{
    private const ROSTER_SPOTS_MAX = 15;

    private $db;
    private \Services\DatabaseService $databaseService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->databaseService = new \Services\DatabaseService();
    }

    /**
     * Calculate complete cap space data for a team
     * 
     * @param \Team $team
     * @return array<string, mixed> Cap space and roster data
     */
    public function calculateTeamCapSpace($team): array
    {
        $year1TotalSalary = $year2TotalSalary = $year3TotalSalary = 0;
        $year4TotalSalary = $year5TotalSalary = $year6TotalSalary = 0;
        
        $rosterspots1 = $rosterspots2 = $rosterspots3 = self::ROSTER_SPOTS_MAX;
        $rosterspots4 = $rosterspots5 = $rosterspots6 = self::ROSTER_SPOTS_MAX;

        $season = new \Season($this->db);

        // Calculate from players under contract
        foreach ($team->getRosterUnderContractOrderedByOrdinalResult() as $playerRow) {
            $player = Player::withPlrRow($this->db, $playerRow);
            
            $yearPlayerIsFreeAgent = $player->draftYear + $player->yearsOfExperience 
                                    + $player->contractTotalYears - $player->contractCurrentYear;
            
            if ($yearPlayerIsFreeAgent != $season->endingYear) {
                $futureSalaries = $this->calculatePlayerFutureSalaries($player);
                
                $year1TotalSalary += $futureSalaries[0];
                $year2TotalSalary += $futureSalaries[1];
                $year3TotalSalary += $futureSalaries[2];
                $year4TotalSalary += $futureSalaries[3];
                $year5TotalSalary += $futureSalaries[4];
                $year6TotalSalary += $futureSalaries[5];
                
                // Count roster spots (exclude players whose name starts with '|')
                $firstChar = substr($player->name, 0, 1);
                if ($player->teamName == $team->name && $firstChar !== '|') {
                    if ($futureSalaries[0] != 0) $rosterspots1--;
                    if ($futureSalaries[1] != 0) $rosterspots2--;
                    if ($futureSalaries[2] != 0) $rosterspots3--;
                    if ($futureSalaries[3] != 0) $rosterspots4--;
                    if ($futureSalaries[4] != 0) $rosterspots5--;
                    if ($futureSalaries[5] != 0) $rosterspots6--;
                }
            }
        }

        // Add contract offers
        foreach ($team->getFreeAgencyOffersResult() as $offerRow) {
            $year1TotalSalary += $offerRow['offer1'];
            $year2TotalSalary += $offerRow['offer2'];
            $year3TotalSalary += $offerRow['offer3'];
            $year4TotalSalary += $offerRow['offer4'];
            $year5TotalSalary += $offerRow['offer5'];
            $year6TotalSalary += $offerRow['offer6'];
            
            if ($offerRow['offer1'] != 0) $rosterspots1--;
            if ($offerRow['offer2'] != 0) $rosterspots2--;
            if ($offerRow['offer3'] != 0) $rosterspots3--;
            if ($offerRow['offer4'] != 0) $rosterspots4--;
            if ($offerRow['offer5'] != 0) $rosterspots5--;
            if ($offerRow['offer6'] != 0) $rosterspots6--;
        }

        // Calculate available cap space
        return [
            'year1TotalSalary' => $year1TotalSalary,
            'year2TotalSalary' => $year2TotalSalary,
            'year3TotalSalary' => $year3TotalSalary,
            'year4TotalSalary' => $year4TotalSalary,
            'year5TotalSalary' => $year5TotalSalary,
            'year6TotalSalary' => $year6TotalSalary,
            
            'year1AvailableSoftCap' => \League::SOFT_CAP_MAX - $year1TotalSalary,
            'year2AvailableSoftCap' => \League::SOFT_CAP_MAX - $year2TotalSalary,
            'year3AvailableSoftCap' => \League::SOFT_CAP_MAX - $year3TotalSalary,
            'year4AvailableSoftCap' => \League::SOFT_CAP_MAX - $year4TotalSalary,
            'year5AvailableSoftCap' => \League::SOFT_CAP_MAX - $year5TotalSalary,
            'year6AvailableSoftCap' => \League::SOFT_CAP_MAX - $year6TotalSalary,
            
            'year1AvailableHardCap' => \League::HARD_CAP_MAX - $year1TotalSalary,
            'year2AvailableHardCap' => \League::HARD_CAP_MAX - $year2TotalSalary,
            'year3AvailableHardCap' => \League::HARD_CAP_MAX - $year3TotalSalary,
            'year4AvailableHardCap' => \League::HARD_CAP_MAX - $year4TotalSalary,
            'year5AvailableHardCap' => \League::HARD_CAP_MAX - $year5TotalSalary,
            'year6AvailableHardCap' => \League::HARD_CAP_MAX - $year6TotalSalary,
            
            'rosterspots1' => $rosterspots1,
            'rosterspots2' => $rosterspots2,
            'rosterspots3' => $rosterspots3,
            'rosterspots4' => $rosterspots4,
            'rosterspots5' => $rosterspots5,
            'rosterspots6' => $rosterspots6,
        ];
    }

    /**
     * Calculate cap space for negotiation page
     * 
     * @param int $teamID Team ID
     * @param string $teamName Team name
     * @param string $playerName Player name to exclude from offers
     * @return array<string, mixed> Cap space data
     */
    public function calculateNegotiationCapSpace(int $teamID, string $teamName, string $playerName): array
    {
        $capSpace = [
            'year1' => \League::SOFT_CAP_MAX,
            'year2' => \League::SOFT_CAP_MAX,
            'year3' => \League::SOFT_CAP_MAX,
            'year4' => \League::SOFT_CAP_MAX,
            'year5' => \League::SOFT_CAP_MAX,
            'year6' => \League::SOFT_CAP_MAX,
        ];
        
        $rosterSpots = self::ROSTER_SPOTS_MAX;
        
        // Subtract current contracts
        $query = "SELECT * FROM ibl_plr WHERE (tid=$teamID AND retired='0') ORDER BY ordinal ASC";
        $result = $this->db->sql_query($query);
        
        foreach ($result as $row) {
            $ordinal = $row['ordinal'];
            $cy = $row['cy'];
            $cyt = $row['cyt'];
            
            switch ($cy) {
                case 0:
                    $capSpace['year1'] -= $row['cy1'];
                    $capSpace['year2'] -= $row['cy2'];
                    $capSpace['year3'] -= $row['cy3'];
                    $capSpace['year4'] -= $row['cy4'];
                    $capSpace['year5'] -= $row['cy5'];
                    $capSpace['year6'] -= $row['cy6'];
                    break;
                case 1:
                    $capSpace['year1'] -= $row['cy2'];
                    $capSpace['year2'] -= $row['cy3'];
                    $capSpace['year3'] -= $row['cy4'];
                    $capSpace['year4'] -= $row['cy5'];
                    $capSpace['year5'] -= $row['cy6'];
                    break;
                case 2:
                    $capSpace['year1'] -= $row['cy3'];
                    $capSpace['year2'] -= $row['cy4'];
                    $capSpace['year3'] -= $row['cy5'];
                    $capSpace['year4'] -= $row['cy6'];
                    break;
                case 3:
                    $capSpace['year1'] -= $row['cy4'];
                    $capSpace['year2'] -= $row['cy5'];
                    $capSpace['year3'] -= $row['cy6'];
                    break;
                case 4:
                    $capSpace['year1'] -= $row['cy5'];
                    $capSpace['year2'] -= $row['cy6'];
                    break;
                case 5:
                    $capSpace['year1'] -= $row['cy6'];
                    break;
            }
            
            if ($cy != $cyt && $ordinal <= \JSB::WAIVERS_ORDINAL) {
                $rosterSpots--;
            }
        }
        
        // Subtract existing offers (excluding the current player's offer)
        $escapedTeamName = $this->databaseService->escapeString($this->db, $teamName);
        $escapedPlayerName = $this->databaseService->escapeString($this->db, $playerName);
        
        $query = "SELECT * FROM ibl_fa_offers WHERE team='$escapedTeamName' AND name!='$escapedPlayerName'";
        $result = $this->db->sql_query($query);
        
        foreach ($result as $offer) {
            $capSpace['year1'] -= $offer['offer1'];
            $capSpace['year2'] -= $offer['offer2'];
            $capSpace['year3'] -= $offer['offer3'];
            $capSpace['year4'] -= $offer['offer4'];
            $capSpace['year5'] -= $offer['offer5'];
            $capSpace['year6'] -= $offer['offer6'];
            $rosterSpots--;
        }
        
        return [
            'softCap' => $capSpace,
            'hardCap' => [
                'year1' => $capSpace['year1'] + 2000,
                'year2' => $capSpace['year2'] + 2000,
                'year3' => $capSpace['year3'] + 2000,
                'year4' => $capSpace['year4'] + 2000,
                'year5' => $capSpace['year5'] + 2000,
                'year6' => $capSpace['year6'] + 2000,
            ],
            'rosterSpots' => $rosterSpots,
        ];
    }

    /**
     * Calculate player's future salaries based on current contract year
     * 
     * @param Player $player
     * @return array<int> Salaries for years 1-6
     */
    private function calculatePlayerFutureSalaries(Player $player): array
    {
        $salaries = [0, 0, 0, 0, 0, 0];
        
        switch ($player->contractCurrentYear) {
            case 0:
                return [
                    $player->contractYear1Salary,
                    $player->contractYear2Salary,
                    $player->contractYear3Salary,
                    $player->contractYear4Salary,
                    $player->contractYear5Salary,
                    $player->contractYear6Salary,
                ];
            case 1:
                return [
                    $player->contractYear2Salary,
                    $player->contractYear3Salary,
                    $player->contractYear4Salary,
                    $player->contractYear5Salary,
                    $player->contractYear6Salary,
                    0,
                ];
            case 2:
                return [
                    $player->contractYear3Salary,
                    $player->contractYear4Salary,
                    $player->contractYear5Salary,
                    $player->contractYear6Salary,
                    0,
                    0,
                ];
            case 3:
                return [
                    $player->contractYear4Salary,
                    $player->contractYear5Salary,
                    $player->contractYear6Salary,
                    0,
                    0,
                    0,
                ];
            case 4:
                return [
                    $player->contractYear5Salary,
                    $player->contractYear6Salary,
                    0,
                    0,
                    0,
                    0,
                ];
            case 5:
                return [
                    $player->contractYear6Salary,
                    0,
                    0,
                    0,
                    0,
                    0,
                ];
        }
        
        return $salaries;
    }
}
