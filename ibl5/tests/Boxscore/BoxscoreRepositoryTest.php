<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use Boxscore\BoxscoreRepository;
use Boxscore\Contracts\BoxscoreRepositoryInterface;
use League\LeagueContext;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \Boxscore\BoxscoreRepository
 */
class BoxscoreRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private BoxscoreRepository $repository;
    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new BoxscoreRepository($this->mockDb);
    }

    protected function tearDown(): void
    {
        if ($this->previousErrorLog !== false) {
            ini_set('error_log', $this->previousErrorLog);
            $this->previousErrorLog = false;
        }
        // setSharedLeagueContext() writes a static slot that leaks into every
        // later test in the process; always clear it.
        BoxscoreRepository::clearSharedLeagueContext();
        parent::tearDown();
    }

    public function testDeletePreseasonBoxScoresExecutesTwoQueries(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->deletePreseasonBoxScores(2025);

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(2, $queries);
        $this->assertStringContainsString('DELETE FROM ibl_box_scores', $queries[0]);
        $this->assertStringContainsString('DELETE FROM ibl_box_scores_teams', $queries[1]);
    }

    public function testDeletePreseasonBoxScoresUsesCorrectDateRange(): void
    {
        $this->mockDb->setReturnTrue(true);

        $this->repository->deletePreseasonBoxScores(2025);

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('2025-09-01', $queries[0]);
        $this->assertStringContainsString('2025-09-30', $queries[0]);
    }

    public function testDeleteHeatBoxScoresUsesCorrectDateRange(): void
    {
        $this->mockDb->setReturnTrue(true);

        $this->repository->deleteHeatBoxScores(2024);

        $queries = $this->mockDb->getExecutedQueries();
        // HEAT month is October (10)
        $this->assertStringContainsString('2024-10-01', $queries[0]);
        $this->assertStringContainsString('2024-10-31', $queries[0]);
    }

    public function testDeleteRegularSeasonAndPlayoffsBoxScoresUsesCorrectDateRange(): void
    {
        $this->mockDb->setReturnTrue(true);

        $this->repository->deleteRegularSeasonAndPlayoffsBoxScores(2024);

        $queries = $this->mockDb->getExecutedQueries();
        // Regular season starts November (11), playoffs end June (06)
        $this->assertStringContainsString('2024-11-01', $queries[0]);
        $this->assertStringContainsString('2025-06-30', $queries[0]);
    }

    public function testDeleteThrowsExceptionOnFailure(): void
    {
        $this->mockDb->setReturnTrue(false);
        $errorLog = ini_get('error_log');
        $this->previousErrorLog = $errorLog ? $errorLog : '';
        ini_set('error_log', '/dev/null');

        $this->expectException(\RuntimeException::class);
        $this->repository->deletePreseasonBoxScores(2025);
    }

    public function testInsertTeamBoxscoreExecutesInsertQuery(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->insertTeamBoxscore([
            'game_date' => '2025-01-15',
            'name' => 'TestTeam',
            'game_of_that_day' => 1,
            'visitor_teamid' => 1,
            'home_teamid' => 2,
            'attendance' => 15000,
            'capacity' => 18000,
            'visitor_wins' => 10,
            'visitor_losses' => 5,
            'home_wins' => 8,
            'home_losses' => 7,
            'visitor_q1_points' => 25,
            'visitor_q2_points' => 30,
            'visitor_q3_points' => 20,
            'visitor_q4_points' => 28,
            'visitor_ot_points' => 0,
            'home_q1_points' => 22,
            'home_q2_points' => 27,
            'home_q3_points' => 24,
            'home_q4_points' => 30,
            'home_ot_points' => 0,
            'game_2gm' => 40,
            'game_2ga' => 85,
            'game_ftm' => 20,
            'game_fta' => 25,
            'game_3gm' => 10,
            'game_3ga' => 30,
            'game_orb' => 12,
            'game_drb' => 30,
            'game_ast' => 25,
            'game_stl' => 8,
            'game_tov' => 15,
            'game_blk' => 5,
            'game_pf' => 20,
        ]);

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO ibl_box_scores_teams', $queries[0]);
        $this->assertSame(1, $result);
    }

    public function testInsertPlayerBoxscoreExecutesInsertQuery(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->insertPlayerBoxscore(
            '2025-01-15',
            'test-uuid-1234',
            'John Smith',
            'PG',
            101,
            1,
            2,
            1,
            15000,
            20000,
            10,
            5,
            12,
            8,
            1,
            32,
            8,
            15,
            4,
            5,
            3,
            7,
            2,
            6,
            7,
            2,
            3,
            1,
            3,
        );

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO ibl_box_scores', $queries[0]);
        $this->assertSame(1, $result);
    }

    public function testConstructorAcceptsNullLeagueContext(): void
    {
        $repo = new BoxscoreRepository($this->mockDb, null);
        $this->assertContains(BoxscoreRepositoryInterface::class, (array) class_implements($repo));
    }

    public function testOlympicsContextUsesOlympicsTables(): void
    {
        // Drive the production STATIC path: the repo gets no instance context;
        // the backtick-quoted table names in its SQL are rewritten by
        // executeQuery()->rewriteTableNames() because the shared context is Olympics.
        $context = new LeagueContext();
        $context->setLeague(LeagueContext::LEAGUE_OLYMPICS);
        BoxscoreRepository::setSharedLeagueContext($context);

        $repo = new BoxscoreRepository($this->mockDb);
        $this->mockDb->setReturnTrue(true);

        $repo->deletePreseasonBoxScores(2025);

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(2, $queries);
        $this->assertStringContainsString('DELETE FROM ibl_olympics_box_scores', $queries[0]);
        $this->assertStringContainsString('DELETE FROM ibl_olympics_box_scores_teams', $queries[1]);
    }

    public function testOlympicsContextInsertsIntoOlympicsTables(): void
    {
        $context = new LeagueContext();
        $context->setLeague(LeagueContext::LEAGUE_OLYMPICS);
        BoxscoreRepository::setSharedLeagueContext($context);

        $repo = new BoxscoreRepository($this->mockDb);
        $this->mockDb->setReturnTrue(true);

        $repo->insertTeamBoxscore([
            'game_date' => '2025-01-15',
            'name' => 'TestTeam',
            'game_of_that_day' => 1,
            'visitor_teamid' => 1,
            'home_teamid' => 2,
            'attendance' => 15000,
            'capacity' => 18000,
            'visitor_wins' => 10,
            'visitor_losses' => 5,
            'home_wins' => 8,
            'home_losses' => 7,
            'visitor_q1_points' => 25,
            'visitor_q2_points' => 30,
            'visitor_q3_points' => 20,
            'visitor_q4_points' => 28,
            'visitor_ot_points' => 0,
            'home_q1_points' => 22,
            'home_q2_points' => 27,
            'home_q3_points' => 24,
            'home_q4_points' => 30,
            'home_ot_points' => 0,
            'game_2gm' => 40,
            'game_2ga' => 85,
            'game_ftm' => 20,
            'game_fta' => 25,
            'game_3gm' => 10,
            'game_3ga' => 30,
            'game_orb' => 12,
            'game_drb' => 30,
            'game_ast' => 25,
            'game_stl' => 8,
            'game_tov' => 15,
            'game_blk' => 5,
            'game_pf' => 20,
        ]);

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('INSERT INTO ibl_olympics_box_scores_teams', $queries[0]);
    }

    public function testTeamInsertTemplateIsFullyParameterized(): void
    {
        $sql = Boxscore::teamInsertSql('`ibl_box_scores_teams`');
        self::assertSame(34, substr_count($sql, '?'));
        self::assertStringContainsString('VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $sql);
        self::assertStringContainsString('INSERT INTO `ibl_box_scores_teams`', $sql);
    }
}
