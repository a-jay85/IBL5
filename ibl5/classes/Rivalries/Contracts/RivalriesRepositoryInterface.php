<?php

declare(strict_types=1);

namespace Rivalries\Contracts;

/**
 * Game-level data access for rivalries. W/L counts come from the H2H matrix;
 * this repository supplies dates, margins, series, and performers only.
 *
 * Pair keys are canonical: a < b (int compare for franchises, strcmp for GM names).
 * `*_side` holds the pair key that owned that team in that game.
 *
 * @phpstan-type PairStat array{a: string, b: string, close_games: int, recent_games: int, playoff_games: int, playoff_series: int}
 * @phpstan-type GameRow array{game_date: string, game_of_that_day: int, season_year: int, game_type: int, visitor_teamid: int, home_teamid: int, visitor_label: string, home_label: string, visitor_points: int, home_points: int, visitor_side: string, home_side: string}
 * @phpstan-type SeriesRow array{year: int, round: int, winner_tid: int, loser_tid: int, winner_games: int, loser_games: int, winner_side: string}
 * @phpstan-type PerformerTotal array{pid: int, name: string, side: string, games: int, total_points: int}
 * @phpstan-type PerformerHigh array{pid: int, name: string, side: string, points: int, game_date: string}
 */
interface RivalriesRepositoryInterface
{
    /** @return list<PairStat> */
    public function getFranchisePairStats(int $recentFromSeasonYear): array;

    /** @return list<PairStat> */
    public function getGmPairStats(int $recentFromSeasonYear): array;

    /** @return list<GameRow> ordered game_date ASC, game_of_that_day ASC */
    public function getFranchisePairGames(int $a, int $b): array;

    /** @return list<GameRow> ordered game_date ASC, game_of_that_day ASC */
    public function getGmPairGames(string $a, string $b): array;

    /** @return list<SeriesRow> ordered year ASC, round ASC */
    public function getFranchisePlayoffSeries(int $a, int $b): array;

    /** @return list<SeriesRow> ordered year ASC, round ASC */
    public function getGmPlayoffSeries(string $a, string $b): array;

    /** @return array{totals: list<PerformerTotal>, highs: list<PerformerHigh>} */
    public function getFranchiseTopPerformers(int $a, int $b): array;

    /** @return array{totals: list<PerformerTotal>, highs: list<PerformerHigh>} */
    public function getGmTopPerformers(string $a, string $b): array;

    public function gmExists(string $gmName): bool;
}
