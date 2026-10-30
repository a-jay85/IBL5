<?php

declare(strict_types=1);

namespace Tests\Rivalries;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Rivalries\Contracts\RivalriesRepositoryInterface;
use Rivalries\RivalriesService;
use Rivalries\RivalryScorer;

/**
 * @phpstan-import-type GameRow from RivalriesRepositoryInterface
 * @phpstan-import-type PairStat from RivalriesRepositoryInterface
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type AxisEntry from HeadToHeadRecordsRepositoryInterface
 */
final class RivalriesServiceTest extends TestCase
{
    public function testFranchisePairRecordOrientsToRequestedSide(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->games = [self::game('2001-11-05', '3', 90, '7', 80)];
        $h2h = new ServiceFakeH2h();
        $h2h->franchiseRecords = ['3' => ['7' => ['wins' => 8, 'losses' => 4]], '7' => ['3' => ['wins' => 4, 'losses' => 8]]];

        $page = $this->service($repo, $h2h)->getFranchisePair(7, 3);

        self::assertNotNull($page);
        self::assertSame(4, $page['a_wins']);
        self::assertSame(8, $page['b_wins']);
        self::assertSame(12, $page['games']);
    }

    public function testPairWithNoGamesReturnsNull(): void
    {
        self::assertNull($this->service(new ServiceFakeRepository(), new ServiceFakeH2h())->getFranchisePair(3, 7));
    }

    public function testBelowCutoffPairStillRendersWithQualifiesFalse(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->games = [self::game('2001-11-05', '3', 90, '7', 80)];
        $repo->franchiseStats = [self::stat('3', '7')];
        $h2h = new ServiceFakeH2h();
        $h2h->franchiseRecords = ['3' => ['7' => ['wins' => 5, 'losses' => 4]]];
        $service = $this->service($repo, $h2h);

        $page = $service->getFranchisePair(3, 7);

        self::assertNotNull($page);
        self::assertFalse($page['qualifies']);
        self::assertSame([], $service->getTopFranchiseRivals(3, 3));
    }

    public function testStreakCountsConsecutiveWinsFromLatestGame(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->games = [
            self::game('2001-11-01', '3', 90, '7', 80),
            self::game('2001-11-02', '3', 90, '7', 80),
            self::game('2001-11-03', '3', 70, '7', 80),
            self::game('2001-11-04', '3', 90, '7', 80),
            self::game('2001-11-05', '7', 80, '3', 90),
            self::game('2001-11-06', '3', 90, '7', 80),
        ];

        $page = $this->service($repo, new ServiceFakeH2h())->getFranchisePair(3, 7);

        self::assertNotNull($page);
        self::assertSame(['side' => '3', 'length' => 3], $page['streak']);
    }

    public function testClosestAndBlowoutsOrderByMarginWithDateTiebreak(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->games = [
            self::game('2001-11-01', '3', 82, '7', 80),
            self::game('2001-12-01', '3', 82, '7', 80),
            self::game('2001-12-05', '3', 110, '7', 80),
        ];

        $page = $this->service($repo, new ServiceFakeH2h())->getFranchisePair(3, 7);

        self::assertNotNull($page);
        self::assertSame('2001-12-01', $page['closest'][0]['game_date']);
        self::assertSame('2001-11-01', $page['closest'][1]['game_date']);
        self::assertSame('2001-12-05', $page['blowouts'][0]['game_date']);
        self::assertSame('2001-12-01', $page['blowouts'][1]['game_date']);
    }

    public function testTopFranchiseRivalsOrientAndLimit(): void
    {
        $repo = new ServiceFakeRepository();
        $h2h = new ServiceFakeH2h();
        foreach (['1', '2', '9', '12'] as $other) {
            [$a, $b] = strcmp($other, '3') < 0 ? [$other, '3'] : ['3', $other];
            $repo->franchiseStats[] = self::stat($a, $b);
            $h2h->franchiseRecords[$a][$b] = ['wins' => 7, 'losses' => 5];
        }

        $rivals = $this->service($repo, $h2h)->getTopFranchiseRivals(3, 3);

        self::assertCount(3, $rivals);
        foreach ($rivals as $row) {
            self::assertSame('3', $row['a']);
            self::assertNotSame('3', $row['b']);
        }
    }

