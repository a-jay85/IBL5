<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\AwaFileParserInterface;

/**
 * Parser for JSB .awa (Awards) binary files.
 *
 * Extracts stat leader PIDs across multiple seasons from the binary format.
 * Each 1,000-byte block contains scoring, rebounding, assists, steals, and blocks
 * leaders for ranks 1-5.
 *
 * @see AwaFileParserInterface
 */
class AwaFileParser implements AwaFileParserInterface
{
    public const BLOCK_SIZE = 1000;
    public const TOTAL_BLOCKS = 50;
    public const FILE_SIZE = 50000;

    /** Byte offset of starting year in block 0 */
    private const STARTING_YEAR_OFFSET = 0;
    private const STARTING_YEAR_WIDTH = 4;

    /** Byte offset to check if a block is active (position-4 PID) */
    private const ACTIVE_CHECK_OFFSET = 32;

    /**
     * Section offsets within each block.
     * Sections 0/1/2/3 correspond to ranks 1/3/4/5.
     */
    private const SECTION_OFFSETS = [
        0 => 0,    // Rank 1
        1 => 268,  // Rank 3
        2 => 402,  // Rank 4
        3 => 536,  // Rank 5
    ];

    private const SECTION_RANKS = [
        0 => 1,
        1 => 3,
        2 => 4,
        3 => 5,
    ];

    /** Offset of scoring leader PID within a section */
    private const SCORING_PID_OFFSET = 32;

    /** Offset of stat chain within a section */
    private const STAT_CHAIN_OFFSET = 40;

    /** Size of each entry in the stat chain */
    private const CHAIN_ENTRY_SIZE = 12;

    /** Rank 2 specific offsets (extended region of section 0) */
    private const RANK2_SCORING_PID_OFFSET = 166;
    private const RANK2_STAT_CHAIN_OFFSET = 172;

    /** @var list<string> Stat category names in chain order */
    private const STAT_CATEGORIES = [
        'Scoring Leader',
        'Rebounding Leader',
        'Assists Leader',
        'Steals Leader',
        'Blocks Leader',
    ];

    /**
     * @see AwaFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("AWA file not found: {$filePath}");
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new \RuntimeException("Failed to read AWA file: {$filePath}");
        }

        $fileSize = strlen($data);
        if ($fileSize < self::FILE_SIZE) {
            throw new \RuntimeException(
                'Invalid .awa file: expected ' . self::FILE_SIZE . ' bytes, got ' . $fileSize
            );
        }

        // Block 0: header — starting year
        $startingYear = (int) trim(substr($data, self::STARTING_YEAR_OFFSET, self::STARTING_YEAR_WIDTH));

        $seasons = [];
        for ($i = 1; $i < self::TOTAL_BLOCKS; $i++) {
            $blockData = substr($data, $i * self::BLOCK_SIZE, self::BLOCK_SIZE);
            $parsed = self::parseBlock($blockData, $i, $startingYear);
            if ($parsed !== null) {
                $seasons[] = $parsed;
            }
        }

        return [
            'starting_year' => $startingYear,
            'seasons' => $seasons,
        ];
    }

    /**
     * @see AwaFileParserInterface::parseBlock()
     */
    public static function parseBlock(string $data, int $blockIndex, int $startingYear): ?array
    {
        if (strlen($data) < self::BLOCK_SIZE) {
            return null;
        }

        // Check if block is active — position-4 PID at offset 32 must be non-zero
        $checkPid = self::readInt32($data, self::ACTIVE_CHECK_OFFSET);
        if ($checkPid === 0) {
            return null;
        }

        $year = $startingYear + $blockIndex;

        /** @var array<string, list<array{rank: int, pid: int}>> $statLeaders */
        $statLeaders = [];
        foreach (self::STAT_CATEGORIES as $category) {
            $statLeaders[$category] = [];
        }

        // Parse sections 0-3 (ranks 1, 3, 4, 5)
        foreach (self::SECTION_OFFSETS as $sectionIndex => $sectionOffset) {
            $rank = self::SECTION_RANKS[$sectionIndex];
            self::parseSectionLeaders($data, $sectionOffset, $rank, $statLeaders);
        }

        // Parse rank 2 (special layout in section 0's extended region)
        self::parseRank2Leaders($data, $statLeaders);

        // Sort each category by rank
        foreach ($statLeaders as &$leaders) {
            usort($leaders, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);
        }

        return [
            'year' => $year,
            'stat_leaders' => $statLeaders,
        ];
    }

