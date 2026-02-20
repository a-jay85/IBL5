<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\AswFileParserInterface;

/**
 * Parser for JSB .asw (All-Star Weekend) text files.
 *
 * The .asw format is 10,000 bytes of ASCII text with CRLF line endings.
 * Contains 8 sections across 112 data lines:
 *
 * - Sections 1-4: All-Star and Rookie Challenge rosters (player IDs)
 * - Sections 5-6: 3-Point Shootout and Dunk Contest participants
 * - Section 7: Dunk Contest scores (stored as score * 10)
 * - Section 8: 3-Point Shootout scores (raw count of makes)
 *
 * @see /docs/JSB_FILE_FORMATS.md
 */
class AswFileParser implements AswFileParserInterface
{
    public const FILE_SIZE = 10000;
    public const TOTAL_DATA_LINES = 112;

    // Section line ranges (0-indexed)
    public const ALLSTAR_1_START = 0;
    public const ALLSTAR_1_END = 15;
    public const ALLSTAR_2_START = 16;
    public const ALLSTAR_2_END = 30;
    public const ROOKIE_1_START = 31;
    public const ROOKIE_1_END = 45;
    public const ROOKIE_2_START = 46;
    public const ROOKIE_2_END = 60;
    public const THREE_POINT_START = 61;
    public const THREE_POINT_END = 70;
    public const DUNK_CONTEST_START = 71;
    public const DUNK_CONTEST_END = 80;
    public const DUNK_SCORES_START = 81;
    public const DUNK_SCORES_END = 96;
    public const THREE_PT_SCORES_START = 97;
    public const THREE_PT_SCORES_END = 111;

    /**
     * @see AswFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("ASW file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read ASW file: {$filePath}");
        }

        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);
        $lines = explode("\n", $content);

        // Parse roster sections (player IDs)
        $allstar1 = self::parseRosterSection($lines, self::ALLSTAR_1_START, self::ALLSTAR_1_END);
        $allstar2 = self::parseRosterSection($lines, self::ALLSTAR_2_START, self::ALLSTAR_2_END);
        $rookie1 = self::parseRosterSection($lines, self::ROOKIE_1_START, self::ROOKIE_1_END);
        $rookie2 = self::parseRosterSection($lines, self::ROOKIE_2_START, self::ROOKIE_2_END);
        $threePoint = self::parseRosterSection($lines, self::THREE_POINT_START, self::THREE_POINT_END);
        $dunkContest = self::parseRosterSection($lines, self::DUNK_CONTEST_START, self::DUNK_CONTEST_END);

        // Parse score sections
        // Dunk scores: line 81 = header (0), 82-89 = round 1, 90-92 = finals
        $dunkRound1 = self::parseScoreSection($lines, 82, 89);
        $dunkFinals = self::parseScoreSection($lines, 90, 92);

        // 3-pt scores: 97-104 = round 1, 105-108 = semis, 109-110 = finals
        $threePtRound1 = self::parseScoreSection($lines, 97, 104);
        $threePtSemis = self::parseScoreSection($lines, 105, 108);
        $threePtFinals = self::parseScoreSection($lines, 109, 110);

        return [
            'rosters' => [
                'allstar_1' => $allstar1,
                'allstar_2' => $allstar2,
                'rookie_1' => $rookie1,
                'rookie_2' => $rookie2,
                'three_point' => $threePoint,
                'dunk_contest' => $dunkContest,
            ],
            'scores' => [
                'dunk_round1' => $dunkRound1,
                'dunk_finals' => $dunkFinals,
                'three_pt_round1' => $threePtRound1,
                'three_pt_semis' => $threePtSemis,
                'three_pt_finals' => $threePtFinals,
            ],
        ];
    }

    /**
     * Parse a roster section (player IDs) from line range.
     *
     * @param list<string> $lines All lines from the file
     * @param int $startLine Start line index (inclusive)
     * @param int $endLine End line index (inclusive)
     * @return list<int> Non-zero player IDs
     */
    private static function parseRosterSection(array $lines, int $startLine, int $endLine): array
    {
        $ids = [];
        for ($i = $startLine; $i <= $endLine; $i++) {
            if (!isset($lines[$i])) {
                continue;
            }
            $value = (int) trim($lines[$i]);
            if ($value > 0) {
                $ids[] = $value;
            }
        }
        return $ids;
    }

    /**
     * Parse a score section from line range.
     *
     * @param list<string> $lines All lines from the file
     * @param int $startLine Start line index (inclusive)
     * @param int $endLine End line index (inclusive)
     * @return list<int> Non-zero scores
     */
    private static function parseScoreSection(array $lines, int $startLine, int $endLine): array
    {
        $scores = [];
        for ($i = $startLine; $i <= $endLine; $i++) {
            if (!isset($lines[$i])) {
                continue;
            }
            $value = (int) trim($lines[$i]);
            if ($value > 0) {
                $scores[] = $value;
            }
        }
        return $scores;
    }
}
