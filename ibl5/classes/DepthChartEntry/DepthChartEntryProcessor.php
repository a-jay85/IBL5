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
        $playerData = [];
        $pos1 = 0;
        $pos2 = 0;
        $pos3 = 0;
        $pos4 = 0;
        $pos5 = 0;
        $hasStarterAtMultiplePositions = false;
        $nameOfProblemStarter = '';

        for ($i = 1; $i <= $maxPlayers; $i++) {
            if (!isset($postData['Name' . $i])) {
                continue;
            }

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
                'canPlayInGame' => $this->sanitizeActiveValue($this->extractIntValue($postData, 'canPlayInGame' . $i)),
                'min' => $this->sanitizeMinutesValue($this->extractIntValue($postData, 'min' . $i)),
                'of' => 0,
                'df' => 0,
                'oi' => 0,
                'di' => 0,
                'bh' => 0,
                'injury' => $injury
            ];

            $playerData[] = $player;

            if ($player['canPlayInGame'] === 1) {
                $activePlayers++;
            }

            if ($injury === 0) {
                if ($player['pg'] > 0) {
                    $pos1++;
                }
                if ($player['sg'] > 0) {
                    $pos2++;
                }
                if ($player['sf'] > 0) {
                    $pos3++;
                }
                if ($player['pf'] > 0) {
                    $pos4++;
                }
                if ($player['c'] > 0) {
                    $pos5++;
                }
            }

            $startCount = 0;
            if ($player['pg'] === 1) {
                $startCount++;
            }
            if ($player['sg'] === 1) {
                $startCount++;
            }
            if ($player['sf'] === 1) {
                $startCount++;
            }
            if ($player['pf'] === 1) {
                $startCount++;
            }
            if ($player['c'] === 1) {
                $startCount++;
            }
            if ($startCount > 1) {
                $hasStarterAtMultiplePositions = true;
                $nameOfProblemStarter = $player['name'];
            }
        }

        return [
            'playerData' => $playerData,
            'activePlayers' => $activePlayers,
            'pos_1' => $pos1,
            'pos_2' => $pos2,
            'pos_3' => $pos3,
            'pos_4' => $pos4,
            'pos_5' => $pos5,
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
                $player['canPlayInGame'],
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
