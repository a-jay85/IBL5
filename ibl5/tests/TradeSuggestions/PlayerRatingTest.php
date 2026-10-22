<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\PlayerRating;

/**
 * ovr is the 21 rating fields as a percent of the league max, averaged: the
 * measure Negotiation\ExtensionContractDemandCalculator prices players with.
 * v1 used talent*2 + skill*2 + intangibles (1-5 grades, so 15-24).
 */
class PlayerRatingTest extends TestCase
{
    public function testAveragesEachRatingAsShareOfLeagueMax(): void
    {
        $maximums = [];
        $row = [];
        foreach (PlayerRating::COLUMNS as $key => $column) {
            $maximums[$key] = 80;
            $row[$column] = 40;
        }
        // One rating at the league max.
        $row['r_fga'] = 80;

        self::assertSame((int) round((20 * 0.5 + 1.0) / 21 * 100), PlayerRating::overall($row, $maximums));
    }

    public function testIgnoresScoutingGrades(): void
    {
        $maximums = array_fill_keys(array_keys(PlayerRating::COLUMNS), 100);
        $row = array_fill_keys(array_values(PlayerRating::COLUMNS), 60);

        $withGrades = $row + ['talent' => 5, 'skill' => 5, 'intangibles' => 5];

        self::assertSame(60, PlayerRating::overall($row, $maximums));
        self::assertSame(60, PlayerRating::overall($withGrades, $maximums));
    }

    public function testCapsAtLeagueMaxAndSkipsZeroMax(): void
    {
        $maximums = array_fill_keys(array_keys(PlayerRating::COLUMNS), 50);
        $maximums['td'] = 0;
        $row = array_fill_keys(array_values(PlayerRating::COLUMNS), 90);

        self::assertSame((int) round(20 / 21 * 100), PlayerRating::overall($row, $maximums));
    }
}
