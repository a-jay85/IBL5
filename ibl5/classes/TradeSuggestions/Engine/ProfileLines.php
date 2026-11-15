<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\SlotMapper;

/**
 * The card's before/after lines for the GM's team, from this season's
 * profile delta only (offset 0), e.g. "Defensive rebounding 24th -> 11th".
 * Every number is a league rank, never a player score.
 */
final class ProfileLines
{
    public const int MIN_PLACES = 2;
    public const int MAX_LINES = 4;
    public const int MAX_DIMENSION_LINES = 3;

    /**
     * @param array<int, ProfileDelta> $deltaByOffset offset => delta
     * @return list<string>
     */
    public static function forUser(array $deltaByOffset): array
    {
        $delta = $deltaByOffset[0] ?? null;
        if ($delta === null) {
            return [];
        }

        $lines = [];
        foreach (array_slice($delta->movedDimensions(self::MIN_PLACES), 0, self::MAX_DIMENSION_LINES) as $key) {
            $pair = $delta->dimensionRanks[$key];
            $label = ucfirst(ReasonWriter::DIMENSION_LABELS[$key] ?? $key);
            $lines[] = sprintf('%s %s -> %s', $label, self::ordinal($pair['before']), self::ordinal($pair['after']));
        }

        foreach (SlotMapper::SLOTS as $slot) {
            if (count($lines) >= self::MAX_LINES) {
                break;
            }
            $before = $delta->starterRanks[$slot]['before'] ?? null;
            $after = $delta->starterRanks[$slot]['after'] ?? null;
            if ($before === null || $after === null || abs($after - $before) < self::MIN_PLACES) {
                continue;
            }
            $lines[] = sprintf('%s starter %s -> %s', $slot, self::ordinal($before), self::ordinal($after));
        }

        return $lines;
    }

    private static function ordinal(int $n): string
    {
        $mod100 = $n % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return $n . 'th';
        }

        return $n . match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
