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
        
        $rosterspots1 = $rosterspots2 = $rosterspots3 = \Team::ROSTER_SPOTS_MAX;
        $rosterspots4 = $rosterspots5 = $rosterspots6 = \Team::ROSTER_SPOTS_MAX;

        $season = new \Season($this->db);

        // Calculate from players under contract
        foreach ($team->getRosterUnderContractOrderedByOrdinalResult() as $playerRow) {
            $player = Player::withPlrRow($this->db, $playerRow);
            
            if (!$player->isPlayerFreeAgent($season)) {
                $futureSalaries = $player->getFutureSalaries();
                
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
            $year1TotalSalary += (int) $offerRow['offer1'];
            $year2TotalSalary += (int) $offerRow['offer2'];
            $year3TotalSalary += (int) $offerRow['offer3'];
            $year4TotalSalary += (int) $offerRow['offer4'];
            $year5TotalSalary += (int) $offerRow['offer5'];
            $year6TotalSalary += (int) $offerRow['offer6'];
            
            if ((int) $offerRow['offer1'] != 0) $rosterspots1--;
            if ((int) $offerRow['offer2'] != 0) $rosterspots2--;
            if ((int) $offerRow['offer3'] != 0) $rosterspots3--;
            if ((int) $offerRow['offer4'] != 0) $rosterspots4--;
            if ((int) $offerRow['offer5'] != 0) $rosterspots5--;
            if ((int) $offerRow['offer6'] != 0) $rosterspots6--;
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
        
        $rosterSpots = \Team::ROSTER_SPOTS_MAX;
        
        // Subtract current contracts
        $query = "SELECT * FROM ibl_plr WHERE (tid=$teamID AND retired='0') ORDER BY ordinal ASC";
        $result = $this->db->sql_query($query);
        
        foreach ($result as $row) {
            $ordinal = (int) $row['ordinal'];
            $cy = (int) $row['cy'];
            $cyt = (int) $row['cyt'];
            
            switch ($cy) {
                case 0:
                    $capSpace['year1'] -= (int) $row['cy1'];
                    $capSpace['year2'] -= (int) $row['cy2'];
                    $capSpace['year3'] -= (int) $row['cy3'];
                    $capSpace['year4'] -= (int) $row['cy4'];
                    $capSpace['year5'] -= (int) $row['cy5'];
                    $capSpace['year6'] -= (int) $row['cy6'];
                    break;
                case 1:
                    $capSpace['year1'] -= (int) $row['cy2'];
                    $capSpace['year2'] -= (int) $row['cy3'];
                    $capSpace['year3'] -= (int) $row['cy4'];
                    $capSpace['year4'] -= (int) $row['cy5'];
                    $capSpace['year5'] -= (int) $row['cy6'];
                    break;
                case 2:
                    $capSpace['year1'] -= (int) $row['cy3'];
                    $capSpace['year2'] -= (int) $row['cy4'];
                    $capSpace['year3'] -= (int) $row['cy5'];
                    $capSpace['year4'] -= (int) $row['cy6'];
                    break;
                case 3:
                    $capSpace['year1'] -= (int) $row['cy4'];
                    $capSpace['year2'] -= (int) $row['cy5'];
                    $capSpace['year3'] -= (int) $row['cy6'];
                    break;
                case 4:
                    $capSpace['year1'] -= (int) $row['cy5'];
                    $capSpace['year2'] -= (int) $row['cy6'];
                    break;
                case 5:
                    $capSpace['year1'] -= (int) $row['cy6'];
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
            $capSpace['year1'] -= (int) $offer['offer1'];
            $capSpace['year2'] -= (int) $offer['offer2'];
            $capSpace['year3'] -= (int) $offer['offer3'];
            $capSpace['year4'] -= (int) $offer['offer4'];
            $capSpace['year5'] -= (int) $offer['offer5'];
            $capSpace['year6'] -= (int) $offer['offer6'];
            $rosterSpots--;
        }
        
        $hardCapBuffer = \League::HARD_CAP_MAX - \League::SOFT_CAP_MAX;
        
        return [
            'softCap' => $capSpace,
            'hardCap' => [
                'year1' => $capSpace['year1'] + $hardCapBuffer,
                'year2' => $capSpace['year2'] + $hardCapBuffer,
                'year3' => $capSpace['year3'] + $hardCapBuffer,
                'year4' => $capSpace['year4'] + $hardCapBuffer,
                'year5' => $capSpace['year5'] + $hardCapBuffer,
                'year6' => $capSpace['year6'] + $hardCapBuffer,
            ],
            'rosterSpots' => $rosterSpots,
        ];
    }
}
