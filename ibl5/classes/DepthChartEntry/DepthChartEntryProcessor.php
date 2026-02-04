<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryProcessorInterface;

/**
 * @phpstan-import-type ProcessedPlayerData from Contracts\DepthChartEntryProcessorInterface
 * @phpstan-import-type ProcessedSubmission from Contracts\DepthChartEntryProcessorInterface
 *
 * @see DepthChartEntryProcessorInterface
 */
class DepthChartEntryProcessor implements DepthChartEntryProcessorInterface
{
    /**
     * @see DepthChartEntryProcessorInterface::processSubmission()
     * @param array<string, mixed> $postData
     * @return ProcessedSubmission
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
            $injury = $this->extractIntValue($postData, 'Injury' . $i);

            /** @var string $rawName */
            $rawName = $postData['Name' . $i];
            $player = [
                'name' => $this->sanitizePlayerName($rawName),
                'pg' => $this->sanitizeDepthValue($this->extractIntValue($postData, 'pg' . $i)),
                'sg' => $this->sanitizeDepthValue($this->extractIntValue($postData, 'sg' . $i)),
                'sf' => $this->sanitizeDepthValue($this->extractIntValue($postData, 'sf' . $i)),
                'pf' => $this->sanitizeDepthValue($this->extractIntValue($postData, 'pf' . $i)),
                'c' => $this->sanitizeDepthValue($this->extractIntValue($postData, 'c' . $i)),
                'active' => $this->sanitizeActiveValue($this->extractIntValue($postData, 'active' . $i)),
                'min' => $this->sanitizeMinutesValue($this->extractIntValue($postData, 'min' . $i)),
                'of' => $this->sanitizeFocusValue($this->extractIntValue($postData, 'OF' . $i)),
                'df' => $this->sanitizeFocusValue($this->extractIntValue($postData, 'DF' . $i)),
                'oi' => $this->sanitizeSettingValue($this->extractIntValue($postData, 'OI' . $i)),
                'di' => $this->sanitizeSettingValue($this->extractIntValue($postData, 'DI' . $i)),
                'bh' => $this->sanitizeSettingValue($this->extractIntValue($postData, 'BH' . $i)),
                'injury' => $injury
            ];
            
            $playerData[] = $player;
            
            if ($player['active'] === 1) {
                $activePlayers++;
            }

            if ($player['pg'] > 0 && $injury === 0) {
                $pos_1++;
            }
            if ($player['sg'] > 0 && $injury === 0) {
                $pos_2++;
            }
            if ($player['sf'] > 0 && $injury === 0) {
                $pos_3++;
            }
            if ($player['pf'] > 0 && $injury === 0) {
                $pos_4++;
            }
            if ($player['c'] > 0 && $injury === 0) {
                $pos_5++;
            }

            if ($player['pg'] === 1) $startingPositionCount++;
            if ($player['sg'] === 1) $startingPositionCount++;
            if ($player['sf'] === 1) $startingPositionCount++;
            if ($player['pf'] === 1) $startingPositionCount++;
            if ($player['c'] === 1) $startingPositionCount++;
            
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
     * Extract an integer value from POST data by key
     *
     * @param array<string, mixed> $postData
     */
    private function extractIntValue(array $postData, string $key): int
    {
        $value = $postData[$key] ?? 0;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    private function sanitizePlayerName(string $name): string
    {
        return trim(strip_tags($name));
    }
    
    private function sanitizeDepthValue(int $value): int
    {
        return max(0, min(5, $value));
    }

    private function sanitizeActiveValue(int $value): int
    {
        return $value === 1 ? 1 : 0;
    }

    private function sanitizeMinutesValue(int $value): int
    {
        return max(0, min(40, $value));
    }

    private function sanitizeFocusValue(int $value): int
    {
        return max(0, min(3, $value));
    }

    private function sanitizeSettingValue(int $value): int
    {
        return max(-2, min(2, $value));
    }
    
    /**
     * @see DepthChartEntryProcessorInterface::generateCsvContent()
     * @param list<ProcessedPlayerData> $playerData
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