    public function testRecencyWindowUsesInjectedEndingYear(): void
    {
        $repo = new ServiceFakeRepository();

        (new RivalriesService($repo, new ServiceFakeH2h(), new RivalryScorer(), 2001))->getLeagueTop(5);

        self::assertSame([1997], $repo->statsWindows);
    }

    public function testTopGmRivalsFollowGmName(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->gmStats = [self::stat('Alpha Gm', 'Mover Gm'), self::stat('Mover Gm', 'Zed Gm')];
        $h2h = new ServiceFakeH2h();
        $h2h->gmRecords = [
            'Alpha Gm' => ['Mover Gm' => ['wins' => 6, 'losses' => 6]],
            'Mover Gm' => ['Zed Gm' => ['wins' => 8, 'losses' => 4]],
        ];

        $rivals = $this->service($repo, $h2h)->getTopGmRivals('Mover Gm', 3);

        self::assertCount(2, $rivals);
        foreach ($rivals as $row) {
            self::assertSame('Mover Gm', $row['a']);
        }
        $alpha = array_values(array_filter($rivals, static fn (array $r): bool => $r['b'] === 'Alpha Gm'));
        self::assertSame(6, $alpha[0]['a_wins']);
    }

    public function testTopGmRivalsMatchOwnerNameWithoutRegardToCase(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->gmStats = [self::stat('Alpha Gm', 'Mover Gm')];
        $h2h = new ServiceFakeH2h();
        $h2h->gmRecords = ['Alpha Gm' => ['Mover Gm' => ['wins' => 6, 'losses' => 6]]];

        $rivals = $this->service($repo, $h2h)->getTopGmRivals('mover gm', 3);

        self::assertCount(1, $rivals);
        self::assertSame('Mover Gm', $rivals[0]['a']);
        self::assertSame('Alpha Gm', $rivals[0]['b']);
    }

    public function testTopRivalsSkipStatPairMissingFromMatrix(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->franchiseStats = [self::stat('3', '7'), self::stat('3', '9')];
        $h2h = new ServiceFakeH2h();
        $h2h->franchiseRecords = ['3' => ['7' => ['wins' => 7, 'losses' => 5]]];

        $rivals = $this->service($repo, $h2h)->getTopFranchiseRivals(3, 3);

        self::assertCount(1, $rivals);
        self::assertSame('7', $rivals[0]['b']);
    }

    public function testGmPairBuildsGmsPageOrientedToRequestedSide(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->games = [self::game('2001-11-05', '3', 90, '7', 80)];
        $h2h = new ServiceFakeH2h();
        $h2h->gmRecords = ['Amy' => ['Zed' => ['wins' => 9, 'losses' => 3]], 'Zed' => ['Amy' => ['wins' => 3, 'losses' => 9]]];

        $page = $this->service($repo, $h2h)->getGmPair('Zed', 'Amy');

        self::assertNotNull($page);
        self::assertSame('gms', $page['kind']);
        self::assertSame('Zed', $page['a']);
        self::assertSame('Amy', $page['b']);
        self::assertSame(3, $page['a_wins']);
        self::assertSame(9, $page['b_wins']);
    }

    public function testGmPairWithNoGamesReturnsNull(): void
    {
        self::assertNull($this->service(new ServiceFakeRepository(), new ServiceFakeH2h())->getGmPair('Amy', 'Zed'));
    }

