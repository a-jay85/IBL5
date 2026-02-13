<?php

declare(strict_types=1);

namespace Utilities;

/**
 * SchFileParser - Parse JSB .sch schedule files
 *
 * The .sch format is a compact fixed-width ASCII format (80,000 bytes):
 * - 8,000 game slots of 10 bytes each
 * - 500 date slots of 16 game slots each (up to 14 games + 2 padding per date)
 * - Date mapping: 31 slots per month (Oct=0-30, Nov=31-61, Dec=62-92, Jan=93-123, etc.)
 * - 10-byte game record: [teams:4][scores:6]
 * - Empty slot: "0   0     "
 */
class SchFileParser
{
    public const FILE_SIZE = 80000;
    public const RECORD_SIZE = 10;
    public const TEAMS_FIELD_SIZE = 4;
    public const SCORES_FIELD_SIZE = 6;
    public const SLOTS_PER_DATE = 16;
    public const MAX_GAMES_PER_DATE = 14;
    public const DAYS_PER_MONTH = 31;
    public const MAX_MONTH_OFFSET = 12;
    public const BOX_ID_MONTH_MULTIPLIER = 500;
    public const BOX_ID_DAY_MULTIPLIER = 15;

    private const EMPTY_RECORD = '0   0     ';

    /**
     * Parse a .sch file and return all games as structured arrays.
     *
     * @return list<array{
     *     date_slot: int,
     *     game_index: int,
     *     visitor: int,
     *     home: int,
     *     visitor_score: int,
     *     home_score: int,
     *     played: bool
     * }>
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Schedule file not found: {$filePath}");
        }

        $fileSize = filesize($filePath);
        if ($fileSize !== self::FILE_SIZE) {
            throw new \RuntimeException(
                "Invalid .sch file size: expected " . self::FILE_SIZE . " bytes, got {$fileSize}"
            );
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new \RuntimeException("Failed to read schedule file: {$filePath}");
        }

        $games = [];
        $bytesPerDate = self::SLOTS_PER_DATE * self::RECORD_SIZE;

        for ($dateSlot = 0; $dateSlot < intdiv(self::FILE_SIZE, $bytesPerDate); $dateSlot++) {
            $monthOffset = intdiv($dateSlot, self::DAYS_PER_MONTH);
            if ($monthOffset >= self::MAX_MONTH_OFFSET) {
                continue;
            }

            $monthDay = self::dateSlotToMonthDay($dateSlot);
            if ($monthDay === null) {
                continue;
            }

            for ($gameIndex = 0; $gameIndex < self::SLOTS_PER_DATE; $gameIndex++) {
                $offset = ($dateSlot * self::SLOTS_PER_DATE + $gameIndex) * self::RECORD_SIZE;
                $record = substr($data, $offset, self::RECORD_SIZE);

                if ($record === self::EMPTY_RECORD) {
                    continue;
                }

                $parsed = self::parseGameRecord($record);
                if ($parsed === null) {
                    continue;
                }

                $played = $parsed['visitor_score'] > 0 || $parsed['home_score'] > 0;

                $games[] = [
                    'date_slot' => $dateSlot,
                    'game_index' => $gameIndex,
                    'visitor' => $parsed['visitor'],
                    'home' => $parsed['home'],
                    'visitor_score' => $parsed['visitor_score'],
                    'home_score' => $parsed['home_score'],
                    'played' => $played,
                ];
            }
        }

        return $games;
    }

    /**
     * Parse a single 10-byte game record.
     *
     * Teams field (4 bytes): visitor ID (unpadded) + home ID (zero-padded to 2 digits), right-padded with spaces.
     * Scores field (6 bytes): visitor score (unpadded) + home score (zero-padded to 3 digits), right-padded with spaces.
     *
     * @return array{visitor: int, home: int, visitor_score: int, home_score: int}|null
     */
    public static function parseGameRecord(string $record): ?array
    {
        if (strlen($record) !== self::RECORD_SIZE) {
            return null;
        }

        if ($record === self::EMPTY_RECORD) {
            return null;
        }

        $teamsField = rtrim(substr($record, 0, self::TEAMS_FIELD_SIZE));
        $scoresField = rtrim(substr($record, self::TEAMS_FIELD_SIZE, self::SCORES_FIELD_SIZE));

        if (strlen($teamsField) < 2) {
            return null;
        }

        // Home team is always the last 2 characters (zero-padded)
        $home = (int) substr($teamsField, -2);
        // Visitor is everything before the last 2 characters
        $visitor = (int) substr($teamsField, 0, -2);

        if ($visitor <= 0 || $home <= 0) {
            return null;
        }

        // For unplayed games, scores field is just "0"
        if ($scoresField === '0') {
            return [
                'visitor' => $visitor,
                'home' => $home,
                'visitor_score' => 0,
                'home_score' => 0,
            ];
        }

        if (strlen($scoresField) < 4) {
            return null;
        }

        // Home score is always the last 3 characters (zero-padded)
        $homeScore = (int) substr($scoresField, -3);
        // Visitor score is everything before the last 3 characters
        $visitorScore = (int) substr($scoresField, 0, -3);

        return [
            'visitor' => $visitor,
            'home' => $home,
            'visitor_score' => $visitorScore,
            'home_score' => $homeScore,
        ];
    }

    /**
     * Convert a date_slot to a calendar date (month and day).
     *
     * @return array{month: int, day: int}|null Null for invalid dates (e.g., Nov 31, Feb 30)
     */
    public static function dateSlotToMonthDay(int $dateSlot): ?array
    {
        $monthOffset = intdiv($dateSlot, self::DAYS_PER_MONTH);
        $dayZeroBased = $dateSlot % self::DAYS_PER_MONTH;

        if ($monthOffset >= self::MAX_MONTH_OFFSET) {
            return null;
        }

        // Convert month offset from October to calendar month (1-12)
        // Offset 0 = October (10), 1 = November (11), 2 = December (12), 3 = January (1), etc.
        $month = (($monthOffset + 9) % 12) + 1;
        $day = $dayZeroBased + 1;

        // Validate with checkdate (uses a leap year to allow Feb 29)
        if (!checkdate($month, $day, 2000)) {
            return null;
        }

        return [
            'month' => $month,
            'day' => $day,
        ];
    }

    /**
     * Compute the JSB BoxID from date slot and game index.
     *
     * Formula: month_from_october * 500 + (day - 1) * 15 + game_index
     */
    public static function computeBoxId(int $dateSlot, int $gameIndex): int
    {
        $monthOffset = intdiv($dateSlot, self::DAYS_PER_MONTH);
        $dayZeroBased = $dateSlot % self::DAYS_PER_MONTH;

        return $monthOffset * self::BOX_ID_MONTH_MULTIPLIER
            + $dayZeroBased * self::BOX_ID_DAY_MULTIPLIER
            + $gameIndex;
    }
}
