<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use HeadToHeadRecords\HeadToHeadRecordsRepository;
use PHPUnit\Framework\Attributes\Group;
use Rivalries\RivalriesRepository;

/**
 * DB-integration tests for RivalriesRepository.
 *
 * Dates map by the generated columns: 2001-11-05 is season 2002 regular,
 * 2001-10-10 is season 2002 HEAT, 2002-06-05 is season 2002 playoffs.
 * Tenures carry explicit end years so they never capture the CI seed's 2020+ games.
 */
#[Group('database')]
final class RivalriesRepositoryIntegrationTest extends DatabaseTestCase
{
    private RivalriesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new RivalriesRepository($this->db);
    }

    public function testGmRivalryFollowsGmAcrossFranchises(): void
    {
        $this->insertGmTenureRow(1, 'Mover Gm', 2001, 2002);
        $this->insertGmTenureRow(2, 'Mover Gm', 2003, 2004);
        $this->insertGmTenureRow(3, 'Rival Gm', 2001, 2004);
        $this->insertScoredTeamBoxscoreRow('2001-11-05', 1, 1, 3, 90, 80);
        $this->insertScoredTeamBoxscoreRow('2003-11-05', 1, 2, 3, 70, 80);

        $games = $this->repo->getGmPairGames('Mover Gm', 'Rival Gm');
        self::assertCount(2, $games);
        foreach ($games as $game) {
            self::assertSame('Mover Gm', $game['visitor_side']);
            self::assertSame('Rival Gm', $game['home_side']);
        }

        $pairs = array_values(array_filter(
            $this->repo->getGmPairStats(1990),
            static fn (array $s): bool => $s['a'] === 'Mover Gm' && $s['b'] === 'Rival Gm',
        ));
        self::assertCount(1, $pairs);
        self::assertSame(2, $pairs[0]['recent_games']);
    }

    public function testSameGmGamesAreExcluded(): void
    {
        $this->insertGmTenureRow(1, 'Twin Gm', 2002, 2002);
        $this->insertGmTenureRow(4, 'Twin Gm', 2002, 2002);
        $this->insertScoredTeamBoxscoreRow('2001-11-05', 1, 1, 4, 90, 80);

        self::assertSame([], $this->repo->getGmPairGames('Twin Gm', 'Twin Gm'));
        foreach ($this->repo->getGmPairStats(1990) as $stat) {
            self::assertFalse($stat['a'] === 'Twin Gm' && $stat['b'] === 'Twin Gm');
        }
    }

    public function testMidSeasonStartTenureBeginsNextSeason(): void
    {
        $this->insertGmTenureRow(5, 'Late Gm', 2002, 2003, 1);
        $this->insertGmTenureRow(6, 'Early Gm', 2001, 2003);
        $this->insertScoredTeamBoxscoreRow('2001-11-05', 1, 5, 6, 90, 80);

        self::assertSame([], $this->repo->getGmPairGames('Late Gm', 'Early Gm'));
    }

    public function testPairGameWinsMatchHeadToHeadMatrixCell(): void
    {
        $this->insertGmTenureRow(1, 'Inv One', 2002, 2002);
        $this->insertGmTenureRow(3, 'Inv Three', 2002, 2002);
        $this->insertScoredTeamBoxscoreRow('2001-11-05', 1, 1, 3, 90, 80);
        // Second row for the same game (the other team's row): the dedupe must count it once.
        $this->insertScoredTeamBoxscoreRow('2001-11-05', 1, 1, 3, 90, 80, 'Test Dup');
        $this->insertScoredTeamBoxscoreRow('2001-11-12', 1, 3, 1, 95, 80);
        $this->insertScoredTeamBoxscoreRow('2001-12-01', 1, 1, 3, 101, 99);
        $this->insertScoredTeamBoxscoreRow('2001-10-10', 1, 3, 1, 70, 75);
        $this->insertScoredTeamBoxscoreRow('2002-06-05', 1, 1, 3, 88, 86);

        $h2h = new HeadToHeadRecordsRepository($this->db, 2026);

        $franchiseGames = $this->repo->getFranchisePairGames(1, 3);
        $franchiseCell = $h2h->buildFranchisesMatrix('all', 'all')['records']['1']['3'];
        self::assertSame($franchiseCell['wins'], $this->winsFor('1', $franchiseGames));
        self::assertCount($franchiseCell['wins'] + $franchiseCell['losses'], $franchiseGames);

        $gmGames = $this->repo->getGmPairGames('Inv One', 'Inv Three');
        self::assertCount(5, $gmGames);
        $gmCell = $h2h->buildGmsMatrix('all', 'all')['records']['Inv One']['Inv Three'];
        self::assertSame($gmCell['wins'], $this->winsFor('Inv One', $gmGames));
        self::assertSame(4, $gmCell['wins']);
        self::assertCount($gmCell['wins'] + $gmCell['losses'], $gmGames);
    }

    public function testFranchisePairStatsCountsCloseRecentAndPlayoffGames(): void
    {
        $before = $this->statFor(9, 10);

        $this->insertScoredTeamBoxscoreRow('2001-11-05', 1, 9, 10, 85, 80);
        $this->insertScoredTeamBoxscoreRow('1995-01-05', 1, 10, 9, 86, 80);
        $this->insertScoredTeamBoxscoreRow('2002-06-05', 1, 9, 10, 100, 90);
        $this->insertPlayoffSeriesResultRow(2002, 1, 9, 10, 'Nine', 'Ten', 4, 2);

        $after = $this->statFor(9, 10);
        self::assertSame(1, $after['close_games'] - $before['close_games']);
        self::assertSame(2, $after['recent_games'] - $before['recent_games']);
        self::assertSame(1, $after['playoff_games'] - $before['playoff_games']);
        self::assertSame(1, $after['playoff_series'] - $before['playoff_series']);
    }

    public function testTopPerformersKeyOnPidAndJoinPlayerName(): void
    {
        $pid = 200009001;
        $this->insertTestPlayer($pid, 'Real Name');
        $this->insertScoredTeamBoxscoreRow('2001-11-05', 1, 11, 13, 90, 80);
        $this->insertScoredTeamBoxscoreRow('2001-11-20', 1, 13, 11, 90, 80);
        $this->insertPlayerBoxscoreRow('2001-11-05', $pid, 'Alias A', 'G', 11, 13, 11, 30, 20);
        $this->insertPlayerBoxscoreRow('2001-11-20', $pid, 'Alias B', 'G', 13, 11, 11, 30, 20);
        // A 0-minute DNP row in a third pair game must not count as a game played.
        $this->insertScoredTeamBoxscoreRow('2001-12-05', 1, 11, 13, 90, 80);
        $this->insertPlayerBoxscoreRow('2001-12-05', $pid, 'Alias C', 'G', 11, 13, 11, 0, 0);

        $performers = $this->repo->getFranchiseTopPerformers(11, 13);
        $rows = array_values(array_filter(
            $performers['totals'],
            static fn (array $t): bool => $t['pid'] === $pid,
        ));
        self::assertCount(1, $rows);
        self::assertSame('Real Name', $rows[0]['name']);
        self::assertSame(2, $rows[0]['games']);
        self::assertSame('11', $rows[0]['side']);
    }

    public function testUnknownPairReturnsEmptyLists(): void
    {
        $this->db->query('DELETE FROM ibl_box_scores_teams WHERE (visitor_teamid = 5 AND home_teamid = 9) OR (visitor_teamid = 9 AND home_teamid = 5)');
        $this->db->query('DELETE FROM ibl_playoff_series_results WHERE (winner_tid = 5 AND loser_tid = 9) OR (winner_tid = 9 AND loser_tid = 5)');

        self::assertSame([], $this->repo->getFranchisePairGames(5, 9));
        self::assertSame([], $this->repo->getFranchisePlayoffSeries(5, 9));
        self::assertSame(['totals' => [], 'highs' => []], $this->repo->getFranchiseTopPerformers(5, 9));
        self::assertSame([], $this->repo->getGmPairGames('Nobody Here', 'Nobody Else'));
        self::assertSame([], $this->repo->getGmPlayoffSeries('Nobody Here', 'Nobody Else'));
        self::assertFalse($this->repo->gmExists('Nobody Here'));
    }

    /**
     * @param list<array{visitor_points: int, home_points: int, visitor_side: string, home_side: string, ...}> $games
     */
    private function winsFor(string $side, array $games): int
    {
        $wins = 0;
        foreach ($games as $g) {
            $winner = $g['visitor_points'] > $g['home_points'] ? $g['visitor_side'] : $g['home_side'];
            if ($winner === $side) {
                $wins++;
            }
        }

        return $wins;
    }

    /**
     * @return array{a: string, b: string, close_games: int, recent_games: int, playoff_games: int, playoff_series: int}
     */
    private function statFor(int $a, int $b): array
    {
        foreach ($this->repo->getFranchisePairStats(1998) as $stat) {
            if ($stat['a'] === (string) $a && $stat['b'] === (string) $b) {
                return $stat;
            }
        }

        return ['a' => (string) $a, 'b' => (string) $b, 'close_games' => 0, 'recent_games' => 0, 'playoff_games' => 0, 'playoff_series' => 0];
    }
}
