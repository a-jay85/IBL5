<?php

declare(strict_types=1);

namespace Rivalries\Contracts;

/**
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 * @phpstan-import-type GameRow from RivalriesRepositoryInterface
 * @phpstan-import-type SeriesRow from RivalriesRepositoryInterface
 * @phpstan-import-type PerformerTotal from RivalriesRepositoryInterface
 * @phpstan-import-type PerformerHigh from RivalriesRepositoryInterface
 * @phpstan-type PairPage array{kind: 'teams'|'gms', a: string, b: string, a_label: string, b_label: string, games: int, a_wins: int, b_wins: int, playoff_a_wins: int, playoff_b_wins: int, score: int, qualifies: bool, streak: array{side: string, length: int}|null, series: list<SeriesRow>, closest: list<GameRow>, blowouts: list<GameRow>, totals: list<PerformerTotal>, highs: list<PerformerHigh>}
 */
interface RivalriesServiceInterface
{
    /** @return array{franchises: list<ScoredRivalry>, gms: list<ScoredRivalry>} top N league-wide */
    public function getLeagueTop(int $limit): array;

    /** @return list<ScoredRivalry> oriented so a === (string) $teamId */
    public function getTopFranchiseRivals(int $teamId, int $limit): array;

    /** @return list<ScoredRivalry> oriented so a === $gmName */
    public function getTopGmRivals(string $gmName, int $limit): array;

    /** @return PairPage|null null when the pair has zero games */
    public function getFranchisePair(int $a, int $b): ?array;

    /** @return PairPage|null null when the pair has zero games */
    public function getGmPair(string $a, string $b): ?array;
}
