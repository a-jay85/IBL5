<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Builds the Discord thread title for a sim, for example "2008-09, SIM #10".
 */
final class PublishThreadTitle
{
    /**
     * Season ending year for a date: the calendar year, plus one from September on.
     * Same rule SimRecapContextRepository uses for its season_year pre-filter.
     */
    public static function seasonEndingYearForDate(string $ymd): int
    {
        /** @infection-ignore-all Equivalent: (int) stops at the first '-', so a longer substr, the whole date, or no cast (PHP adds and compares numeric strings as numbers) gives the same year. */
        $year = (int) substr($ymd, 0, 4);
        /** @infection-ignore-all Equivalent: (int) stops at the second '-', and '09' >= 9 compares as a number, so a longer substr or no cast gives the same month test. */
        $month = (int) substr($ymd, 5, 2);

        return $year + ($month >= 9 ? 1 : 0);
    }

    /**
     * @throws \InvalidArgumentException when no override is given and the sim number is below 1
     */
    public static function build(int $seasonEndingYear, int $phaseSimNumber, ?string $override): string
    {
        if ($override !== null) {
            $trimmed = trim($override);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }
        if ($phaseSimNumber < 1) {
            throw new \InvalidArgumentException('Phase sim number must be at least 1 when no title override is set');
        }

        return sprintf('%d-%02d, SIM #%d', $seasonEndingYear - 1, $seasonEndingYear % 100, $phaseSimNumber);
    }
}
