<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use Tests\Integration\IntegrationTestCase;
use RecordHolders\RecordHoldersRepository;

final class RecordHoldersRepositoryTest extends IntegrationTestCase
{
    private RecordHoldersRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suppressErrorLog();
        $this->repository = new RecordHoldersRepository($this->mockDb);
    }

    protected function tearDown(): void
    {
        unset($this->repository);
        parent::tearDown();
    }

    public function testGetTopPlayerSingleGameQueriesBoxScores(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTopPlayerSingleGame(
            '(bs.game2GM * 2 + bs.gameFTM + bs.game3GM * 3)',
            'MONTH(bs.Date) IN (11, 12, 1, 2, 3, 4, 5)'
        );

        $this->assertQueryExecuted('ibl_box_scores');
        $this->assertQueryExecuted('ibl_plr');
        $this->assertQueryExecuted('ibl_hist');
    }

    public function testGetTopSeasonAverageQueriesHistoryTable(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTopSeasonAverage('pts', 'games', 50);

        $this->assertQueryExecuted('ibl_hist');
    }

    public function testGetTopSeasonAverageRejectsInvalidColumnName(): void
    {
        $result = $this->repository->getTopSeasonAverage('pts; DROP TABLE', 'games', 50);

        $this->assertSame([], $result);
    }

    public function testGetQuadrupleDoublesQueriesForFourCategories(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getQuadrupleDoubles();

        $this->assertQueryExecuted('ibl_box_scores');
        $this->assertQueryExecuted('>= 4');
    }

    public function testGetMostAllStarAppearancesQueriesAwards(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getMostAllStarAppearances();

        $this->assertQueryExecuted('ibl_awards');
        $this->assertQueryExecuted('Conference All-Star');
    }

    public function testGetTopTeamSingleGameQueriesTeamBoxScores(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTopTeamSingleGame(
            '(bs.game2GM * 2 + bs.gameFTM + bs.game3GM * 3)',
            'MONTH(bs.Date) IN (11, 12, 1, 2, 3, 4, 5)'
        );

        $this->assertQueryExecuted('ibl_box_scores_teams');
    }

    public function testGetTopTeamSingleGameAcceptsAscOrder(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTopTeamSingleGame(
            '(bs.game2GM * 2 + bs.gameFTM + bs.game3GM * 3)',
            '1=1',
            'ASC'
        );

        $this->assertQueryExecuted('ASC');
    }

    public function testGetTopTeamHalfScoreQueriesQuarterPoints(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTopTeamHalfScore('first', 'DESC');

        $this->assertQueryExecuted('Q1points');
        $this->assertQueryExecuted('Q2points');
    }

    public function testGetBestWorstSeasonRecordQueriesWinLoss(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getBestWorstSeasonRecord('DESC');

        $this->assertQueryExecuted('ibl_team_win_loss');
    }

    public function testGetMostPlayoffAppearancesQueriesPlayoffResults(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getMostPlayoffAppearances();

        $this->assertQueryExecuted('ibl_playoff_results');
    }

    public function testGetMostTitlesByTypeQueriesTeamAwards(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getMostTitlesByType('Division');

        $this->assertQueryExecuted('ibl_team_awards');
        $this->assertQueryExecuted('Division');
    }

    public function testGetTopPlayerSingleGameReturnsFormattedRecords(): void
    {
        $this->mockDb->setMockData([
            [
                'pid' => 927,
                'name' => 'Bob Pettit',
                'tid' => 14,
                'team_name' => 'Timberwolves',
                'date' => '1996-01-16',
                'BoxID' => 0,
                'oppTid' => 20,
                'opp_team_name' => 'Grizzlies',
                'value' => 80,
            ],
        ]);

        $result = $this->repository->getTopPlayerSingleGame(
            '(bs.game2GM * 2 + bs.gameFTM + bs.game3GM * 3)',
            'MONTH(bs.Date) IN (11, 12, 1, 2, 3, 4, 5)'
        );

        $this->assertCount(1, $result);
        $this->assertSame(927, $result[0]['pid']);
        $this->assertSame('Bob Pettit', $result[0]['name']);
        $this->assertSame(14, $result[0]['tid']);
        $this->assertSame(80, $result[0]['value']);
    }

    public function testGetMostAllStarAppearancesReturnsFormattedRecords(): void
    {
        $this->mockDb->setMockData([
            [
                'name' => 'Mitch Richmond',
                'pid' => 304,
                'appearances' => 10,
            ],
        ]);

        $result = $this->repository->getMostAllStarAppearances();

        $this->assertCount(1, $result);
        $this->assertSame('Mitch Richmond', $result[0]['name']);
        $this->assertSame(304, $result[0]['pid']);
        $this->assertSame(10, $result[0]['appearances']);
    }

    public function testGetMostTitlesByTypeReturnsOnlyTopTied(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Heat', 'count' => 4, 'years' => '1989, 1992, 1993, 1994'],
            ['team_name' => 'Nets', 'count' => 4, 'years' => '1990, 1991, 1995, 1996'],
            ['team_name' => 'Bulls', 'count' => 2, 'years' => '1993, 1994'],
        ]);

        $result = $this->repository->getMostTitlesByType('Division');

        $this->assertCount(2, $result);
        $this->assertSame('Heat', $result[0]['team_name']);
        $this->assertSame('Nets', $result[1]['team_name']);
    }
}
