<?php

namespace FreeAgency;

/**
 * Repository for retrieving free agency demand-related data from the database
 */
class FreeAgencyDemandRepository implements FreeAgencyDemandRepositoryInterface
{
    private $db;
    private $mysqli_db;

    public function __construct($db, $mysqli_db = null)
    {
        $this->db = $db;
        $this->mysqli_db = $mysqli_db;
    }

    /**
     * Get team contract performance data
     * 
     * @param string $teamName Team name
     * @return array{wins: int, losses: int, tradWins: int, tradLosses: int}
     */
    public function getTeamPerformance(string $teamName): array
    {
        $query = "SELECT Contract_Wins, Contract_Losses, Contract_AvgW, Contract_AvgL 
                  FROM ibl_team_info 
                  WHERE team_name = ?";
        
        $stmt = $this->mysqli_db->prepare($query);
        $stmt->bind_param('s', $teamName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['wins' => 0, 'losses' => 0, 'tradWins' => 0, 'tradLosses' => 0];
        }
        
        $row = $result->fetch_assoc();
        
        return [
            'wins' => (int) ($row['Contract_Wins'] ?? 0),
            'losses' => (int) ($row['Contract_Losses'] ?? 0),
            'tradWins' => (int) ($row['Contract_AvgW'] ?? 0),
            'tradLosses' => (int) ($row['Contract_AvgL'] ?? 0),
        ];
    }

    /**
     * Get total salary committed to a specific position on a team
     * 
     * @param string $teamName Team name
     * @param string $position Player position
     * @param int $excludePlayerID Player ID to exclude from calculation
     * @return int Total salary committed to the position
     */
    public function getPositionSalaryCommitment(string $teamName, string $position, int $excludePlayerID): int
    {
        $query = "SELECT cy, cy1, cy2, cy3, cy4, cy5, cy6 
                  FROM ibl_plr 
                  WHERE teamname = ? 
                    AND pos = ? 
                    AND pid != ?";
        
        $stmt = $this->mysqli_db->prepare($query);
        $stmt->bind_param('ssi', $teamName, $position, $excludePlayerID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $totalSalary = 0;
        
        while ($row = $result->fetch_assoc()) {
            $currentYear = (int) $row['cy'];
            
            // Get salary for next year based on current contract year
            switch ($currentYear) {
                case 0:
                    $totalSalary += (int) $row['cy1'];
                    break;
                case 1:
                    $totalSalary += (int) $row['cy2'];
                    break;
                case 2:
                    $totalSalary += (int) $row['cy3'];
                    break;
                case 3:
                    $totalSalary += (int) $row['cy4'];
                    break;
                case 4:
                    $totalSalary += (int) $row['cy5'];
                    break;
                case 5:
                    $totalSalary += (int) $row['cy6'];
                    break;
            }
        }
        
        return $totalSalary;
    }

    /**
     * Get player contract demands
     * 
     * @param string $playerName Player name
     * @return array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}
     */
    public function getPlayerDemands(string $playerName): array
    {
        $query = "SELECT dem1, dem2, dem3, dem4, dem5, dem6 
                  FROM ibl_demands 
                  WHERE name = ?";
        
        $stmt = $this->mysqli_db->prepare($query);
        $stmt->bind_param('s', $playerName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'dem1' => 0,
                'dem2' => 0,
                'dem3' => 0,
                'dem4' => 0,
                'dem5' => 0,
                'dem6' => 0,
            ];
        }
        
        $row = $result->fetch_assoc();
        
        return [
            'dem1' => (int) ($row['dem1'] ?? 0),
            'dem2' => (int) ($row['dem2'] ?? 0),
            'dem3' => (int) ($row['dem3'] ?? 0),
            'dem4' => (int) ($row['dem4'] ?? 0),
            'dem5' => (int) ($row['dem5'] ?? 0),
            'dem6' => (int) ($row['dem6'] ?? 0),
        ];
    }
}
