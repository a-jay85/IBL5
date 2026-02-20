<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .asw (All-Star Weekend) text files.
 *
 * The .asw format contains All-Star rosters, 3-point shootout participants/scores,
 * and slam dunk contest participants/scores.
 */
interface AswFileParserInterface
{
    /**
     * Parse a complete .asw file.
     *
     * @param string $filePath Path to the .asw file
     * @return array{rosters: array{allstar_1: list<int>, allstar_2: list<int>, rookie_1: list<int>, rookie_2: list<int>, three_point: list<int>, dunk_contest: list<int>}, scores: array{dunk_round1: list<int>, dunk_finals: list<int>, three_pt_round1: list<int>, three_pt_semis: list<int>, three_pt_finals: list<int>}}
     * @throws \RuntimeException If file cannot be read
     */
    public static function parseFile(string $filePath): array;
}
