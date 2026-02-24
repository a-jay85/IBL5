<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\RcbFileParserInterface;

/**
 * Parser for JSB .rcb (Record Book) text files.
 *
 * The .rcb format contains pre-computed all-time records (league-wide + per-team)
 * and current-season single-game records. Fixed-width entries with CRLF line endings.
 *
 * @see /docs/JSB_FILE_FORMATS.md
 */
class RcbFileParser implements RcbFileParserInterface
{
    /** Number of all-time ranking lines (lines 0-49, one per rank #1-#50) */
    public const ALLTIME_LINE_COUNT = 50;

    /** Number of current season lines (lines 50-82) */
    public const SEASON_LINE_COUNT = 33;

    /** Characters per all-time entry */
    public const ALLTIME_ENTRY_SIZE = 51;

    /** Characters per current season entry (45-char player + 45-char team) */
    public const SEASON_ENTRY_SIZE = 90;

    /** Number of entries per group in all-time section */
    public const ENTRIES_PER_GROUP = 16;

    /** Number of groups per all-time line (league + 28 teams + 4 reserved) */
    public const GROUPS_PER_LINE = 33;

    /** Total entries per all-time line: 33 groups × 16 entries = 528 */
    public const ENTRIES_PER_ALLTIME_LINE = 528;

    /** Number of stat categories per context in current season (8 stats) */
    public const SEASON_STATS_PER_CONTEXT = 8;

    /** Number of ranking positions per stat in current season */
    public const SEASON_RANKINGS_PER_STAT = 10;

    /** Total entries per current season line: 10 rankings × 16 entries = 160 */
    public const ENTRIES_PER_SEASON_LINE = 160;

    /**
     * Mapping of even entry indices (within a 16-entry group) to single-season stat categories.
     *
     * @var array<int, string>
     */
    public const SINGLE_SEASON_STATS = [
        0 => 'ppg',
        2 => 'rpg',
        4 => 'apg',
        6 => 'spg',
        8 => 'bpg',
        10 => 'fg_pct',
        12 => 'ft_pct',
        14 => 'three_pct',
    ];

    /**
     * Mapping of odd entry indices (within a 16-entry group) to career stat categories.
     *
     * @var array<int, string>
     */
    public const CAREER_STATS = [
        1 => 'pts',
        3 => 'trb',
        5 => 'ast',
        7 => 'stl',
        9 => 'blk',
        11 => 'fg_pct',
        13 => 'ft_pct',
        15 => 'three_pct',
    ];

    /**
     * Stats that use percentage encoding (value * 10000) rather than per-game average (value * 100).
     *
     * @var list<string>
     */
    private const PERCENTAGE_STATS = ['fg_pct', 'ft_pct', 'three_pct'];

