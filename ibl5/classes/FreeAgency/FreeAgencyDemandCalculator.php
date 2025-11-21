<?php

namespace FreeAgency;

use Player\Player;

/**
 * Calculates player contract demands with team-specific modifiers
 * 
 * Factors affecting demands:
 * - Team season performance (wins/losses)
 * - Team tradition (historical wins/losses)
 * - Player loyalty to current team
 * - Contract security (years offered)
 * - Playing time opportunity
 * - Random variance for negotiation dynamics
 */
class FreeAgencyDemandCalculator
{
    private const PLAY_FOR_WINNER_FACTOR = 0.000153;
    private const TRADITION_FACTOR = 0.000153;
    private const LOYALTY_BONUS_PERCENTAGE = 0.025;
    private const SECURITY_BASE_FACTOR = -0.025;
    private const SECURITY_YEAR_FACTOR = 0.01;
    private const PLAYING_TIME_BASE_FACTOR = -0.025;
    private const PLAYING_TIME_MONEY_FACTOR = -0.0025;
    private const PLAYING_TIME_DIVISOR = 100;
    private const MAX_POSITION_SALARY_CAP = 2000;
    private const RANDOM_VARIANCE_MIN = -5;
    private const RANDOM_VARIANCE_MAX = 5;
    private const RANDOM_VARIANCE_BASE = 100;

    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Calculate perceived value of an offer with team modifiers applied
     * 
     * @param int $offerAverage Average salary offered per year
     * @param string $teamName Offering team name
     * @param Player $player Player object with preferences
     * @param int $yearsInOffer Number of years in the offer
     * @return float Perceived value after modifiers
     */
    public function calculatePerceivedValue(
        int $offerAverage,
        string $teamName,
        Player $player,
        int $yearsInOffer
    ): float {
        $databaseService = new \Services\DatabaseService();
        
        // Get team performance data
        $escapedTeamName = $databaseService->escapeString($this->db, $teamName);
        $teamQuery = "SELECT Contract_Wins, Contract_Losses, Contract_AvgW, Contract_AvgL 
                      FROM ibl_team_info WHERE team_name = '$escapedTeamName'";
        $teamResult = $this->db->sql_query($teamQuery);
        
        $teamWins = (int) $this->db->sql_result($teamResult, 0, "Contract_Wins");
        $teamLosses = (int) $this->db->sql_result($teamResult, 0, "Contract_Losses");
        $tradWins = (int) $this->db->sql_result($teamResult, 0, "Contract_AvgW");
        $tradLosses = (int) $this->db->sql_result($teamResult, 0, "Contract_AvgL");
        
        // Calculate position salary
        $positionSalary = $this->calculatePositionSalary($teamName, $player);
        
        // Calculate modifiers
        $modifier = $this->calculateModifier(
            $teamWins,
            $teamLosses,
            $tradWins,
            $tradLosses,
            $player->freeAgencyPlayForWinner,
            $player->freeAgencyTradition,
            $player->freeAgencyLoyalty,
            $player->freeAgencySecurity,
            $player->freeAgencyPlayingTime,
            $teamName,
            $player->teamName,
            $yearsInOffer,
            $positionSalary
        );
        
        // Apply random variance
        $random = rand(self::RANDOM_VARIANCE_MIN, self::RANDOM_VARIANCE_MAX);
        $modRandom = (self::RANDOM_VARIANCE_BASE + $random) / self::RANDOM_VARIANCE_BASE;
        
        return $offerAverage * $modifier * $modRandom;
    }

