<?php

declare(strict_types=1);

namespace Database;

/**
 * Fixed-width bound placeholder lists, so an `IN (...)` clause stays a literal
 * instead of being built from a runtime count.
 */
final class SqlPlaceholders
{
    public const PID_LIST_WIDTH = 64;

    /** 64 comma-separated placeholders; keep in step with PID_LIST_WIDTH. */
    public const PID_LIST = '?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?';

    /** 64 `i` bind types; keep in step with PID_LIST_WIDTH. */
    public const PID_LIST_TYPES = 'iiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii';

    /** Padding value; no `pid` column holds a negative id. */
    private const PID_PAD = -1;

    /**
     * Pad a pid list to PID_LIST_WIDTH so it binds to PID_LIST.
     *
     * `pid IN (5, 7, -1, -1, ...)` selects the same rows as `pid IN (5, 7)`.
     *
     * @param list<int> $pids
     * @return list<int> exactly PID_LIST_WIDTH entries
     * @throws \InvalidArgumentException when $pids is empty or longer than PID_LIST_WIDTH
     */
    public static function padPidList(array $pids): array
    {
        $count = count($pids);
        if ($count === 0 || $count > self::PID_LIST_WIDTH) {
            throw new \InvalidArgumentException('pid list must hold 1-' . self::PID_LIST_WIDTH . ' ids, got ' . $count);
        }

        return array_merge(array_values($pids), array_fill(0, self::PID_LIST_WIDTH - $count, self::PID_PAD));
    }
}
