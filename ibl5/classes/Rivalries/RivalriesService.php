<?php

declare(strict_types=1);

namespace Rivalries;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use Rivalries\Contracts\RivalriesRepositoryInterface;
use Rivalries\Contracts\RivalriesServiceInterface;
use Rivalries\Contracts\RivalryScorerInterface;

/**
 * RivalriesService - Assembles ranked rivalries and pair pages.
 *
 * Win/loss records come only from the Head-to-Head Records matrices, so a
 * rivalry's record always matches the H2H grid cell. The repository supplies
 * the extra counts (close, recent, playoff) and the game list.
 *
 * @phpstan-import-type RivalryInput from RivalryScorerInterface
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 * @phpstan-import-type GameRow from RivalriesRepositoryInterface
 * @phpstan-import-type SeriesRow from RivalriesRepositoryInterface
 * @phpstan-import-type PairStat from RivalriesRepositoryInterface
 * @phpstan-import-type PairPage from RivalriesServiceInterface
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 */
final class RivalriesService implements RivalriesServiceInterface
{
    private const PAIR_LIST_LIMIT = 5;

    /** @var array<string, list<RivalryInput>> */
    private array $inputCache = [];

    /** @var array<string, MatrixPayload> */
    private array $matrixCache = [];

    public function __construct(
        private readonly RivalriesRepositoryInterface $repo,
        private readonly HeadToHeadRecordsRepositoryInterface $h2h,
        private readonly RivalryScorerInterface $scorer,
        private readonly int $endingYear,
    ) {
    }

    /**
     * @see RivalriesServiceInterface::getLeagueTop()
     */
    public function getLeagueTop(int $limit): array
    {
        return [
            'franchises' => array_slice($this->scorer->rank($this->inputs('teams')), 0, $limit),
            'gms' => array_slice($this->scorer->rank($this->inputs('gms')), 0, $limit),
        ];
    }

    /**
     * @see RivalriesServiceInterface::getTopFranchiseRivals()
     */
    public function getTopFranchiseRivals(int $teamId, int $limit): array
    {
        return $this->topFor('teams', (string) $teamId, $limit);
    }

    /**
     * @see RivalriesServiceInterface::getTopGmRivals()
     */
    public function getTopGmRivals(string $gmName, int $limit): array
    {
        return $this->topFor('gms', $gmName, $limit);
    }

    /**
     * @see RivalriesServiceInterface::getFranchisePair()
     */
    public function getFranchisePair(int $a, int $b): ?array
    {
        $games = $this->repo->getFranchisePairGames($a, $b);
        if ($games === []) {
            return null;
        }

        $series = $this->repo->getFranchisePlayoffSeries($a, $b);
        $performers = $this->repo->getFranchiseTopPerformers($a, $b);

        return $this->buildPage('teams', (string) $a, (string) $b, $games, $series, $performers);
    }

    /**
     * @see RivalriesServiceInterface::getGmPair()
     */
    public function getGmPair(string $a, string $b): ?array
    {
        $games = $this->repo->getGmPairGames($a, $b);
        if ($games === []) {
            return null;
        }

        $series = $this->repo->getGmPlayoffSeries($a, $b);
        $performers = $this->repo->getGmTopPerformers($a, $b);

        return $this->buildPage('gms', $a, $b, $games, $series, $performers);
    }

    /**
     * @param 'teams'|'gms' $kind
     * @return list<ScoredRivalry>
     */
    private function topFor(string $kind, string $subject, int $limit): array
    {
        $mine = [];
        foreach ($this->inputs($kind) as $input) {
            if ($input['a'] === $subject || $input['b'] === $subject) {
                $mine[] = $this->orient($input, $subject);
            }
        }

        return array_slice($this->scorer->rank($mine), 0, $limit);
    }

    /**
     * @param 'teams'|'gms' $kind
     * @return list<RivalryInput>
     */
    private function inputs(string $kind): array
    {
        if (isset($this->inputCache[$kind])) {
            return $this->inputCache[$kind];
        }

        $windowStart = RivalryScorer::recentWindowStart($this->endingYear);
        $stats = $kind === 'teams'
            ? $this->repo->getFranchisePairStats($windowStart)
            : $this->repo->getGmPairStats($windowStart);
        $matrix = $this->matrix($kind, 'all');
        $labels = $this->labels($matrix);

        $inputs = [];
        foreach ($stats as $stat) {
            $cell = $matrix['records'][$stat['a']][$stat['b']] ?? null;
            if ($cell === null) {
                continue;
            }
            $inputs[] = [
                'a' => $stat['a'],
                'b' => $stat['b'],
                'games' => $cell['wins'] + $cell['losses'],
                'a_wins' => $cell['wins'],
                'b_wins' => $cell['losses'],
                'playoff_games' => $stat['playoff_games'],
                'playoff_series' => $stat['playoff_series'],
                'close_games' => $stat['close_games'],
                'recent_games' => $stat['recent_games'],
                'a_label' => $labels[$stat['a']] ?? $stat['a'],
                'b_label' => $labels[$stat['b']] ?? $stat['b'],
            ];
        }

        $this->inputCache[$kind] = $inputs;

        return $inputs;
    }

    /**
     * @param RivalryInput $in
     * @return RivalryInput
     */
    private function orient(array $in, string $subject): array
    {
        if ($in['b'] !== $subject) {
            return $in;
        }

        $out = $in;
        $out['a'] = $in['b'];
        $out['b'] = $in['a'];
        $out['a_wins'] = $in['b_wins'];
        $out['b_wins'] = $in['a_wins'];
        $out['a_label'] = $in['b_label'] ?? $in['b'];
        $out['b_label'] = $in['a_label'] ?? $in['a'];

        return $out;
    }