    /**
     * Calculate total salary committed to a position
     * 
     * @param string $teamName Team name
     * @param Player $player Player to exclude and get position from
     * @return int Total salary committed
     */
    private function calculatePositionSalary(
        string $teamName,
        Player $player
    ): int {
        $databaseService = new \Services\DatabaseService();
        $escapedTeamName = $databaseService->escapeString($this->db, $teamName);
        $escapedPosition = $databaseService->escapeString($this->db, $player->position);
        $escapedPlayerName = $databaseService->escapeString($this->db, $player->name);
        
        $query = "SELECT cy, cy1, cy2, cy3, cy4, cy5, cy6 
                  FROM ibl_plr 
                  WHERE teamname='$escapedTeamName' 
                    AND pos='$escapedPosition' 
                    AND name!='$escapedPlayerName'";
        $result = $this->db->sql_query($query);
        
        $totalSalary = 0;
        
        foreach ($result as $row) {
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
        
        // Cap at maximum
        return min($totalSalary, self::MAX_POSITION_SALARY_CAP);
    }

    /**
     * Calculate overall modifier based on team and player factors
     * 
     * @param int $teamWins Team wins this season
     * @param int $teamLosses Team losses this season
     * @param int $tradWins Team tradition wins
     * @param int $tradLosses Team tradition losses
     * @param int $playerWinner Player's desire to play for winner (1-10)
     * @param int $playerTradition Player's value of team tradition (1-10)
     * @param int $playerLoyalty Player's loyalty factor (1-10)
     * @param int $playerSecurity Player's desire for security (1-10)
     * @param int $playerPlayingTime Player's desire for playing time (1-10)
     * @param string $teamName Offering team
     * @param string $playerTeamName Player's current team
     * @param int $yearsInOffer Years in contract offer
     * @param int $positionSalary Salary committed to position
     * @return float Combined modifier (typically 0.8-1.2)
     */
    private function calculateModifier(
        int $teamWins,
        int $teamLosses,
        int $tradWins,
        int $tradLosses,
        int $playerWinner,
        int $playerTradition,
        int $playerLoyalty,
        int $playerSecurity,
        int $playerPlayingTime,
        string $teamName,
        string $playerTeamName,
        int $yearsInOffer,
        int $positionSalary
    ): float {
        // Play for winner factor
        $seasonDifferential = $teamWins - $teamLosses;
        $factorPlayForWinner = self::PLAY_FOR_WINNER_FACTOR * $seasonDifferential * ($playerWinner - 1);
        
        // Tradition factor
        $traditionDifferential = $tradWins - $tradLosses;
        $factorTradition = self::TRADITION_FACTOR * $traditionDifferential * ($playerTradition - 1);
        
        // Loyalty factor (bonus for staying, penalty for leaving)
        if ($teamName == $playerTeamName) {
            $factorLoyalty = self::LOYALTY_BONUS_PERCENTAGE * ($playerLoyalty - 1);
        } else {
            $factorLoyalty = -self::LOYALTY_BONUS_PERCENTAGE * ($playerLoyalty - 1);
        }
        
        // Security factor (longer contracts more attractive)
        $factorSecurity = (self::SECURITY_YEAR_FACTOR * ($yearsInOffer - 1) + self::SECURITY_BASE_FACTOR) 
                          * ($playerSecurity - 1);
        
        // Playing time factor (less money at position means more opportunity)
        $factorPlayingTime = (self::PLAYING_TIME_MONEY_FACTOR * $positionSalary / self::PLAYING_TIME_DIVISOR 
                              + self::PLAYING_TIME_BASE_FACTOR) 
                             * ($playerPlayingTime - 1);
        
        return 1 + $factorPlayForWinner + $factorTradition + $factorLoyalty + $factorSecurity + $factorPlayingTime;
    }

    /**
     * Get player demands from database
     * 
     * @param string $playerName Player name
     * @return array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}
     */
    public function getPlayerDemands(string $playerName): array
    {
        $databaseService = new \Services\DatabaseService();
        $escapedPlayerName = $databaseService->escapeString($this->db, $playerName);
        
        $query = "SELECT dem1, dem2, dem3, dem4, dem5, dem6 
                  FROM ibl_demands 
                  WHERE name='$escapedPlayerName'";
        $result = $this->db->sql_query($query);
        $demands = $this->db->sql_fetchrow($result);
        
        return [
            'dem1' => (int) ($demands['dem1'] ?? 0),
            'dem2' => (int) ($demands['dem2'] ?? 0),
            'dem3' => (int) ($demands['dem3'] ?? 0),
            'dem4' => (int) ($demands['dem4'] ?? 0),
            'dem5' => (int) ($demands['dem5'] ?? 0),
            'dem6' => (int) ($demands['dem6'] ?? 0),
        ];
    }
}
