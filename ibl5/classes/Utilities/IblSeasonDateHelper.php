<?php

declare(strict_types=1);

namespace Utilities;

/**
 * Shared helper for IBL season date logic.
 *
 * IBL seasons span two calendar years (Sep-June).
 * Preseason is September, HEAT is October, playoffs are June, regular season is Nov-May.
 */
final class IblSeasonDateHelper
{
    /**
     * Convert a date to its IBL season ending year.
     *
     * Sep-Dec games belong to the season that ends the following year.
     * Jan-Aug games belong to the season ending that same year.
     */
    public static function dateToSeasonEndingYear(string $date): int
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return 0;
        }
        $month = (int) date('n', $timestamp);
        $year = (int) date('Y', $timestamp);

        return $month >= 9 ? $year + 1 : $year;
    }

    /**
     * Determine the game type from a date.
     *
     * September → 'preseason', October → 'heat', June → 'playoffs', all other months → 'regularSeason'.
     */
    public static function getGameTypeFromDate(string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return 'regularSeason';
        }
        $month = (int) date('n', $timestamp);

        if ($month === 9) {
            return 'preseason';
        }
        if ($month === 10) {
            return 'heat';
        }
        if ($month === 6) {
            return 'playoffs';
        }
        return 'regularSeason';
    }
}