    /**
     * Mapping of current season entry index within a 16-entry block to [context, stat_category].
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const SEASON_STAT_LAYOUT = [
        0 => ['away', 'pts'],
        1 => ['away', 'reb'],
        2 => ['away', 'ast'],
        3 => ['away', 'stl'],
        4 => ['away', 'blk'],
        5 => ['away', 'two_gm'],
        6 => ['away', 'three_gm'],
        7 => ['away', 'ftm'],
        8 => ['home', 'pts'],
        9 => ['home', 'reb'],
        10 => ['home', 'ast'],
        11 => ['home', 'stl'],
        12 => ['home', 'blk'],
        13 => ['home', 'two_gm'],
        14 => ['home', 'three_gm'],
        15 => ['home', 'ftm'],
    ];

    /**
     * @see RcbFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("RCB file not found: {$filePath}");
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read RCB file: {$filePath}");
        }

        // Split on CRLF (or LF for flexibility)
        $lines = preg_split('/\r?\n/', $contents);
        if ($lines === false) {
            throw new \RuntimeException("Failed to split RCB file into lines: {$filePath}");
        }

        $lineCount = count($lines);
        if ($lineCount < self::ALLTIME_LINE_COUNT + self::SEASON_LINE_COUNT) {
            throw new \RuntimeException(
                'Invalid .rcb file: expected at least '
                . (self::ALLTIME_LINE_COUNT + self::SEASON_LINE_COUNT)
                . ' lines, got ' . $lineCount
            );
        }

        $alltime = self::parseAlltimeSection($lines);
        $currentSeason = self::parseCurrentSeasonSection($lines);

        return [
            'alltime' => $alltime,
            'currentSeason' => $currentSeason,
        ];
    }

    /**
     * Parse the all-time records section (lines 0-49).
     *
     * @param list<string> $lines All lines from the file
     * @return list<array{scope: string, team_id: int, record_type: string, stat_category: string, ranking: int, player_name: string, car_block_id: int, stat_value: float, stat_raw: int, team_of_record: int|null, season_year: int|null, career_total: int|null}>
     */
    private static function parseAlltimeSection(array $lines): array
    {
        $records = [];

        for ($ranking = 0; $ranking < self::ALLTIME_LINE_COUNT; $ranking++) {
            $line = $lines[$ranking];

            // Process each group (0 = league, 1-28 = teams, 29-32 = reserved)
            for ($group = 0; $group <= 28; $group++) {
                $scope = $group === 0 ? 'league' : 'team';
                $teamId = $group;

                // Process each entry within the group
                for ($entryIdx = 0; $entryIdx < self::ENTRIES_PER_GROUP; $entryIdx++) {
                    $globalEntryIdx = $group * self::ENTRIES_PER_GROUP + $entryIdx;
                    $charOffset = $globalEntryIdx * self::ALLTIME_ENTRY_SIZE;
                    $entryData = substr($line, $charOffset, self::ALLTIME_ENTRY_SIZE);

                    if (strlen($entryData) < self::ALLTIME_ENTRY_SIZE) {
                        continue;
                    }

                    $isEven = $entryIdx % 2 === 0;

                    if ($isEven) {
                        // Single-season record
                        if (!isset(self::SINGLE_SEASON_STATS[$entryIdx])) {
                            continue;
                        }
                        $statCategory = self::SINGLE_SEASON_STATS[$entryIdx];
                        $parsed = self::parseAlltimeSingleSeasonEntry($entryData);
                        if ($parsed === null) {
                            continue;
                        }

                        $records[] = [
                            'scope' => $scope,
                            'team_id' => $teamId,
                            'record_type' => 'single_season',
                            'stat_category' => $statCategory,
                            'ranking' => $ranking + 1,
                            'player_name' => $parsed['player_name'],
                            'car_block_id' => $parsed['car_block_id'],
                            'stat_value' => self::decodeStatValue($parsed['stat_raw'], $statCategory),
                            'stat_raw' => $parsed['stat_raw'],
                            'team_of_record' => $parsed['team_of_record'],
                            'season_year' => $parsed['season_year'],
                            'career_total' => null,
                        ];
                    } else {
                        // Career record — but for team groups 1-28, odd entries are team season records
                        if ($group >= 1) {
                            // Team season stat records (odd entries in team groups)
                            // These have a different format — skip for now as they're less useful
                            continue;
                        }

                        if (!isset(self::CAREER_STATS[$entryIdx])) {
                            continue;
                        }
                        $statCategory = self::CAREER_STATS[$entryIdx];
                        $parsed = self::parseAlltimeCareerEntry($entryData);
                        if ($parsed === null) {
                            continue;
                        }

                        $records[] = [
                            'scope' => $scope,
                            'team_id' => $teamId,
                            'record_type' => 'career',
                            'stat_category' => $statCategory,
                            'ranking' => $ranking + 1,
                            'player_name' => $parsed['player_name'],
                            'car_block_id' => $parsed['car_block_id'],
                            'stat_value' => self::decodeStatValue($parsed['stat_raw'], $statCategory),
                            'stat_raw' => $parsed['stat_raw'],
                            'team_of_record' => $parsed['team_of_record'],
                            'season_year' => null,
                            'career_total' => $parsed['career_total'],
                        ];
                    }
                }
            }
        }

        return $records;
    }

    /**
     * Parse the current season records section (lines 50-82).
     *
     * @param list<string> $lines All lines from the file
     * @return list<array{scope: string, team_id: int, context: string, stat_category: string, ranking: int, player_name: string, player_position: string, car_block_id: int, stat_value: int, season_year: int}>
     */
    private static function parseCurrentSeasonSection(array $lines): array
    {
        $records = [];

        for ($lineIdx = 0; $lineIdx < self::SEASON_LINE_COUNT; $lineIdx++) {
            $actualLineNum = self::ALLTIME_LINE_COUNT + $lineIdx;
            if (!isset($lines[$actualLineNum])) {
                continue;
            }
            $line = $lines[$actualLineNum];

            // Line 50 (lineIdx 0) = league-wide, lines 51-82 (lineIdx 1-32) = team-specific
            $scope = $lineIdx === 0 ? 'league' : 'team';
            $teamId = $lineIdx;

            // 160 entries = 10 rankings × 16 stat/context combos
            for ($entryIdx = 0; $entryIdx < self::ENTRIES_PER_SEASON_LINE; $entryIdx++) {
                $charOffset = $entryIdx * self::SEASON_ENTRY_SIZE;
                $entryData = substr($line, $charOffset, self::SEASON_ENTRY_SIZE);

                if (strlen($entryData) < self::SEASON_ENTRY_SIZE) {
                    continue;
                }

                $parsed = self::parseCurrentSeasonEntry($entryData);
                if ($parsed === null) {
                    continue;
                }

                // Determine ranking position and stat/context
                $statContextIdx = $entryIdx % self::ENTRIES_PER_GROUP;
                $rankingPosition = intdiv($entryIdx, self::ENTRIES_PER_GROUP) + 1;

                [$context, $statCategory] = self::SEASON_STAT_LAYOUT[$statContextIdx];

                $records[] = [
                    'scope' => $scope,
                    'team_id' => $teamId,
                    'context' => $context,
                    'stat_category' => $statCategory,
                    'ranking' => $rankingPosition,
                    'player_name' => $parsed['player_name'],
                    'player_position' => $parsed['player_position'],
                    'car_block_id' => $parsed['car_block_id'],
                    'stat_value' => $parsed['stat_value'],
                    'season_year' => $parsed['season_year'],
                ];
            }
        }

        return $records;
    }

