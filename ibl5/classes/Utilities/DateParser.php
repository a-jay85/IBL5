<?php

declare(strict_types=1);

namespace Utilities;

/**
 * DateParser - Parse dates from schedule files with season phase logic
 * 
 * Handles special date formats from JSB exports including "Post" dates
 * and applies season phase adjustments for preseason, HEAT, and Olympics.
 */
class DateParser
{
    /**
     * Parse a raw date string from schedule files
     * 
     * @param string $rawDate The raw date string (e.g., "November 1, 2023" or "Post 15, 2024")
     * @param string $phase The current season phase (Preseason, HEAT, Regular Season, etc.)
     * @param int $beginningYear The beginning year of the season
     * @param int $endingYear The ending year of the season
     * @param string $league The current league (IBL or olympics)
     * @return array{date: string, year: int, month: int, day: int}|null
     */
    public static function extractDate(
        string $rawDate,
        string $phase,
        int $beginningYear,
        int $endingYear,
        string $league = 'IBL'
    ): ?array {
        if (empty($rawDate)) {
            return null;
        }

        // Convert "Post" dates to June
        if (substr($rawDate, 0, 4) === "Post") {
            $rawDate = substr_replace($rawDate, 'June', 0, 4);
        }

        $timestamp = strtotime($rawDate);
        if ($timestamp === false) {
            return null;
        }

        $month = (int) ltrim(date('m', $timestamp), '0');
        $day = (int) ltrim(date('d', $timestamp), '0');
        $year = (int) date('Y', $timestamp);

        // Apply phase adjustments
        if ($phase === "Preseason") {
            $beginningYear = \Season::IBL_PRESEASON_YEAR;
            $endingYear = \Season::IBL_PRESEASON_YEAR + 1;
        } elseif ($phase === "HEAT") {
            if ($month === 11) {
                $month = \Season::IBL_HEAT_MONTH;
            }
        }

        // Olympics override
        if (strtolower($league) === 'olympics') {
            $month = \Season::IBL_OLYMPICS_MONTH;
        }

        // Determine year based on month
        if ($month < \Season::IBL_HEAT_MONTH) {
            $year = $endingYear;
        } else {
            $year = $beginningYear;
        }

        return [
            'date' => $year . "-" . $month . "-" . $day,
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ];
    }
}
