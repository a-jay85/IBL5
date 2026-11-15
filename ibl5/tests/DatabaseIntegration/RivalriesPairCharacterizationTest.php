<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use HeadToHeadRecords\HeadToHeadRecordsRepository;
use PHPUnit\Framework\Attributes\Group;
use Rivalries\RivalriesRepository;

/**
 * Characterization suite for the Rivalries pair queries.
 *
 * Pins franchise-pair and GM-pair games, series, and top performers on a hand-built fixture
 * (seasons 1949-1954, franchises 1-4). It ran green on the unchanged queries before the pair
 * page was made faster, and must stay green afterwards.
 */
#[Group('database')]
final class RivalriesPairCharacterizationTest extends DatabaseTestCase
{
    private RivalriesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new RivalriesRepository($this->db);
    }

    public function testFranchisePairGamesMatchCharacterization(): void
    {
        $this->seed();

        $games = $this->repo->getFranchisePairGames(1, 2);

        self::assertSame(
            ['1950-01-10', '1951-01-20', '1951-06-05', '1952-02-05', '1953-02-10'],
            array_column($games, 'game_date'),
        );
        self::assertSame(95, $games[1]['visitor_points']);
        self::assertSame(99, $games[1]['home_points']);
        self::assertSame(self::fixtureFranchiseGames(), $games);
    }

    public function testGmPairGamesMatchCharacterization(): void
    {
        $this->seed();

        $games = $this->repo->getGmPairGames('Char Alpha', 'Char Beta');

        self::assertSame(
            ['1951-01-20', '1951-02-10', '1951-06-05', '1952-02-05', '1953-01-15'],
            array_column($games, 'game_date'),
        );
        self::assertSame(self::fixtureGmGames(), $games);
    }

    public function testPlayoffSeriesMatchCharacterization(): void
    {
        $this->seed();

        $franchise = $this->repo->getFranchisePlayoffSeries(1, 2);
        $gm = $this->repo->getGmPlayoffSeries('Char Alpha', 'Char Beta');

        self::assertSame([1950, 1951], array_column($franchise, 'year'));
        self::assertSame([1951], array_column($gm, 'year'));
        self::assertSame(self::fixtureFranchiseSeries(), $franchise);
        self::assertSame(self::fixtureGmSeries(), $gm);
    }

    public function testFranchiseTopPerformersMatchCharacterization(): void
    {
        $this->seed();

        $performers = $this->repo->getFranchiseTopPerformers(1, 2);

        self::assertSame([200000001, 200000002, 200000001], array_column($performers['highs'], 'pid'));
        self::assertNotContains(200000004, array_column($performers['totals'], 'pid'));
        self::assertSame(self::fixtureFranchisePerformers(), $performers);
    }

    public function testGmTopPerformersMatchCharacterization(): void
    {
        $this->seed();

        $performers = $this->repo->getGmTopPerformers('Char Alpha', 'Char Beta');

        self::assertSame([200000003, 200000001, 200000002], array_column($performers['highs'], 'pid'));
        foreach ($performers['highs'] as $high) {
            self::assertNotSame('1952-03-01', $high['game_date'], 'game E has no GM on one side');
        }
        self::assertSame(self::fixtureGmPerformers(), $performers);
    }

    public function testTopPerformersAfterPairGamesMatchColdCall(): void
    {
        $this->seed();

        $warm = new RivalriesRepository($this->db);
        $warm->getFranchisePairGames(1, 2);
        $cold = new RivalriesRepository($this->db);
        self::assertSame($cold->getFranchiseTopPerformers(1, 2), $warm->getFranchiseTopPerformers(1, 2));

        $warm = new RivalriesRepository($this->db);
        $warm->getGmPairGames('Char Alpha', 'Char Beta');
        $cold = new RivalriesRepository($this->db);
        self::assertSame(
            $cold->getGmTopPerformers('Char Alpha', 'Char Beta'),
            $warm->getGmTopPerformers('Char Alpha', 'Char Beta'),
        );
    }

    public function testGmPairSharingOneFranchiseHasNoGames(): void
    {
        $this->seed();

        self::assertSame([], $this->repo->getGmPairGames('Char Theta', 'Char Iota'));
        self::assertSame([], $this->repo->getGmPlayoffSeries('Char Theta', 'Char Iota'));
        self::assertSame(
            ['totals' => [], 'highs' => []],
            $this->repo->getGmTopPerformers('Char Theta', 'Char Iota'),
        );
    }

    public function testUnknownGmPairIsEmpty(): void
    {
        $this->seed();

        self::assertSame([], $this->repo->getGmPairGames('Char Alpha', 'Char Nobody'));
        self::assertSame(
            ['totals' => [], 'highs' => []],
            $this->repo->getGmTopPerformers('Char Alpha', 'Char Nobody'),
        );
    }

    public function testGmPairAgreesWithHeadToHeadMatrix(): void
    {
        $this->seed();

        $h2h = new HeadToHeadRecordsRepository($this->db, 1954);
        $records = $h2h->buildGmsMatrix('all', 'all')['records'];

        self::assertSame(['wins' => 3, 'losses' => 2], $records['Char Alpha']['Char Beta']);
        self::assertSame(['wins' => 2, 'losses' => 3], $records['Char Beta']['Char Alpha']);

        $wins = 0;
        $losses = 0;
        foreach ($this->repo->getGmPairGames('Char Alpha', 'Char Beta') as $game) {
            if ($game['visitor_side'] === 'Char Alpha') {
                $alpha = $game['visitor_points'];
                $beta = $game['home_points'];
            } else {
                $alpha = $game['home_points'];
                $beta = $game['visitor_points'];
            }
            if ($alpha > $beta) {
                $wins++;
            } else {
                $losses++;
            }
        }
        self::assertSame(3, $wins);
        self::assertSame(2, $losses);
    }

    private function seed(): void
    {
        // Tenure ids ascend in this order; the order is part of what the tests pin.
        $this->insertGmTenureRow(1, 'Char Alpha', 1950, 1952);
        $this->insertGmTenureRow(1, 'Char Aaron', 1950, 1951);
        $this->insertGmTenureRow(2, 'Char Zeta', 1949, 1950);
        $this->insertGmTenureRow(2, 'Char Beta', 1950, null);
        $this->insertGmTenureRow(3, 'Char Gamma', 1950, 1951);
        $this->insertGmTenureRow(3, 'Char Alpha', 1952, 1954, 1);
        $this->insertGmTenureRow(1, 'Char Delta', 1953, 1954);
        $this->insertGmTenureRow(4, 'Char Theta', 1950, 1952);
        $this->insertGmTenureRow(4, 'Char Iota', 1953, 1954);
        // Same start year on franchise 5, lower id, but a mid-season start: 1951 resolves to Alpha, not Mu.
        $this->insertGmTenureRow(5, 'Char Mu', 1951, 1953, 1);
        $this->insertGmTenureRow(5, 'Char Alpha', 1951, 1951);

        $this->insertScoredTeamBoxscoreRow('1950-01-10', 1, 1, 2, 100, 90);
        $this->insertScoredTeamBoxscoreRow('1951-01-20', 1, 2, 1, 95, 99);
        $this->insertScoredTeamBoxscoreRow('1951-01-20', 1, 2, 1, 70, 120, 'Dup');
        $this->insertScoredTeamBoxscoreRow('1951-02-10', 1, 5, 2, 75, 70);
        $this->insertScoredTeamBoxscoreRow('1951-06-05', 1, 1, 2, 88, 80);
        $this->insertScoredTeamBoxscoreRow('1951-09-20', 1, 1, 2, 77, 70);
        $this->insertScoredTeamBoxscoreRow('1952-02-05', 2, 1, 2, 90, 101);
        $this->insertScoredTeamBoxscoreRow('1952-03-01', 1, 3, 2, 81, 79);
        $this->insertScoredTeamBoxscoreRow('1953-01-15', 1, 3, 2, 85, 92);
        $this->insertScoredTeamBoxscoreRow('1953-02-10', 1, 1, 2, 104, 98);
        $this->insertScoredTeamBoxscoreRow('1952-01-12', 1, 4, 1, 80, 70);

        $this->insertTestPlayer(200000001, 'Char One');
        $this->insertTestPlayer(200000002, 'Char Two');
        $this->insertTestPlayer(200000003, 'Char Three');
        $this->insertTestPlayer(200000004, 'Char Four');

        $this->insertPlayerRow('1950-01-10', 1, 200000001, 'Char One', 1, 2, 1, 10);
        $this->insertPlayerRow('1951-01-20', 1, 200000001, 'Char One', 2, 1, 1, 30);
        $this->insertPlayerRow('1952-02-05', 2, 200000001, 'Char One', 1, 2, 1, 20);
        $this->insertPlayerRow('1953-02-10', 1, 200000001, 'Char One', 1, 2, 1, 15);
        $this->insertPlayerRow('1951-01-20', 1, 200000002, 'Char Two', 2, 1, 2, 25);
        $this->insertPlayerRow('1953-01-15', 1, 200000002, 'Char Two', 3, 2, 2, 18);
        $this->insertPlayerRow('1953-01-15', 1, 200000003, 'Char Three', 3, 2, 3, 40);
        $this->insertPlayerRow('1952-03-01', 1, 200000003, 'Char Three', 3, 2, 3, 50);
        // Did not play: the 0-minute row must be filtered out.
        $this->insertPlayerRow('1951-01-20', 1, 200000004, 'Char Four', 2, 1, 1, 12, 0);

        $this->insertPlayoffSeriesResultRow(1951, 1, 1, 2, 'Alpha Team', 'Beta Team', 4, 2);
        $this->insertPlayoffSeriesResultRow(1950, 1, 2, 1, 'Zeta Team', 'Alpha Team', 4, 3);
    }

    /**
     * One player box row scoring exactly $points: 2-pointers and one free throw for an odd total.
     */
    private function insertPlayerRow(
        string $date,
        int $gameOfDay,
        int $pid,
        string $name,
        int $visitorTid,
        int $homeTid,
        int $teamId,
        int $points,
        int $minutes = 30,
    ): void {
        $twos = intdiv($points, 2);
        $ft = $points % 2;
        $this->insertPlayerBoxscoreRow(
            $date,
            $pid,
            $name,
            'PG',
            $visitorTid,
            $homeTid,
            $teamId,
            $minutes,
            $twos,
            $twos,
            $ft,
            $ft,
            0,
            0,
            overrides: ['game_of_that_day' => $gameOfDay],
        );
    }

    /** @return list<array<string, mixed>> */
    private static function fixtureFranchiseGames(): array
    {
        return [
            [
                'game_date' => '1950-01-10',
                'game_of_that_day' => 1,
                'season_year' => 1950,
                'game_type' => 1,
                'visitor_teamid' => 1,
                'home_teamid' => 2,
                'visitor_label' => 'Metros',
                'home_label' => 'Stars',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 100,
                'home_points' => 90,
                'visitor_side' => '1',
                'home_side' => '2',
            ],
            [
                'game_date' => '1951-01-20',
                'game_of_that_day' => 1,
                'season_year' => 1951,
                'game_type' => 1,
                'visitor_teamid' => 2,
                'home_teamid' => 1,
                'visitor_label' => 'Stars',
                'home_label' => 'Metros',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 95,
                'home_points' => 99,
                'visitor_side' => '2',
                'home_side' => '1',
            ],
            [
                'game_date' => '1951-06-05',
                'game_of_that_day' => 1,
                'season_year' => 1951,
                'game_type' => 2,
                'visitor_teamid' => 1,
                'home_teamid' => 2,
                'visitor_label' => 'Metros',
                'home_label' => 'Stars',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 88,
                'home_points' => 80,
                'visitor_side' => '1',
                'home_side' => '2',
            ],
            [
                'game_date' => '1952-02-05',
                'game_of_that_day' => 2,
                'season_year' => 1952,
                'game_type' => 1,
                'visitor_teamid' => 1,
                'home_teamid' => 2,
                'visitor_label' => 'Metros',
                'home_label' => 'Stars',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 90,
                'home_points' => 101,
                'visitor_side' => '1',
                'home_side' => '2',
            ],
            [
                'game_date' => '1953-02-10',
                'game_of_that_day' => 1,
                'season_year' => 1953,
                'game_type' => 1,
                'visitor_teamid' => 1,
                'home_teamid' => 2,
                'visitor_label' => 'Metros',
                'home_label' => 'Stars',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 104,
                'home_points' => 98,
                'visitor_side' => '1',
                'home_side' => '2',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function fixtureGmGames(): array
    {
        return [
            [
                'game_date' => '1951-01-20',
                'game_of_that_day' => 1,
                'season_year' => 1951,
                'game_type' => 1,
                'visitor_teamid' => 2,
                'home_teamid' => 1,
                'visitor_label' => 'Stars',
                'home_label' => 'Metros',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 95,
                'home_points' => 99,
                'visitor_side' => 'Char Beta',
                'home_side' => 'Char Alpha',
            ],
            [
                'game_date' => '1951-02-10',
                'game_of_that_day' => 1,
                'season_year' => 1951,
                'game_type' => 1,
                'visitor_teamid' => 5,
                'home_teamid' => 2,
                'visitor_label' => 'Minutemen',
                'home_label' => 'Stars',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 75,
                'home_points' => 70,
                'visitor_side' => 'Char Alpha',
                'home_side' => 'Char Beta',
            ],
            [
                'game_date' => '1951-06-05',
                'game_of_that_day' => 1,
                'season_year' => 1951,
                'game_type' => 2,
                'visitor_teamid' => 1,
                'home_teamid' => 2,
                'visitor_label' => 'Metros',
                'home_label' => 'Stars',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 88,
                'home_points' => 80,
                'visitor_side' => 'Char Alpha',
                'home_side' => 'Char Beta',
            ],
            [
                'game_date' => '1952-02-05',
                'game_of_that_day' => 2,
                'season_year' => 1952,
                'game_type' => 1,
                'visitor_teamid' => 1,
                'home_teamid' => 2,
                'visitor_label' => 'Metros',
                'home_label' => 'Stars',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 90,
                'home_points' => 101,
                'visitor_side' => 'Char Alpha',
                'home_side' => 'Char Beta',
            ],
            [
                'game_date' => '1953-01-15',
                'game_of_that_day' => 1,
                'season_year' => 1953,
                'game_type' => 1,
                'visitor_teamid' => 3,
                'home_teamid' => 2,
                'visitor_label' => 'Cougars',
                'home_label' => 'Stars',
                'visitor_era_colors' => null,
                'home_era_colors' => null,
                'visitor_points' => 85,
                'home_points' => 92,
                'visitor_side' => 'Char Alpha',
                'home_side' => 'Char Beta',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function fixtureFranchiseSeries(): array
    {
        return [
            [
                'year' => 1950,
                'round' => 1,
                'winner_tid' => 2,
                'loser_tid' => 1,
                'winner_games' => 4,
                'loser_games' => 3,
                'winner_side' => '2',
            ],
            [
                'year' => 1951,
                'round' => 1,
                'winner_tid' => 1,
                'loser_tid' => 2,
                'winner_games' => 4,
                'loser_games' => 2,
                'winner_side' => '1',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function fixtureGmSeries(): array
    {
        return [
            [
                'year' => 1951,
                'round' => 1,
                'winner_tid' => 1,
                'loser_tid' => 2,
                'winner_games' => 4,
                'loser_games' => 2,
                'winner_side' => 'Char Alpha',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function fixtureFranchisePerformers(): array
    {
        return [
            'totals' => [
                [
                    'pid' => 200000001,
                    'name' => 'Char One',
                    'side' => '1',
                    'games' => 4,
                    'total_points' => 75,
                ],
                [
                    'pid' => 200000002,
                    'name' => 'Char Two',
                    'side' => '2',
                    'games' => 1,
                    'total_points' => 25,
                ],
            ],
            'highs' => [
                [
                    'pid' => 200000001,
                    'name' => 'Char One',
                    'side' => '1',
                    'points' => 30,
                    'game_date' => '1951-01-20',
                    'game_of_that_day' => 1,
                ],
                [
                    'pid' => 200000002,
                    'name' => 'Char Two',
                    'side' => '2',
                    'points' => 25,
                    'game_date' => '1951-01-20',
                    'game_of_that_day' => 1,
                ],
                [
                    'pid' => 200000001,
                    'name' => 'Char One',
                    'side' => '1',
                    'points' => 20,
                    'game_date' => '1952-02-05',
                    'game_of_that_day' => 2,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function fixtureGmPerformers(): array
    {
        return [
            'totals' => [
                [
                    'pid' => 200000001,
                    'name' => 'Char One',
                    'side' => 'Char Alpha',
                    'games' => 2,
                    'total_points' => 50,
                ],
                [
                    'pid' => 200000002,
                    'name' => 'Char Two',
                    'side' => 'Char Beta',
                    'games' => 2,
                    'total_points' => 43,
                ],
                [
                    'pid' => 200000003,
                    'name' => 'Char Three',
                    'side' => 'Char Alpha',
                    'games' => 1,
                    'total_points' => 40,
                ],
            ],
            'highs' => [
                [
                    'pid' => 200000003,
                    'name' => 'Char Three',
                    'side' => 'Char Alpha',
                    'points' => 40,
                    'game_date' => '1953-01-15',
                    'game_of_that_day' => 1,
                ],
                [
                    'pid' => 200000001,
                    'name' => 'Char One',
                    'side' => 'Char Alpha',
                    'points' => 30,
                    'game_date' => '1951-01-20',
                    'game_of_that_day' => 1,
                ],
                [
                    'pid' => 200000002,
                    'name' => 'Char Two',
                    'side' => 'Char Beta',
                    'points' => 25,
                    'game_date' => '1951-01-20',
                    'game_of_that_day' => 1,
                ],
            ],
        ];
    }
}