    /**
     * @see RcbFileParserInterface::parseAlltimeSingleSeasonEntry()
     */
    public static function parseAlltimeSingleSeasonEntry(string $data): ?array
    {
        if (strlen($data) < self::ALLTIME_ENTRY_SIZE) {
            return null;
        }

        $playerName = trim(substr($data, 0, 33));
        if ($playerName === '' || $playerName === '0') {
            return null;
        }

        $carBlockId = (int) trim(substr($data, 33, 5));
        $statRaw = (int) trim(substr($data, 38, 6));
        $teamOfRecord = (int) trim(substr($data, 44, 2));
        $seasonYear = (int) trim(substr($data, 46, 4));

        // Empty slots: JSB fills unused ranking positions with recycled memory.
        // Valid entries always have nonzero stat values and alphabetic-only names.
        if ($statRaw === 0) {
            return null;
        }
        if (preg_match('/\d/', $playerName) === 1) {
            return null;
        }

        return [
            'player_name' => $playerName,
            'car_block_id' => $carBlockId,
            'stat_raw' => $statRaw,
            'team_of_record' => $teamOfRecord,
            'season_year' => $seasonYear,
        ];
    }

    /**
     * @see RcbFileParserInterface::parseAlltimeCareerEntry()
     */
    public static function parseAlltimeCareerEntry(string $data): ?array
    {
        if (strlen($data) < self::ALLTIME_ENTRY_SIZE) {
            return null;
        }

        $playerName = trim(substr($data, 0, 33));
        if ($playerName === '' || $playerName === '0') {
            return null;
        }

        $carBlockId = (int) trim(substr($data, 33, 5));
        $careerTotal = (int) trim(substr($data, 38, 5));
        $statRaw = (int) trim(substr($data, 43, 6));
        $teamOfRecord = (int) trim(substr($data, 49, 2));

        // Empty slots: JSB fills unused ranking positions with recycled memory.
        // Valid entries always have nonzero stat values and alphabetic-only names.
        if ($statRaw === 0) {
            return null;
        }
        if (preg_match('/\d/', $playerName) === 1) {
            return null;
        }

        return [
            'player_name' => $playerName,
            'car_block_id' => $carBlockId,
            'career_total' => $careerTotal,
            'stat_raw' => $statRaw,
            'team_of_record' => $teamOfRecord,
        ];
    }

    /**
     * @see RcbFileParserInterface::parseCurrentSeasonEntry()
     */
    public static function parseCurrentSeasonEntry(string $data): ?array
    {
        if (strlen($data) < self::SEASON_ENTRY_SIZE) {
            return null;
        }

        // Player record is first 45 chars
        $posAndName = trim(substr($data, 0, 33));
        if ($posAndName === '' || $posAndName === '0') {
            return null;
        }

        // Extract position code (first 1-2 chars before first space)
        $position = '';
        $playerName = $posAndName;
        $spacePos = strpos($posAndName, ' ');
        if ($spacePos !== false && $spacePos <= 2) {
            $position = substr($posAndName, 0, $spacePos);
            $playerName = trim(substr($posAndName, $spacePos + 1));
        }

        $carBlockId = (int) trim(substr($data, 33, 5));
        $statValue = (int) trim(substr($data, 38, 3));
        $seasonYear = (int) trim(substr($data, 41, 4));

        // Empty slots: JSB fills unused ranking positions with recycled memory.
        // Valid entries always have nonzero stat values and alphabetic-only names.
        if ($playerName === '' || $statValue === 0) {
            return null;
        }
        if (preg_match('/\d/', $playerName) === 1) {
            return null;
        }

        return [
            'player_name' => $playerName,
            'player_position' => $position,
            'car_block_id' => $carBlockId,
            'stat_value' => $statValue,
            'season_year' => $seasonYear,
        ];
    }

    /**
     * Decode a raw stat value into its actual value.
     *
     * Per-game averages: raw / 100 (e.g., 3611 → 36.11)
     * Percentages: raw / 10000 (e.g., 6708 → 0.6708)
     *
     * @param int $raw Raw encoded value from the file
     * @param string $statCategory Stat category to determine encoding
     * @return float Decoded stat value
     */
    public static function decodeStatValue(int $raw, string $statCategory): float
    {
        if (in_array($statCategory, self::PERCENTAGE_STATS, true)) {
            return $raw / 10000.0;
        }
        return $raw / 100.0;
    }
}
