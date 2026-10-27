<?php

declare(strict_types=1);

namespace OnThisDay;

use RecordHolders\IblSeasonDateHelper;

/**
 * Month-day window and season-year arithmetic for the "on this day" lookup.
 *
 * Pure logic: no I/O.
 */
final class OnThisDayCalendar
{
    /**
     * Widening search radii in days. Capped at 7: past a week the "this week in
     * league history" label stops meaning anything.
     */
    public const WIDENING_RADII = [0, 1, 3, 7];

    private const REFERENCE_LEAP_YEAR = '2000';

    /**
     * Month-day strings (`MM-DD`) within $radius days of the center date's month/day.
     *
     * The window is laid out on leap year 2000, so Feb 29 is a real day: a Feb 29
     * center at radius 0 matches only Feb 29, and a Mar 1 center at radius 1
     * includes `02-29`. Year-end wraps naturally.
     *
     * @return list<string>
     */
    public function monthDayWindow(string $centerDate, int $radius): array
    {
        $center = new \DateTimeImmutable($centerDate);
        $reference = new \DateTimeImmutable(self::REFERENCE_LEAP_YEAR . '-' . $center->format('m-d'));

        $window = [];
        for ($offset = -$radius; $offset <= $radius; $offset++) {
            $window[] = $reference->modify(sprintf('%+d days', $offset))->format('m-d');
        }

        return $window;
    }

    public function seasonYearOf(string $date): int
    {
        return IblSeasonDateHelper::dateToSeasonEndingYear($date);
    }

    /**
     * Seasons elapsed since the moment, or null for the current or a future season.
     */
    public function seasonsAgo(int $currentSeasonYear, int $momentSeasonYear): ?int
    {
        if ($momentSeasonYear >= $currentSeasonYear) {
            return null;
        }

        return $currentSeasonYear - $momentSeasonYear;
    }
}
