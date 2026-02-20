<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .his (Historical Results) text files.
 *
 * The .his format contains team-by-team season results with W-L records and playoff outcomes.
 */
interface HisFileParserInterface
{
    /**
     * Parse a complete .his file.
     *
     * @param string $filePath Path to the .his file
     * @return list<array{year: int, teams: list<array{name: string, wins: int, losses: int, playoff_result: string, made_playoffs: int, playoff_round_reached: string, won_championship: int}>}>
     * @throws \RuntimeException If file cannot be read
     */
    public static function parseFile(string $filePath): array;

    /**
     * Parse a single team result line.
     *
     * @param string $line Raw text line from the .his file
     * @return array{name: string, wins: int, losses: int, year: int, playoff_result: string, made_playoffs: int, playoff_round_reached: string, won_championship: int}|null Null if line cannot be parsed
     */
    public static function parseTeamLine(string $line): ?array;
}
