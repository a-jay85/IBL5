<?php

declare(strict_types=1);

namespace DepthChart;

use BasketballStats\StatsSanitizer;
use DepthChart\Contracts\DepthChartProcessorInterface;

/**
 * @phpstan-import-type ProcessedPlayerData from Contracts\DepthChartProcessorInterface
 * @phpstan-import-type ProcessedSubmission from Contracts\DepthChartProcessorInterface
 *
 * @see DepthChartProcessorInterface
 */
class DepthChartProcessor implements DepthChartProcessorInterface
{
    /**
     * Depth-chart slots in output order: index N here is `pos_(N+1)` in ProcessedSubmission.
     * Lowercase on purpose: these are the POST field prefixes and the ProcessedPlayerData keys.
     * \League\JsbConstants::PLAYER_POSITIONS is the uppercase CSV header list and stays separate.
     */
    private const POSITIONS = ['pg', 'sg', 'sf', 'pf', 'c'];

    /**
     * @see DepthChartProcessorInterface::processSubmission()
     * @param array<string, mixed> $postData
     * @return ProcessedSubmission
     */
    public function processSubmission(array $postData, int $maxPlayers = 15): array
    {
        $activePlayers = 0;
        $playerData = [];
        $positionCounts = array_fill_keys(self::POSITIONS, 0);
        $hasStarterAtMultiplePositions = false;
        $nameOfProblemStarter = '';

        for ($i = 1; $i <= $maxPlayers; $i++) {
            if (!isset($postData['Name' . $i])) {
                continue;
            }

            $player = $this->readPlayerRow($postData, $i);
            $playerData[] = $player;

            if ($player['canPlayInGame'] === 1) {
                $activePlayers++;
            }

            $startCount = 0;
            foreach (self::POSITIONS as $slot) {
                if ($player['injury'] === 0 && $player[$slot] > 0) {
                    $positionCounts[$slot]++;
                }
                if ($player[$slot] === 1) {
                    $startCount++;
                }
            }
            if ($startCount > 1) {
                $hasStarterAtMultiplePositions = true;
                $nameOfProblemStarter = $player['name'];
            }
        }

        return [
            'playerData' => $playerData,
            'activePlayers' => $activePlayers,
            'pos_1' => $positionCounts['pg'],
            'pos_2' => $positionCounts['sg'],
            'pos_3' => $positionCounts['sf'],
            'pos_4' => $positionCounts['pf'],
            'pos_5' => $positionCounts['c'],
            'hasStarterAtMultiplePositions' => $hasStarterAtMultiplePositions,
            'nameOfProblemStarter' => $nameOfProblemStarter
        ];
    }

    /**
     * Reads and sanitises one submitted roster row. Keys stay literal and in
     * ProcessedPlayerData order so PHPStan keeps the array shape.
     *
     * @param array<string, mixed> $postData
     * @return ProcessedPlayerData
     */
    private function readPlayerRow(array $postData, int $i): array
    {
        /** @var string $rawName */
        $rawName = $postData['Name' . $i];

        return [
            'pid' => StatsSanitizer::intFromArray($postData, 'pid' . $i),
            'name' => $this->sanitizePlayerName($rawName),
            'pg' => $this->sanitizeDepthValue(StatsSanitizer::intFromArray($postData, 'pg' . $i)),
            'sg' => $this->sanitizeDepthValue(StatsSanitizer::intFromArray($postData, 'sg' . $i)),
            'sf' => $this->sanitizeDepthValue(StatsSanitizer::intFromArray($postData, 'sf' . $i)),
            'pf' => $this->sanitizeDepthValue(StatsSanitizer::intFromArray($postData, 'pf' . $i)),
            'c' => $this->sanitizeDepthValue(StatsSanitizer::intFromArray($postData, 'c' . $i)),
            'canPlayInGame' => $this->sanitizeActiveValue(StatsSanitizer::intFromArray($postData, 'canPlayInGame' . $i)),
            'min' => $this->sanitizeMinutesValue(StatsSanitizer::intFromArray($postData, 'min' . $i)),
            'of' => 0,
            'df' => 0,
            'oi' => 0,
            'di' => 0,
            'bh' => 0,
            'injury' => StatsSanitizer::intFromArray($postData, 'Injury' . $i),
        ];
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
     * @see DepthChartProcessorInterface::generateCsvContent()
     * @param list<ProcessedPlayerData> $playerData
     */
    public function generateCsvContent(array $playerData): string
    {
        $csv = "Name," . implode(',', \League\JsbConstants::PLAYER_POSITIONS) . ",ACTIVE,MIN,OF,DF,OI,DI,BH\n";
        
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
