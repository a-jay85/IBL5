<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * Scores moments by how remarkable they are and ranks them for display.
 *
 * Pure logic: no I/O.
 */
final class WowScorer
{
    public const FIFTY_POINT_THRESHOLD = 50;
    public const FIFTY_POINT_BASE = 100;
    public const FIFTY_POINT_PER_EXTRA_POINT = 2;

    public const DOUBLE_DIGIT_THRESHOLD = 10;
    public const TRIPLE_DOUBLE_MIN_CATEGORIES = 3;
    public const TRIPLE_DOUBLE_BASE = 60;
    public const TRIPLE_DOUBLE_BASELINE_TOTAL = 30;
    public const QUADRUPLE_DOUBLE_MIN_CATEGORIES = 4;
    public const QUADRUPLE_DOUBLE_BONUS = 40;

    public const RECORD_GAME_SCORE = 150;

    public const SERIES_CLINCHER_BASE = 70;
    public const FINALS_ROUND = 4;
    public const FINALS_BONUS = 30;
    public const GAME_SEVEN_TOTAL_GAMES = 7;
    public const GAME_SEVEN_BONUS = 15;

    public const TRADE_BASE = 40;
    public const TRADE_PER_PLAYER = 5;
    public const TRADE_MAX_PLAYERS_COUNTED = 6;

    public const MAX_PER_KIND = 2;

    private const DOUBLE_DIGIT_STATS = ['points', 'rebounds', 'assists', 'steals', 'blocks'];

    public function score(Moment $m): int
    {
        return match ($m->kind) {
            MomentKind::FiftyPointGame => $this->scoreFiftyPointGame($m),
            MomentKind::TripleDouble => $this->scoreTripleDouble($m),
            MomentKind::RecordGame => self::RECORD_GAME_SCORE,
            MomentKind::SeriesClincher => $this->scoreSeriesClincher($m),
            MomentKind::Trade => $this->scoreTrade($m),
        };
    }

    /**
     * Rank moments: drop zero scores, dedupe by key, drop excluded keys, sort
     * (score DESC, seasonYear DESC, kind declaration order, key ASC), then take
     * at most MAX_PER_KIND of any kind up to $limit.
     *
     * @param list<Moment> $moments
     * @param list<string> $excludeKeys
     * @return list<Moment>
     */
    public function rank(array $moments, int $limit, array $excludeKeys): array
    {
        $excluded = array_flip($excludeKeys);
        /** @var array<string, array{moment: Moment, score: int}> $candidates */
        $candidates = [];

        foreach ($moments as $moment) {
            $score = $this->score($moment);
            if ($score === 0) {
                continue;
            }
            if (isset($candidates[$moment->key])) {
                continue;
            }
            if (isset($excluded[$moment->key])) {
                continue;
            }
            $candidates[$moment->key] = ['moment' => $moment, 'score' => $score];
        }

        $kindOrder = [];
        foreach (MomentKind::cases() as $index => $case) {
            $kindOrder[$case->value] = $index;
        }

        $sorted = array_values($candidates);
        usort($sorted, static function (array $a, array $b) use ($kindOrder): int {
            return [$b['score'], $b['moment']->seasonYear, $kindOrder[$a['moment']->kind->value], $a['moment']->key]
                <=> [$a['score'], $a['moment']->seasonYear, $kindOrder[$b['moment']->kind->value], $b['moment']->key];
        });

        $result = [];
        $perKind = [];
        foreach ($sorted as $entry) {
            if (count($result) >= $limit) {
                break;
            }
            $kindValue = $entry['moment']->kind->value;
            $taken = $perKind[$kindValue] ?? 0;
            if ($taken >= self::MAX_PER_KIND) {
                continue;
            }
            $perKind[$kindValue] = $taken + 1;
            $result[] = $entry['moment'];
        }

        return $result;
    }

    private function fact(Moment $m, string $name): int
    {
        return $m->facts[$name] ?? 0;
    }

    private function scoreFiftyPointGame(Moment $m): int
    {
        $points = $this->fact($m, 'points');
        if ($points < self::FIFTY_POINT_THRESHOLD) {
            return 0;
        }

        return self::FIFTY_POINT_BASE + self::FIFTY_POINT_PER_EXTRA_POINT * ($points - self::FIFTY_POINT_THRESHOLD);
    }

    private function scoreTripleDouble(Moment $m): int
    {
        $count = 0;
        foreach (self::DOUBLE_DIGIT_STATS as $stat) {
            if ($this->fact($m, $stat) >= self::DOUBLE_DIGIT_THRESHOLD) {
                $count++;
            }
        }
        if ($count < self::TRIPLE_DOUBLE_MIN_CATEGORIES) {
            return 0;
        }

        $total = $this->fact($m, 'points') + $this->fact($m, 'rebounds') + $this->fact($m, 'assists');
        $score = self::TRIPLE_DOUBLE_BASE + ($total - self::TRIPLE_DOUBLE_BASELINE_TOTAL);
        if ($count >= self::QUADRUPLE_DOUBLE_MIN_CATEGORIES) {
            $score += self::QUADRUPLE_DOUBLE_BONUS;
        }

        return $score;
    }

    private function scoreSeriesClincher(Moment $m): int
    {
        $score = self::SERIES_CLINCHER_BASE;
        if ($this->fact($m, 'round') === self::FINALS_ROUND) {
            $score += self::FINALS_BONUS;
        }
        if ($this->fact($m, 'wins') + $this->fact($m, 'losses') === self::GAME_SEVEN_TOTAL_GAMES) {
            $score += self::GAME_SEVEN_BONUS;
        }

        return $score;
    }

    private function scoreTrade(Moment $m): int
    {
        $moved = min($this->fact($m, 'players_moved'), self::TRADE_MAX_PLAYERS_COUNTED);

        return self::TRADE_BASE + self::TRADE_PER_PLAYER * $moved;
    }
}