    public function testReversedPairScoresFromStoredStats(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->games = [self::game('2001-11-05', '3', 90, '7', 80)];
        $repo->franchiseStats = [['a' => '3', 'b' => '7', 'close_games' => 6, 'recent_games' => 8, 'playoff_games' => 7, 'playoff_series' => 2]];
        $h2h = new ServiceFakeH2h();
        $h2h->franchiseRecords = ['3' => ['7' => ['wins' => 8, 'losses' => 6]], '7' => ['3' => ['wins' => 6, 'losses' => 8]]];
        $service = $this->service($repo, $h2h);

        $forward = $service->getFranchisePair(3, 7);
        $reversed = $service->getFranchisePair(7, 3);

        self::assertNotNull($forward);
        self::assertNotNull($reversed);
        $expected = (new RivalryScorer())->score([
            'a' => '7', 'b' => '3', 'games' => 14, 'a_wins' => 6, 'b_wins' => 8,
            'playoff_games' => 7, 'playoff_series' => 2, 'close_games' => 6, 'recent_games' => 8,
        ]);
        self::assertSame($expected, $reversed['score']);
        self::assertSame($forward['score'], $reversed['score']);
    }

    public function testPairWithoutStoredStatsScoresFromItsGames(): void
    {
        $repo = new ServiceFakeRepository();
        $recentPlayoff = self::game('2025-05-01', '3', 81, '7', 80);
        $recentPlayoff['season_year'] = 2025;
        $recentPlayoff['game_type'] = 2;
        $repo->games = [self::game('2001-11-05', '3', 110, '7', 80), $recentPlayoff];
        $h2h = new ServiceFakeH2h();
        $h2h->franchiseRecords = ['3' => ['7' => ['wins' => 10, 'losses' => 2]]];

        $page = $this->service($repo, $h2h)->getFranchisePair(3, 7);

        self::assertNotNull($page);
        $expected = (new RivalryScorer())->score([
            'a' => '3', 'b' => '7', 'games' => 12, 'a_wins' => 10, 'b_wins' => 2,
            'playoff_games' => 1, 'playoff_series' => 0, 'close_games' => 1, 'recent_games' => 1,
        ]);
        self::assertSame($expected, $page['score']);
    }

    public function testPairPageCarriesAxisLabelsAndPlayoffRecord(): void
    {
        $repo = new ServiceFakeRepository();
        $repo->games = [self::game('2001-11-05', '3', 90, '7', 80)];
        $h2h = new ServiceFakeH2h();
        $h2h->franchiseRecords = ['3' => ['7' => ['wins' => 8, 'losses' => 4]]];
        $h2h->franchisePlayoffRecords = ['3' => ['7' => ['wins' => 3, 'losses' => 1]]];
        $h2h->axis = [self::axis('3', 'Bulls'), self::axis('7', 'Hawks')];

        $page = $this->service($repo, $h2h)->getFranchisePair(3, 7);

        self::assertNotNull($page);
        self::assertSame('Bulls', $page['a_label']);
        self::assertSame('Hawks', $page['b_label']);
        self::assertSame(3, $page['playoff_a_wins']);
        self::assertSame(1, $page['playoff_b_wins']);
    }

    private function service(ServiceFakeRepository $repo, ServiceFakeH2h $h2h): RivalriesService
    {
        return new RivalriesService($repo, $h2h, new RivalryScorer(), 2026);
    }

    /**
     * @return GameRow
     */
    private static function game(string $date, string $visitor, int $vPts, string $home, int $hPts): array
    {
        return [
            'game_date' => $date,
            'game_of_that_day' => 1,
            'season_year' => 2002,
            'game_type' => 1,
            'visitor_teamid' => (int) $visitor,
            'home_teamid' => (int) $home,
            'visitor_label' => 'V',
            'home_label' => 'H',
            'visitor_era_colors' => null,
            'home_era_colors' => null,
            'visitor_points' => $vPts,
            'home_points' => $hPts,
            'visitor_side' => $visitor,
            'home_side' => $home,
        ];
    }

