<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .plb (Depth Chart) text files.
 *
 * The .plb format contains 32 lines (one per team slot), each with 30 player slots.
 * Each slot is 12 characters: minutes(2) | of(2) | df(2) | oi(2) | di(2) | bh(2).
 */
interface PlbFileParserInterface
{
    /**
     * Parse raw .plb text data.
     *
     * @return array<int, list<array{slot_index: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}>>
     * @throws \RuntimeException If data cannot be parsed
     */
    public static function parse(string $data): array;

    /**
     * Parse a complete .plb file.
     *
     * @param string $filePath Path to the .plb file
     * @return array<int, list<array{slot_index: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}>>
     *         Outer key = line index (0-31), inner = 30 slots per team
     * @throws \RuntimeException If file cannot be read
     */
    public static function parseFile(string $filePath): array;
}
