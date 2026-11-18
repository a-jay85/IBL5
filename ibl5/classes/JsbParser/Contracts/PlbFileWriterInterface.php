<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for writing JSB .plb (Depth Chart) text files.
 *
 * Mirrors PlbFileParserInterface: 32 team lines, 30 slots per line, and 12
 * chars per slot laid out as minutes(2) | of(2) | df(2) | oi(2) | di(2) | bh(2).
 */
interface PlbFileWriterInterface
{
    /**
     * Format one slot as 12 chars.
     *
     * @param array{dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int} $slot
     * @throws \InvalidArgumentException If a value does not fit in two chars (-9..99)
     */
    public static function formatSlot(array $slot): string;

    /**
     * Overwrite slots in raw .plb content and return content of the same length.
     *
     * @param array<int, array<int, array{dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}>> $updates
     *        Outer key = team line (0-31), inner key = slot (0-29)
     * @throws \InvalidArgumentException If a team line or slot index is out of range
     * @throws \RuntimeException If a target line is shorter than 360 chars or the length changes
     */
    public static function applySlots(string $content, array $updates): string;

    /**
     * Map a .plr ordinal to its .plb [team line, slot] position.
     *
     * @return array{0: int, 1: int}|null Null when the ordinal is outside 1..960
     */
    public static function ordinalToTeamSlot(int $ordinal): ?array;
}