    /**
     * @return AxisEntry
     */
    private static function axis(string $key, string $label): array
    {
        return [
            'key' => $key,
            'franchise_id' => (int) $key,
            'label' => $label,
            'sublabel' => '',
            'color1' => '000000',
            'color2' => 'FFFFFF',
            'logo' => '',
            'link_franchise_id' => (int) $key,
        ];
    }

    /**
     * @return PairStat
     */
    private static function stat(string $a, string $b): array
    {
        return ['a' => $a, 'b' => $b, 'close_games' => 2, 'recent_games' => 3, 'playoff_games' => 0, 'playoff_series' => 0];
    }
}

/**
 * @phpstan-import-type GameRow from RivalriesRepositoryInterface
 * @phpstan-import-type PairStat from RivalriesRepositoryInterface
 */
final class ServiceFakeRepository implements RivalriesRepositoryInterface
{
    /** @var list<GameRow> */
    public array $games = [];
    /** @var list<PairStat> */
    public array $franchiseStats = [];
    /** @var list<PairStat> */
    public array $gmStats = [];
    /** @var list<int> */
    public array $statsWindows = [];

    public function getFranchisePairStats(int $recentFromSeasonYear): array
    {
        $this->statsWindows[] = $recentFromSeasonYear;
        return $this->franchiseStats;
    }

    public function getGmPairStats(int $recentFromSeasonYear): array
    {
        return $this->gmStats;
    }

    public function getFranchisePairGames(int $a, int $b): array
    {
        return $this->games;
    }

    public function getGmPairGames(string $a, string $b): array
    {
        return $this->games;
    }

    public function getFranchisePlayoffSeries(int $a, int $b): array
    {
        return [];
    }

    public function getGmPlayoffSeries(string $a, string $b): array
    {
        return [];
    }

    public function getFranchiseTopPerformers(int $a, int $b): array
    {
        return ['totals' => [], 'highs' => []];
    }

    public function getGmTopPerformers(string $a, string $b): array
    {
        return ['totals' => [], 'highs' => []];
    }

    public function findGmName(string $gmName): string
    {
        return $gmName;
    }

    public function findColorFranchise(string $gmName): ?int
    {
        return null;
    }
}

/**
 * @phpstan-import-type MatchupRecord from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type AxisEntry from HeadToHeadRecordsRepositoryInterface
 */
final class ServiceFakeH2h implements HeadToHeadRecordsRepositoryInterface
{
    /** @var array<array-key, array<array-key, MatchupRecord>> */
    public array $franchiseRecords = [];
    /** @var array<array-key, array<array-key, MatchupRecord>> */
    public array $gmRecords = [];
    /** @var array<array-key, array<array-key, MatchupRecord>> */
    public array $franchisePlayoffRecords = [];
    /** @var list<AxisEntry> */
    public array $axis = [];

    public function buildFranchisesMatrix(string $phase, string $scope): array
    {
        return $this->payload('franchises', $phase === 'all' ? $this->franchiseRecords : $this->franchisePlayoffRecords);
    }

    public function buildTeamsMatrix(string $phase, string $scope): array
    {
        return $this->payload('teams', []);
    }

    public function buildGmsMatrix(string $phase, string $scope): array
    {
        return $this->payload('gms', $phase === 'all' ? $this->gmRecords : []);
    }

    public function currentSeasonHasGames(): bool
    {
        return true;
    }

    public function sourceFingerprint(): string
    {
        return 'fp';
    }

    /**
     * @param 'franchises'|'teams'|'gms' $dimension
     * @param array<array-key, array<array-key, MatchupRecord>> $records
     * @return MatrixPayload
     */
    private function payload(string $dimension, array $records): array
    {
        return ['dimension' => $dimension, 'phase' => 'all', 'scope' => 'all', 'axis' => $this->axis, 'records' => $records];
    }
}
