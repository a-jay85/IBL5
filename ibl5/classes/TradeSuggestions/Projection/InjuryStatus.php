<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * F12 current-season injury status. `ibl_plr.injured` is days remaining, and
 * one game is played per sim day. No injury history is read.
 */
enum InjuryStatus: string
{
    case Healthy = 'healthy';
    case BackSoon = 'back_soon';
    case BackForPlayoffs = 'back_for_playoffs';
    case OutForYear = 'out_for_year';

    public const int BACK_SOON_MAX_DAYS = 10;
    public const float BACK_SOON_MAX_SHARE = 0.15;

    /** @var array<string, float> */
    public const array WEIGHT = [
        'healthy' => 1.0,
        'back_soon' => 0.9,
        'back_for_playoffs' => 0.6,
        'out_for_year' => 0.0,
    ];

    /**
     * OutForYear is checked before BackSoon, so 5 days out with 3 games left
     * is OutForYear.
     */
    public static function fromDays(int $injuredDays, int $gamesLeft, bool $regularSeason): self
    {
        if (!$regularSeason || $injuredDays <= 0) {
            return self::Healthy;
        }
        if ($gamesLeft <= 0 || $injuredDays >= $gamesLeft) {
            return self::OutForYear;
        }
        if ($injuredDays <= self::BACK_SOON_MAX_DAYS || $injuredDays <= self::BACK_SOON_MAX_SHARE * $gamesLeft) {
            return self::BackSoon;
        }

        return self::BackForPlayoffs;
    }

    /**
     * Offset-0 regular-season availability.
     */
    public function availabilityWeight(): float
    {
        return self::WEIGHT[$this->value];
    }

    /**
     * Playoff availability: a player back for the playoffs counts in full.
     */
    public function playoffWeight(): float
    {
        return $this === self::BackForPlayoffs ? 1.0 : self::WEIGHT[$this->value];
    }
}
