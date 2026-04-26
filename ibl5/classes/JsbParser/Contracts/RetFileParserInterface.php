<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .ret (Retired Players) text files.
 *
 * The .ret format is a per-season text file listing retired players,
 * one per line, with the JSB player ID as the last whitespace token.
 */
interface RetFileParserInterface
{
    /**
     * Parse raw .ret text data.
     *
     * @return list<array{jsb_pid: int, player_name: string}>
     * @throws \RuntimeException If data cannot be parsed
     */
    public static function parse(string $data): array;

    /**
     * Parse a complete .ret file.
     *
     * @param string $filePath Path to the .ret file
     * @return list<array{jsb_pid: int, player_name: string}>
     * @throws \RuntimeException If file cannot be read
     */
    public static function parseFile(string $filePath): array;
}