    /**
     * @param 'teams'|'gms' $kind
     * @param list<GameRow> $games
     * @param list<SeriesRow> $series
     * @param array{totals: list<array{pid: int, name: string, side: string, games: int, total_points: int}>, highs: list<array{pid: int, name: string, side: string, points: int, game_date: string}>} $performers
     * @return PairPage
     */
    private function buildPage(string $kind, string $a, string $b, array $games, array $series, array $performers): array
    {
        $all = $this->matrix($kind, 'all');
        $playoffs = $this->matrix($kind, 'playoffs');
        $labels = $this->labels($all);
        $cell = $all['records'][$a][$b] ?? ['wins' => 0, 'losses' => 0];
        $playoffCell = $playoffs['records'][$a][$b] ?? ['wins' => 0, 'losses' => 0];

        $input = $this->inputFor($kind, $a, $b) ?? $this->synthesiseInput($a, $b, $games, $series);
        $input['a_wins'] = $cell['wins'];
        $input['b_wins'] = $cell['losses'];
        $input['games'] = $cell['wins'] + $cell['losses'];

        return [
            'kind' => $kind,
            'a' => $a,
            'b' => $b,
            'a_label' => $labels[$a] ?? $a,
            'b_label' => $labels[$b] ?? $b,
            'games' => $input['games'],
            'a_wins' => $cell['wins'],
            'b_wins' => $cell['losses'],
            'playoff_a_wins' => $playoffCell['wins'],
            'playoff_b_wins' => $playoffCell['losses'],
            'score' => $this->scorer->score($input),
            'qualifies' => $this->scorer->qualifies($input),
            'streak' => $this->streak($games),
            'series' => $series,
            'closest' => $this->byMargin($games, true),
            'blowouts' => $this->byMargin($games, false),
            'totals' => $performers['totals'],
            'highs' => $performers['highs'],
        ];
    }

    /**
     * @param 'teams'|'gms' $kind
     * @return RivalryInput|null
     */
    private function inputFor(string $kind, string $a, string $b): ?array
    {
        foreach ($this->inputs($kind) as $input) {
            if ($input['a'] === $a && $input['b'] === $b) {
                return $input;
            }
            if ($input['a'] === $b && $input['b'] === $a) {
                return $this->orient($input, $a);
            }
        }

        return null;
    }

    /**
     * Build a scorer input from the game list when the stats list lacks the pair.
     *
     * @param list<GameRow> $games
     * @param list<SeriesRow> $series
     * @return RivalryInput
     */
    private function synthesiseInput(string $a, string $b, array $games, array $series): array
    {
        $windowStart = RivalryScorer::recentWindowStart($this->endingYear);
        $playoffGames = 0;
        $closeGames = 0;
        $recentGames = 0;
        foreach ($games as $g) {
            if ($g['game_type'] === 2) {
                $playoffGames++;
            }
            if (abs($g['visitor_points'] - $g['home_points']) <= RivalryScorer::CLOSE_GAME_MARGIN) {
                $closeGames++;
            }
            if ($g['season_year'] >= $windowStart) {
                $recentGames++;
            }
        }

        return [
            'a' => $a,
            'b' => $b,
            'games' => 0,
            'a_wins' => 0,
            'b_wins' => 0,
            'playoff_games' => $playoffGames,
            'playoff_series' => count($series),
            'close_games' => $closeGames,
            'recent_games' => $recentGames,
        ];
    }

    /**
     * @param list<GameRow> $games
     * @return array{side: string, length: int}|null
     */
    private function streak(array $games): ?array
    {
        $side = null;
        $length = 0;
        for ($i = count($games) - 1; $i >= 0; $i--) {
            $g = $games[$i];
            $winner = $g['visitor_points'] > $g['home_points'] ? $g['visitor_side'] : $g['home_side'];
            if ($side === null) {
                $side = $winner;
            } elseif ($winner !== $side) {
                break;
            }
            $length++;
        }

        return $side === null ? null : ['side' => $side, 'length' => $length];
    }

    /**
     * @param list<GameRow> $games
     * @return list<GameRow>
     */
    private function byMargin(array $games, bool $ascending): array
    {
        usort($games, static function (array $x, array $y) use ($ascending): int {
            $mx = abs($x['visitor_points'] - $x['home_points']);
            $my = abs($y['visitor_points'] - $y['home_points']);
            $byMargin = $ascending ? $mx <=> $my : $my <=> $mx;

            return $byMargin
                ?: [$y['game_date'], $y['game_of_that_day']] <=> [$x['game_date'], $x['game_of_that_day']];
        });

        return array_slice($games, 0, self::PAIR_LIST_LIMIT);
    }

    /**
     * @param 'teams'|'gms' $kind
     * @param 'all'|'playoffs' $phase
     * @return MatrixPayload
     */
    private function matrix(string $kind, string $phase): array
    {
        $cacheKey = "{$kind}:{$phase}";
        if (!isset($this->matrixCache[$cacheKey])) {
            $this->matrixCache[$cacheKey] = $kind === 'teams'
                ? $this->h2h->buildFranchisesMatrix($phase, 'all')
                : $this->h2h->buildGmsMatrix($phase, 'all');
        }

        return $this->matrixCache[$cacheKey];
    }

    /**
     * @param MatrixPayload $matrix
     * @return array<string, string>
     */
    private function labels(array $matrix): array
    {
        $labels = [];
        foreach ($matrix['axis'] as $entry) {
            $labels[$entry['key']] = $entry['label'];
        }

        return $labels;
    }
}
