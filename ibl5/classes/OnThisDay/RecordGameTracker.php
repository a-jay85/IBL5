<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * Running single-game maximum per stat over a chronological stream of
 * regular-season player games.
 *
 * A line is a record only when it is strictly greater than every earlier value
 * of that stat. A stat with no earlier value never reports, a tie never counts,
 * and games in the league's first season set the maximum without reporting:
 * that season has no prior history, so its early games would set a "record"
 * every night.
 */
final class RecordGameTracker
{
    /** @var array<string, int> label => highest value seen so far */
    private array $max = [];

    public function __construct(private readonly int $firstSeasonYear)
    {
    }

    /**
     * Feed one regular-season player game in chronological order.
     * Returns the labels whose value strictly beats every earlier game.
     * The max always updates; a game in the first season never reports.
     *
     * @param array<string, int> $stats label => value (points, rebounds, assists, steals, blocks)
     * @return list<string>
     */
    public function observe(int $seasonYear, array $stats): array
    {
        $records = [];
        foreach ($stats as $label => $value) {
            if (!array_key_exists($label, $this->max)) {
                $this->max[$label] = $value;
                continue;
            }

            if ($value > $this->max[$label]) {
                if ($seasonYear > $this->firstSeasonYear) {
                    $records[] = $label;
                }
                $this->max[$label] = $value;
            }
        }

        return $records;
    }
}
