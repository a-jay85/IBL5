<?php

namespace DepthChart;

/**
 * Processes depth chart data and submissions
 */
class DepthChartProcessor
{
    /**
     * Processes submitted depth chart data from POST request
     * 
     * @param array $postData POST data ($_POST)
     * @param int $maxPlayers Maximum number of players (default 15)
     * @return array Processed data with statistics
     */
    public function processSubmission(array $postData, int $maxPlayers = 15): array
    {
        $activePlayers = 0;
        $pos_1 = 0;
        $pos_2 = 0;
        $pos_3 = 0;
        $pos_4 = 0;
        $pos_5 = 0;
        $hasStarterAtMultiplePositions = false;
        $nameOfProblemStarter = '';
        $playerData = [];
        
        for ($i = 1; $i <= $maxPlayers; $i++) {
            if (!isset($postData['Name' . $i])) {
                continue;
            }
            
            $startingPositionCount = 0;
            $injury = $postData['Injury' . $i] ?? 0;
            
            $player = [
                'name' => $postData['Name' . $i],
                'pg' => $postData['pg' . $i],
                'sg' => $postData['sg' . $i],
                'sf' => $postData['sf' . $i],
                'pf' => $postData['pf' . $i],
                'c' => $postData['c' . $i],
                'active' => $postData['active' . $i],
                'min' => $postData['min' . $i],
                'of' => $postData['OF' . $i],
                'df' => $postData['DF' . $i],
                'oi' => $postData['OI' . $i],
                'di' => $postData['DI' . $i],
                'bh' => $postData['BH' . $i],
                'injury' => $injury
            ];
            
            $playerData[] = $player;
            
            // Count active players
            if ($player['active'] == 1) {
                $activePlayers++;
            }
            
            // Count players at each position (excluding injured players)
            if ($player['pg'] > 0 && $injury == 0) {
                $pos_1++;
            }
            if ($player['sg'] > 0 && $injury == 0) {
                $pos_2++;
            }
            if ($player['sf'] > 0 && $injury == 0) {
                $pos_3++;
            }
            if ($player['pf'] > 0 && $injury == 0) {
                $pos_4++;
            }
            if ($player['c'] > 0 && $injury == 0) {
                $pos_5++;
            }
            
            // Check if player is starting at multiple positions
            if ($player['pg'] == 1) $startingPositionCount++;
            if ($player['sg'] == 1) $startingPositionCount++;
            if ($player['sf'] == 1) $startingPositionCount++;
            if ($player['pf'] == 1) $startingPositionCount++;
            if ($player['c'] == 1) $startingPositionCount++;
            
            if ($startingPositionCount > 1) {
                $hasStarterAtMultiplePositions = true;
                $nameOfProblemStarter = $player['name'];
            }
        }
        
        return [
            'playerData' => $playerData,
            'activePlayers' => $activePlayers,
            'pos_1' => $pos_1,
            'pos_2' => $pos_2,
            'pos_3' => $pos_3,
            'pos_4' => $pos_4,
            'pos_5' => $pos_5,
            'hasStarterAtMultiplePositions' => $hasStarterAtMultiplePositions,
            'nameOfProblemStarter' => $nameOfProblemStarter
        ];
    }
    
    /**
     * Generates CSV content from player data
     * 
     * @param array $playerData Array of player data
     * @return string CSV content
     */
    public function generateCsvContent(array $playerData): string
    {
        $csv = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI\n";
        
        foreach ($playerData as $player) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $player['name'],
                $player['pg'],
                $player['sg'],
                $player['sf'],
                $player['pf'],
                $player['c'],
                $player['active'],
                $player['min'],
                $player['of'],
                $player['df'],
                $player['oi'],
                $player['di'],
                $player['bh']
            );
        }
        
        return $csv;
    }
    
    /**
     * Gets position value from position string
     * 
     * @param string $position Position string (e.g., 'PG', 'C', 'GF')
     * @return int Position value (1-9)
     */
    public function getPositionValue(string $position): int
    {
        $positionMap = [
            'PG' => 1,
            'G' => 2,
            'SG' => 3,
            'GF' => 4,
            'SF' => 5,
            'F' => 6,
            'PF' => 7,
            'FC' => 8,
            'C' => 9
        ];
        
        return $positionMap[$position] ?? 0;
    }
    
    /**
     * Checks if a player can play at a position based on their natural position
     * 
     * @param string $playerPosition Player's natural position
     * @param int $slotMin Minimum position value for slot
     * @param int $slotMax Maximum position value for slot
     * @param int $injuryLevel Player's injury level
     * @return bool True if player can play at position
     */
    public function canPlayAtPosition(string $playerPosition, int $slotMin, int $slotMax, int $injuryLevel): bool
    {
        $posValue = $this->getPositionValue($playerPosition);
        
        // Player must not be severely injured (injury < 15 days)
        if ($injuryLevel >= 15) {
            return false;
        }
        
        // Player's position must be within the slot's range
        return $posValue >= $slotMin && $posValue <= $slotMax;
    }
}
