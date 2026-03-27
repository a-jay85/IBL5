<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\PlbFileParserInterface;

/**
 * Parser for JSB .plb (Depth Chart) text files.
 *
 * Each line represents one team (32 lines total, indexed 0-31).
 * Each line contains 30 player slots × 12 chars = 360 chars minimum.
 * Per-slot layout (12 chars): minutes(2) | of(2) | df(2) | oi(2) | di(2) | bh(2)
 */
class PlbFileParser implements PlbFileParserInterface
{
    private const TEAMS_PER_FILE = 32;
    private const SLOTS_PER_TEAM = 30;
    private const CHARS_PER_SLOT = 12;
    private const MIN_LINE_LENGTH = 360; // 30 × 12

    /**
     * @see PlbFileParserInterface::parseFile()
     */
    public static function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("PLB file not found: {$filePath}");
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read PLB file: {$filePath}");
        }

        // Normalize line endings (CRLF → LF)
        $contents = str_replace("\r\n", "\n", $contents);
        $lines = explode("\n", $contents);

        /** @var array<int, list<array{slot_index: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}>> $result */
        $result = [];

        $lineCount = min(count($lines), self::TEAMS_PER_FILE);
        for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
            $line = $lines[$lineIndex];

            if (strlen($line) < self::MIN_LINE_LENGTH) {
                continue;
            }

            $slots = [];
            for ($slot = 0; $slot < self::SLOTS_PER_TEAM; $slot++) {
                $offset = $slot * self::CHARS_PER_SLOT;

                $slots[] = [
                    'slot_index' => $slot,
                    'dc_minutes' => (int) substr($line, $offset, 2),
                    'dc_of' => (int) substr($line, $offset + 2, 2),
                    'dc_df' => (int) substr($line, $offset + 4, 2),
                    'dc_oi' => (int) substr($line, $offset + 6, 2),
                    'dc_di' => (int) substr($line, $offset + 8, 2),
                    'dc_bh' => (int) substr($line, $offset + 10, 2),
                ];
            }

            $result[$lineIndex] = $slots;
        }

        return $result;
    }
}
