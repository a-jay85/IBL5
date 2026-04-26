<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .dra (Draft Results) text files.
 *
 * The .dra format is a cumulative text file containing all draft results
 * across multiple seasons, with year headers, round/pick markers, and
 * team:position player entries.
 */
interface DraFileParserInterface
{
    /**
     * Parse raw .dra text data.
     *
     * @return list<array{draft_year: int, picks: list<array{round: int, pick: int, team_name: string, pos: string, player_name: string}>}>
     * @throws \RuntimeException If data cannot be parsed
     */
    public static function parse(string $data): array;

    /**
     * Parse a complete .dra file.
     *
     * @param string $filePath Path to the .dra file
     * @return list<array{draft_year: int, picks: list<array{round: int, pick: int, team_name: string, pos: string, player_name: string}>}>
     * @throws \RuntimeException If file cannot be read
     */
    public static function parseFile(string $filePath): array;
}