    /**
     * Parse stat leaders from a standard section (ranks 1, 3, 4, 5).
     *
     * Layout: scoring PID at section_start+32, stat chain at section_start+40.
     * Chain entries: stat(4) + PID(4) + blank(2) + team(2) = 12 bytes each.
     * Chain order: scoring_stat → reb_PID, reb_stat → ast_PID, ast_stat → stl_PID, stl_stat → blk_PID, blk_stat.
     *
     * @param array<string, list<array{rank: int, pid: int}>> $statLeaders
     */
    private static function parseSectionLeaders(string $data, int $sectionOffset, int $rank, array &$statLeaders): void
    {
        // Scoring leader PID is at section_start + 32
        $scoringPid = self::readInt32($data, $sectionOffset + self::SCORING_PID_OFFSET);
        if ($scoringPid > 0) {
            $statLeaders['Scoring Leader'][] = ['rank' => $rank, 'pid' => $scoringPid];
        }

        // Stat chain at section_start + 40
        // Entry layout: stat(4) + PID(4) + blank(2) + team(2)
        // The chain provides the NEXT category's PID after the stat value
        $chainBase = $sectionOffset + self::STAT_CHAIN_OFFSET;

        // Entry 0: scoring_stat(4) → reb_PID(4) → blank(2) → team(2)
        $rebPid = self::readInt32($data, $chainBase + 4);
        if ($rebPid > 0) {
            $statLeaders['Rebounding Leader'][] = ['rank' => $rank, 'pid' => $rebPid];
        }

        // Entry 1: reb_stat(4) → ast_PID(4) → blank(2) → team(2)
        $astPid = self::readInt32($data, $chainBase + self::CHAIN_ENTRY_SIZE + 4);
        if ($astPid > 0) {
            $statLeaders['Assists Leader'][] = ['rank' => $rank, 'pid' => $astPid];
        }

        // Entry 2: ast_stat(4) → stl_PID(4) → blank(2) → team(2)
        $stlPid = self::readInt32($data, $chainBase + 2 * self::CHAIN_ENTRY_SIZE + 4);
        if ($stlPid > 0) {
            $statLeaders['Steals Leader'][] = ['rank' => $rank, 'pid' => $stlPid];
        }

        // Entry 3: stl_stat(4) → blk_PID(4) → blank(2) → team(2)
        $blkPid = self::readInt32($data, $chainBase + 3 * self::CHAIN_ENTRY_SIZE + 4);
        if ($blkPid > 0) {
            $statLeaders['Blocks Leader'][] = ['rank' => $rank, 'pid' => $blkPid];
        }
    }

    /**
     * Parse rank 2 stat leaders from the extended region.
     *
     * Layout differs: team(2) + stat(4) + PID(4) + blank(2) = 12 bytes each.
     *
     * @param array<string, list<array{rank: int, pid: int}>> $statLeaders
     */
    private static function parseRank2Leaders(string $data, array &$statLeaders): void
    {
        // Scoring leader 2nd PID at offset 166
        $scoringPid = self::readInt32($data, self::RANK2_SCORING_PID_OFFSET);
        if ($scoringPid > 0) {
            $statLeaders['Scoring Leader'][] = ['rank' => 2, 'pid' => $scoringPid];
        }

        // Stat chain at offset 172
        // Entry layout: team(2) + stat(4) + PID(4) + blank(2)
        $chainBase = self::RANK2_STAT_CHAIN_OFFSET;

        // Entry 0: team(2) + scoring_stat(4) → reb_PID(4) → blank(2)
        $rebPid = self::readInt32($data, $chainBase + 6);
        if ($rebPid > 0) {
            $statLeaders['Rebounding Leader'][] = ['rank' => 2, 'pid' => $rebPid];
        }

        // Entry 1: team(2) + reb_stat(4) → ast_PID(4) → blank(2)
        $astPid = self::readInt32($data, $chainBase + self::CHAIN_ENTRY_SIZE + 6);
        if ($astPid > 0) {
            $statLeaders['Assists Leader'][] = ['rank' => 2, 'pid' => $astPid];
        }

        // Entry 2: team(2) + ast_stat(4) → stl_PID(4) → blank(2)
        $stlPid = self::readInt32($data, $chainBase + 2 * self::CHAIN_ENTRY_SIZE + 6);
        if ($stlPid > 0) {
            $statLeaders['Steals Leader'][] = ['rank' => 2, 'pid' => $stlPid];
        }

        // Entry 3: team(2) + stl_stat(4) → blk_PID(4) → blank(2)
        $blkPid = self::readInt32($data, $chainBase + 3 * self::CHAIN_ENTRY_SIZE + 6);
        if ($blkPid > 0) {
            $statLeaders['Blocks Leader'][] = ['rank' => 2, 'pid' => $blkPid];
        }
    }

    /**
     * Read a 4-byte little-endian unsigned integer from binary data.
     */
    private static function readInt32(string $data, int $offset): int
    {
        if ($offset + 4 > strlen($data)) {
            return 0;
        }

        $unpacked = unpack('V', substr($data, $offset, 4));
        if ($unpacked === false) {
            return 0;
        }

        /** @var int $value */
        $value = $unpacked[1];
        return $value;
    }
}
