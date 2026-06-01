<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use EngineBundle\BundleSerializer;
use EngineBundle\EmptyScheduleException;
use EngineBundle\EngineBundleRepository;
use EngineBundle\EngineBundleService;
use EngineRunner\Contracts\EngineRunnerInterface;
use EngineShadow\EngineShadowLoader;
use EngineShadow\EngineShadowRepository;
use EngineShadow\EngineShadowRunService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * DB-integration test for EngineShadowRunService against the real schema. A STUB
 * EngineRunnerInterface drives the streaming callback with a captured NDJSON game
 * (no Go binary), proving the service writes shadow rows through the real loader +
 * repository and never touches canonical box scores.
 *
 * #[Group('database')] is declared here (NOT inherited) so this runs in the DB job.
 */
#[Group('database')]
final class EngineShadowRunServiceTest extends DatabaseTestCase
{
    private const GAME_DATE = '2026-03-10';
    private const VISITOR_TID = 3;
    private const HOME_TID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        // Roster the engine's pids so teamid resolves; an unplayed 2026 game so
        // buildBundleJson() produces a non-empty bundle (the stub runner ignores
        // the bundle content and emits the fixture game).
        $this->insertTestPlayer(901, 'Visitor Star', ['teamid' => self::VISITOR_TID]);
        $this->insertTestPlayer(902, 'Home Center', ['teamid' => self::HOME_TID]);
        $this->insertScheduleRow(2026, self::GAME_DATE, self::VISITOR_TID, 0, self::HOME_TID, 0);
    }

    #[Test]
    public function runForSeasonWritesShadowRowsAndLeavesCanonicalUntouched(): void
    {
        $canonicalPlayersBefore = $this->countRows('ibl_box_scores');
        $canonicalTeamsBefore = $this->countRows('ibl_box_scores_teams');

        $service = $this->makeService($this->stubRunnerEmitting($this->oneGame(), 12345));
        $summary = $service->runForSeason(2026);

        self::assertSame(1, $summary->gamesLoaded);
        self::assertSame(12345, $summary->seed);

        // Shadow rows landed (queried directly — loadOneGame returns void).
        self::assertSame(2, $this->countShadowRowsForGame('ibl_box_scores_engine_shadow'));
        self::assertSame(2, $this->countShadowRowsForGame('ibl_box_scores_engine_shadow_teams'));

        // Canonical box scores untouched.
        self::assertSame($canonicalPlayersBefore, $this->countRows('ibl_box_scores'), 'canonical player boxscores changed');
        self::assertSame($canonicalTeamsBefore, $this->countRows('ibl_box_scores_teams'), 'canonical team boxscores changed');
    }

    #[Test]
    public function emptySchedulePropagatesException(): void
    {
        // Year 2099 has no unplayed games → buildBundleJson throws, and the service
        // propagates it (the CLI is the catch point, not the service).
        $runner = $this->createMock(EngineRunnerInterface::class);
        $runner->expects(self::never())->method('runStreaming');

        $this->expectException(EmptyScheduleException::class);
        $this->makeService($runner)->runForSeason(2099);
    }

    private function makeService(EngineRunnerInterface $runner): EngineShadowRunService
    {
        $shadowRepo = new EngineShadowRepository($this->db);

        return new EngineShadowRunService(
            new EngineBundleService(new EngineBundleRepository($this->db), new BundleSerializer()),
            $runner,
            new EngineShadowLoader($shadowRepo),
            $shadowRepo,
        );
    }

    /**
     * @param array<string, mixed> $game
     */
    private function stubRunnerEmitting(array $game, int $seed): EngineRunnerInterface
    {
        $runner = self::createStub(EngineRunnerInterface::class);
        $runner->method('runStreaming')->willReturnCallback(
            function (string $bundleJson, callable $onGame) use ($game, $seed): int {
                $onGame($game, $seed);
                return $seed;
            }
        );

        return $runner;
    }

    /** @return array<string, mixed> One NDJSON game: 2 player boxes + visitor/home team boxes. */
    private function oneGame(): array
    {
        return [
            'date' => self::GAME_DATE,
            'home_team_id' => self::HOME_TID,
            'visitor_team_id' => self::VISITOR_TID,
            'game_of_that_day' => 1,
            'sim_game_type' => 2,
            'player_boxes' => [
                ['pid' => 901, 'pos' => 'PG', 'gameMIN' => 36, 'game2GM' => 8],
                ['pid' => 902, 'pos' => 'C', 'gameMIN' => 30, 'game2GM' => 6],
            ],
            'team_boxes' => [
                ['team_id' => self::VISITOR_TID, 'is_home' => false, 'q1' => 28, 'q2' => 26, 'q3' => 24, 'q4' => 25, 'ot' => []],
                ['team_id' => self::HOME_TID, 'is_home' => true, 'q1' => 30, 'q2' => 27, 'q3' => 26, 'q4' => 24, 'ot' => []],
            ],
        ];
    }

    private function countShadowRowsForGame(string $table): int
    {
        $allowed = ['ibl_box_scores_engine_shadow', 'ibl_box_scores_engine_shadow_teams'];
        self::assertContains($table, $allowed, 'unexpected shadow table name');
        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM `$table` WHERE game_date = ?");
        self::assertNotFalse($stmt);
        $date = self::GAME_DATE;
        $stmt->bind_param('s', $date);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) $row['cnt'];
    }

    private function countRows(string $table): int
    {
        $result = $this->db->query("SELECT COUNT(*) AS cnt FROM `$table`");
        self::assertInstanceOf(\mysqli_result::class, $result);
        /** @var array{cnt: int} $row */
        $row = $result->fetch_assoc();
        $result->free();

        return (int) $row['cnt'];
    }
}
