<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Value object mapping .plr ordinals to player identity (pid, name).
 *
 * Built once per season from the HEAT-end .plr file. Used by the PLB import
 * flow to resolve depth chart slot positions to actual players.
 *
 * Ordinal math: ordinal = (teamid - 1) * 30 + slotIndex + 1
 * where teamid is 1-based (team 1 = ordinals 1-30, team 2 = 31-60, etc.)
 */
final class PlrOrdinalMap
{
    private const MAX_ORDINAL = 1440; // 48 teams × 30 slots (only first 32 teams used in practice)
    private const SLOTS_PER_TEAM = 30;

    // .plr line field offsets and widths
    private const ORDINAL_OFFSET = 0;
    private const ORDINAL_WIDTH = 4;
    private const NAME_OFFSET = 4;
    private const NAME_WIDTH = 32;
    private const PID_OFFSET = 38;
    private const PID_WIDTH = 6;

    /** @var array<int, array{pid: int, name: string}> Key = ordinal (1-1440) */
    private array $map;

    /**
     * @param array<int, array{pid: int, name: string}> $map
     */
    private function __construct(array $map)
    {
        $this->map = $map;
    }

    /**
     * Build an ordinal map from a .plr file.
     *
     * Reads only the 3 fields needed (ordinal, name, pid) from each line.
     * This is intentional minimal duplication of PlrLineParser::parse() —
     * only 3 of 70+ fields are needed, and constructing a full PlrParserService
     * requires DB dependencies not needed here.
     *
     * @throws \RuntimeException If file cannot be read
     */
    public static function fromPlrFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("PLR file not found: {$filePath}");
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read PLR file: {$filePath}");
        }

        // Normalize line endings
        $contents = str_replace("\r\n", "\n", $contents);
        $lines = explode("\n", $contents);

        $map = [];

        foreach ($lines as $line) {
            if (strlen($line) < self::PID_OFFSET + self::PID_WIDTH) {
                continue;
            }

            $ordinal = (int) trim(substr($line, self::ORDINAL_OFFSET, self::ORDINAL_WIDTH));
            if ($ordinal <= 0 || $ordinal > self::MAX_ORDINAL) {
                continue;
            }

            $pid = (int) trim(substr($line, self::PID_OFFSET, self::PID_WIDTH));
            if ($pid === 0) {
                continue;
            }

            $rawName = trim(substr($line, self::NAME_OFFSET, self::NAME_WIDTH));
            $name = mb_convert_encoding($rawName, 'UTF-8', 'Windows-1252');

            $map[$ordinal] = [
                'pid' => $pid,
                'name' => $name,
            ];
        }

        return new self($map);
    }

    /**
     * Create an empty map (fallback when .plr is unavailable).
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Look up the player at a given team/slot position.
     *
     * @param int $teamid 1-based team ID (1-32)
     * @param int $slotIndex 0-based slot within team (0-29)
     * @return array{pid: int, name: string}|null Player info or null if not found
     */
    public function getSlotPlayer(int $teamid, int $slotIndex): ?array
    {
        $ordinal = ($teamid - 1) * self::SLOTS_PER_TEAM + $slotIndex + 1;

        return $this->map[$ordinal] ?? null;
    }

    /**
     * Get the number of mapped players.
     */
    public function count(): int
    {
        return count($this->map);
    }
}
