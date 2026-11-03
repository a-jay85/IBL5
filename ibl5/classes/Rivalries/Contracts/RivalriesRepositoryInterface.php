<?php

declare(strict_types=1);

namespace Rivalries\Contracts;

/**
 * Game-level data access for rivalries. W/L counts come from the H2H matrix;
 * this repository supplies dates, margins, series, and performers only.
 *
 * Pair keys are canonical: a < b by int compare for franchises. For GM names the
 * repository orders by SQL LEAST/GREATEST (column collation, case-insensitive), which
 * can differ from strcmp. Callers that need strcmp order must re-sort, as
 * CachedRivalriesRepository::gmsKey does.
 * `*_side` holds the pair key that owned that team in that game.
 * `*_era_colors` holds a relocated or renamed team's old colors from
 * `ibl_franchise_era_branding`, or null when the game used today's identity or
 * that era has no branding row.
 *
 * @phpstan-type PairStat array{a: string, b: string, close_games: int, recent_games: int, playoff_games: int, playoff_series: int}
 * @phpstan-type GameRow array{game_date: string, game_of_that_day: int, season_year: int, game_type: int, visitor_teamid: int, home_teamid: int, visitor_label: string, home_label: string, visitor_era_colors: array{color1: string, color2: string}|null, home_era_colors: array{color1: string, color2: string}|null, visitor_points: int, home_points: int, visitor_side: string, home_side: string}
 * @phpstan-type SeriesRow array{year: int, round: int, winner_tid: int, loser_tid: int, winner_games: int, loser_games: int, winner_side: string}
 * @phpstan-type PerformerTotal array{pid: int, name: string, side: string, games: int, total_points: int}
 * @phpstan-type PerformerHigh array{pid: int, name: string, side: string, points: int, game_date: string, game_of_that_day: int}
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

    /**
     * The stored spelling of a GM name, or null when no tenure has it. The lookup
     * ignores case (column collation), so callers must use the returned name.
     */
    public function findGmName(string $gmName): ?string;

    /**
     * The franchise whose colors stand for a GM: the team they run now, or,
     * when they run none, the only franchise they ever ran. Null when they
     * run none and ran several (or none).
     */
    public function findColorFranchise(string $gmName): ?int;
}
