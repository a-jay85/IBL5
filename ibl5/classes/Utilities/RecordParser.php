<?php

declare(strict_types=1);

namespace Utilities;

/**
 * RecordParser - Parse win-loss record strings
 * 
 * Extracts wins and losses from record formats like "45-37" or "5-3"
 */
class RecordParser
{
    /**
     * Extract wins from a record string (e.g., "45-37" -> 45)
     */
    public static function extractWins(string $record): int
    {
        $parts = explode('-', $record);
        return (int) trim($parts[0]);
    }

    /**
     * Extract losses from a record string (e.g., "45-37" -> 37)
     */
    public static function extractLosses(string $record): int
    {
        $parts = explode('-', $record);
        return isset($parts[1]) ? (int) trim($parts[1]) : 0;
    }

    /**
     * Parse a full record into wins and losses
     * 
     * @return array{wins: int, losses: int}
     */
    public static function parseRecord(string $record): array
    {
        return [
            'wins' => self::extractWins($record),
            'losses' => self::extractLosses($record),
        ];
    }
}
