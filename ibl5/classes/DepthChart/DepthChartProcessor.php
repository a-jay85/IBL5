<?php

declare(strict_types=1);

namespace DepthChart;

use DepthChart\Contracts\DepthChartProcessorInterface;

/**
 * @see DepthChartProcessorInterface
 */
class DepthChartProcessor implements DepthChartProcessorInterface
{
    /**
     * @see DepthChartProcessorInterface::processSubmission()
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
            $injury = (int) ($postData['Injury' . $i] ?? 0);
            
            $player = [
                'name' => $this->sanitizePlayerName($postData['Name' . $i]),
                'pg' => $this->sanitizeDepthValue($postData['pg' . $i] ?? 0),
                'sg' => $this->sanitizeDepthValue($postData['sg' . $i] ?? 0),
                'sf' => $this->sanitizeDepthValue($postData['sf' . $i] ?? 0),
                'pf' => $this->sanitizeDepthValue($postData['pf' . $i] ?? 0),
                'c' => $this->sanitizeDepthValue($postData['c' . $i] ?? 0),
                'active' => $this->sanitizeActiveValue($postData['active' . $i] ?? 0),
                'min' => $this->sanitizeMinutesValue($postData['min' . $i] ?? 0),
                'of' => $this->sanitizeFocusValue($postData['OF' . $i] ?? 0),
                'df' => $this->sanitizeFocusValue($postData['DF' . $i] ?? 0),
                'oi' => $this->sanitizeSettingValue($postData['OI' . $i] ?? 0),
                'di' => $this->sanitizeSettingValue($postData['DI' . $i] ?? 0),
                'bh' => $this->sanitizeSettingValue($postData['BH' . $i] ?? 0),
                'injury' => $injury
            ];
            
            $playerData[] = $player;
            
            if ($player['active'] == 1) {
                $activePlayers++;
            }
            
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
    
    private function sanitizePlayerName(string $name): string
    {
        return trim(strip_tags($name));
    }
    
    private function sanitizeDepthValue($value): int
    {
        $value = (int) $value;
        return max(0, min(5, $value));
    }
    
    private function sanitizeActiveValue($value): int
    {
        return ((int) $value) === 1 ? 1 : 0;
    }
    
    private function sanitizeMinutesValue($value): int
    {
        $value = (int) $value;
        return max(0, min(40, $value));
    }
    
    private function sanitizeFocusValue($value): int
    {
        $value = (int) $value;
        return max(0, min(3, $value));
    }
    
    private function sanitizeSettingValue($value): int
    {
        $value = (int) $value;
        return max(-2, min(2, $value));
    }
    
    /**
     * @see DepthChartProcessorInterface::generateCsvContent()
     */
    public function generateCsvContent(array $playerData): string
    {
        $csv = "Name," . implode(',', \JSB::PLAYER_POSITIONS) . ",ACTIVE,MIN,OF,DF,OI,DI,BH\n";
        
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
}
