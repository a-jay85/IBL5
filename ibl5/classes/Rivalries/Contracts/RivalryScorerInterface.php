<?php

declare(strict_types=1);

namespace Rivalries\Contracts;

/**
 * Pure rivalry scoring: weights playoff meetings, volume, closeness of the
 * record, close-game share, and recency into an integer 0-100.
 *
 * Labels are optional on input and default to the pair key, so views never
 * re-query names.
 *
 * @phpstan-type RivalryInput array{a: string, b: string, games: int, a_wins: int, b_wins: int, playoff_games: int, playoff_series: int, close_games: int, recent_games: int, a_label?: string, b_label?: string}
 * @phpstan-type ScoredRivalry array{a: string, b: string, a_label: string, b_label: string, games: int, a_wins: int, b_wins: int, score: int}
 */
interface RivalryScorerInterface
{
    /** @param RivalryInput $input */
    public function score(array $input): int;

    /** @param RivalryInput $input */
    public function qualifies(array $input): bool;

    /**
     * @param list<RivalryInput> $inputs
     * @return list<ScoredRivalry> qualifying only, sorted score DESC, games DESC, key ASC
     */
    public function rank(array $inputs): array;
}
