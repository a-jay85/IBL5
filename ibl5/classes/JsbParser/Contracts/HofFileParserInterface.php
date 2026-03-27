<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .hof (Hall of Fame) fixed-size files.
 *
 * The .hof format is exactly 7000 bytes, organized as 14 × 500-byte year blocks.
 * Each entry contains position, player name, JSB PID, and induction year.
 */
interface HofFileParserInterface
{
    /**
     * Parse a complete .hof file.
     *
     * @param string $filePath Path to the .hof file
     * @return list<array{jsb_pid: int, player_name: string, pos: string, induction_year: int}>
     * @throws \RuntimeException If file cannot be read or has invalid structure
     */
    public static function parseFile(string $filePath): array;
}
