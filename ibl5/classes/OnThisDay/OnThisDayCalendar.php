<?php

declare(strict_types=1);

namespace OnThisDay;

use RecordHolders\IblSeasonDateHelper;

/**
 * Sim-span month-day window and season-year arithmetic for the "on this day" lookup.
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
     * Longest sim span searched, in days. A longer span (bad data, or a long
     * break) keeps only its last MAX_SPAN_DAYS days, which end on the sim's end date.
     */
    public const MAX_SPAN_DAYS = 31;

    /**
     * Month-day strings (`MM-DD`) from $radius days before the start date's
     * month/day through $radius days after the end date's month/day.
     *
     * Laid out on leap year 2000, so Feb 29 is a real day. A span that crosses
     * Dec 31 (end month/day before start month/day) moves the start back one
     * year, so the list runs through year-end in order. A start after the end
     * collapses to the end day. A span longer than MAX_SPAN_DAYS keeps its last
     * MAX_SPAN_DAYS days. A single-day span equals the old center-date window.
     *
     * @return list<string>
     */
    public function spanWindow(string $startDate, string $endDate, int $radius): array
    {
        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        if ($start > $end) {
            $start = $end;
        }

        $first = new \DateTimeImmutable(self::REFERENCE_LEAP_YEAR . '-' . $start->format('m-d'));
        $last = new \DateTimeImmutable(self::REFERENCE_LEAP_YEAR . '-' . $end->format('m-d'));
        if ($last < $first) {
            $first = $first->modify('-1 year');
        }
        if ((int) $first->diff($last)->days >= self::MAX_SPAN_DAYS) {
            $first = $last->modify(sprintf('-%d days', self::MAX_SPAN_DAYS - 1));
        }

        $window = [];
        $stop = $last->modify(sprintf('+%d days', $radius));
        for ($day = $first->modify(sprintf('-%d days', $radius)); $day <= $stop; $day = $day->modify('+1 day')) {
            $window[] = $day->format('m-d');
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
