<?php

declare(strict_types=1);

namespace Rivalries;

use Rivalries\Contracts\RivalryScorerInterface;

/**
 * RivalryScorer - Pure rivalry scoring. No I/O, no clock.
 *
 * W/L counts arrive from the H2H matrix; close, recent, and playoff counts
 * arrive from the game-level repository.
 *
 * @phpstan-import-type RivalryInput from RivalryScorerInterface
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 *
 * @see RivalryScorerInterface
 */
final class RivalryScorer implements RivalryScorerInterface
{
    public const WEIGHT_PLAYOFF = 30;
    public const WEIGHT_VOLUME = 20;
    public const WEIGHT_CLOSENESS = 20;
    public const WEIGHT_CLOSE_GAMES = 15;
    public const WEIGHT_RECENCY = 15;
    public const MIN_GAMES = 10;            // pair counts as a rivalry at >= 10 games
    public const VOLUME_CAP_GAMES = 60;     // ~ 15 seasons of 4 regular-season meetings
    public const PLAYOFF_SERIES_CAP = 3;
    public const PLAYOFF_GAMES_CAP = 15;
    public const CLOSE_GAME_MARGIN = 5;     // |margin| <= 5 points (consumed by the repository SQL)
    public const RECENT_SEASONS = 5;
    public const RECENT_GAMES_CAP = 20;

    /**
     * @see RivalryScorerInterface::score()
     */
    public function score(array $input): int
    {
        $n = $input['a_wins'] + $input['b_wins'];
        if ($n <= 0) {
            return 0;
        }

        // Series carry 0.6 of the playoff term because a series is the rarer,
        // higher-stakes event; games carry 0.4 so a seven-game series outranks a sweep.
        $playoff = 0.6 * min(1.0, $input['playoff_series'] / self::PLAYOFF_SERIES_CAP)
            + 0.4 * min(1.0, $input['playoff_games'] / self::PLAYOFF_GAMES_CAP);
        $volume = min(1.0, $n / self::VOLUME_CAP_GAMES);
        $closeness = 1.0 - abs($input['a_wins'] - $input['b_wins']) / $n;
        $closeShare = min(1.0, $input['close_games'] / $n);
        $recency = min(1.0, $input['recent_games'] / self::RECENT_GAMES_CAP);

        $raw = self::WEIGHT_PLAYOFF * $playoff
            + self::WEIGHT_VOLUME * $volume
            + self::WEIGHT_CLOSENESS * $closeness
            + self::WEIGHT_CLOSE_GAMES * $closeShare
            + self::WEIGHT_RECENCY * $recency;

        return max(0, min(100, (int) round($raw)));
    }

    /**
     * @see RivalryScorerInterface::qualifies()
     */
    public function qualifies(array $input): bool
    {
        return $input['games'] >= self::MIN_GAMES;
    }

    /**
     * @see RivalryScorerInterface::rank()
     */
    public function rank(array $inputs): array
    {
        $scored = [];
        foreach ($inputs as $input) {
            if (!$this->qualifies($input)) {
                continue;
            }
            $scored[] = [
                'a' => $input['a'],
                'b' => $input['b'],
                'a_label' => $input['a_label'] ?? $input['a'],
                'b_label' => $input['b_label'] ?? $input['b'],
                'games' => $input['games'],
                'a_wins' => $input['a_wins'],
                'b_wins' => $input['b_wins'],
                'score' => $this->score($input),
            ];
        }

        usort($scored, static function (array $x, array $y): int {
            $byScore = [$y['score'], $y['games']] <=> [$x['score'], $x['games']];

            return $byScore !== 0 ? $byScore : strcmp($x['a'] . "\0" . $x['b'], $y['a'] . "\0" . $y['b']);
        });

        return $scored;
    }

    /**
     * First season year of the recency window, anchored on the passed ending year.
     */
    public static function recentWindowStart(int $endingYear): int
    {
        return $endingYear - self::RECENT_SEASONS + 1;
    }
}
