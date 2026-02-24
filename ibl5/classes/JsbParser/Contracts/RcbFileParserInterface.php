<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .rcb (Record Book) text files.
 *
 * The .rcb format uses fixed-width entries organized into two sections:
 * - Lines 0-49: All-time records (top 50 rankings, 528 entries per line × 51 chars)
 * - Lines 50-82: Current season single-game records (160 entries per line × 90 chars)
 */
interface RcbFileParserInterface
{
    /**
     * Parse a complete .rcb file.
     *
     * @param string $filePath Path to the .rcb file
     * @return array{alltime: list<array{scope: string, team_id: int|null, record_type: string, stat_category: string, ranking: int, player_name: string, car_block_id: int, stat_value: float, stat_raw: int, team_of_record: int|null, season_year: int|null, career_total: int|null}>, currentSeason: list<array{scope: string, team_id: int|null, context: string, stat_category: string, ranking: int, player_name: string, player_position: string, car_block_id: int, stat_value: int, season_year: int}>}
     * @throws \RuntimeException If file cannot be read or has invalid structure
     */
    public static function parseFile(string $filePath): array;

    /**
     * Parse a single 51-character all-time single-season entry.
     *
     * @param string $data Raw 51-character entry
     * @return array{player_name: string, car_block_id: int, stat_raw: int, team_of_record: int, season_year: int}|null Null if entry is empty/zeroed
     */
    public static function parseAlltimeSingleSeasonEntry(string $data): ?array;

    /**
     * Parse a single 51-character all-time career entry.
     *
     * @param string $data Raw 51-character entry
     * @return array{player_name: string, car_block_id: int, career_total: int, stat_raw: int, team_of_record: int}|null Null if entry is empty/zeroed
     */
    public static function parseAlltimeCareerEntry(string $data): ?array;

    /**
     * Parse a single 90-character current season entry (45-char player + 45-char team).
     *
     * @param string $data Raw 90-character entry
     * @return array{player_name: string, player_position: string, car_block_id: int, stat_value: int, season_year: int}|null Null if entry is empty/zeroed
     */
    public static function parseCurrentSeasonEntry(string $data): ?array;
}
