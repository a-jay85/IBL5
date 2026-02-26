<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for read-modify-write operations on JSB .plr (Player) files.
 *
 * The .plr file contains fields not stored in the database (NBA stats, morale, engine state).
 * The writer reads an existing .plr file, modifies only the fields that change via the website,
 * and produces a new file with all JSB-internal data preserved.
 */
interface PlrFileWriterInterface
{
    /**
     * Read the raw content of a .plr file.
     *
     * @param string $filePath Path to the .plr file
     * @return string Raw file content
     * @throws \RuntimeException If file cannot be read
     */
    public static function readFile(string $filePath): string;

    /**
     * Split .plr content into individual lines, preserving CRLF delimiters.
     *
     * @param string $content Raw file content
     * @return list<string> Lines WITHOUT the CRLF delimiter
     */
    public static function splitIntoLines(string $content): array;

    /**
     * Build a map of line index → pid for valid player records.
     *
     * Only includes lines where ordinal <= 1440 AND pid != 0.
     *
     * @param list<string> $lines Lines from splitIntoLines()
     * @return array<int, int> Map of line index → pid
     */
    public static function indexPlayerRecords(array $lines): array;

    /**
     * Apply field changes to a single player record line.
     *
     * Uses substr_replace to overlay formatted values at specific offsets.
     * Asserts that output length equals input length.
     *
     * @param string $line The 607-byte player record
     * @param array<string, int> $changes Map of field name → new value
     * @return string Modified record, same length as input
     * @throws \RuntimeException If output length differs from input length
     */
    public static function applyChangesToRecord(string $line, array $changes): string;

    /**
     * Reassemble lines back into file content with CRLF delimiters.
     *
     * @param list<string> $lines Lines to join
     * @return string File content with CRLF line endings
     */
    public static function assembleFile(array $lines): string;

    /**
     * Write content to a file atomically (temp file + rename).
     *
     * @param string $content File content to write
     * @param string $outputPath Destination file path
     * @throws \RuntimeException If write fails
     */
    public static function writeFile(string $content, string $outputPath): void;
}
