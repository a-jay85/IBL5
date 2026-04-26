<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .awa (Awards) binary files.
 *
 * The .awa format stores stat leader data across multiple seasons in 50 blocks
 * of 1,000 bytes each. Block 0 is a header; blocks 1-N contain per-season data.
 */
interface AwaFileParserInterface
{
    /**
     * Parse raw .awa binary data.
     *
     * @return array{starting_year: int, seasons: list<array{year: int, stat_leaders: array<string, list<array{rank: int, pid: int}>>}>}
     * @throws \RuntimeException If data has invalid structure
     */
    public static function parse(string $data): array;

    /**
     * Parse a complete .awa file.
     *
     * @param string $filePath Path to the .awa file
     * @return array{starting_year: int, seasons: list<array{year: int, stat_leaders: array<string, list<array{rank: int, pid: int}>>}>}
     * @throws \RuntimeException If file cannot be read or has invalid structure
     */
    public static function parseFile(string $filePath): array;

    /**
     * Parse a single 1,000-byte season block.
     *
     * @param string $data Raw binary data (1,000 bytes)
     * @param int $blockIndex Position in the file (1-based for season blocks)
     * @param int $startingYear Starting year from block 0
     * @return array{year: int, stat_leaders: array<string, list<array{rank: int, pid: int}>>}|null Null if block is empty
     */
    public static function parseBlock(string $data, int $blockIndex, int $startingYear): ?array;
}
