<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * Compares two players rating by rating at one slot (F3).
 *
 * Each relevant rating is a vote. A rating is decided for one side when it
 * beats the other by more than TOLERANCE points. No weighted sum exists.
 */
final class DominanceComparator
{
    public const float TOLERANCE = 2.0;
    public const float BETTER_SHARE = 0.6;

    public static function compare(RatingSet $a, RatingSet $b, string $slot): Dominance
    {
        $record = self::record($a, $b, $slot);
        $winsA = $record['wins'];
        $winsB = $record['losses'];
        $decided = $winsA + $winsB;

        if ($decided > 0 && $winsA > $winsB && $winsA >= self::BETTER_SHARE * $decided) {
            return Dominance::Better;
        }
        if ($decided > 0 && $winsB > $winsA && $winsB >= self::BETTER_SHARE * $decided) {
            return Dominance::Worse;
        }

        return Dominance::Mixed;
    }

    /**
     * Counts the relevant ratings `a` wins and loses against `b`.
     *
     * @return array{wins: int, losses: int}
     */
    public static function record(RatingSet $a, RatingSet $b, string $slot): array
    {
        $wins = 0;
        $losses = 0;
        foreach (SlotRelevance::keysFor($slot) as $key) {
            $delta = $a->get($key) - $b->get($key);
            if (!RatingDirection::higherIsBetter($key)) {
                $delta = -$delta;
            }
            if ($delta > self::TOLERANCE) {
                $wins++;
            } elseif ($delta < -self::TOLERANCE) {
                $losses++;
            }
        }

        return ['wins' => $wins, 'losses' => $losses];
    }
}
