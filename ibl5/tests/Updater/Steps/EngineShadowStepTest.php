<?php

declare(strict_types=1);

namespace Tests\Updater\Steps;

use EngineBundle\BundleSerializer;
use EngineBundle\Contracts\BundleSerializerInterface;
use EngineBundle\Contracts\EngineBundleRepositoryInterface;
use EngineBundle\Dto\Game;
use EngineBundle\Dto\Player;
use EngineBundle\Dto\Team;
use EngineBundle\EngineBundleService;
use EngineRunner\Contracts\EngineRunnerInterface;
use EngineRunner\EngineRunnerException;
use EngineShadow\EngineShadowLoader;
use EngineShadow\EngineShadowRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Updater\Steps\EngineShadowStep;

/**
 * Unit tests for EngineShadowStep — the flag gate and failure-isolation behavior,
 * exercised without a database or a real engine binary.
 */
final class EngineShadowStepTest extends TestCase
{
    private const SEASON_YEAR = 2026;

    #[Test]
    public function flagOffSkipsWithoutRunningEngine(): void
    {
        $runner = $this->createMock(EngineRunnerInterface::class);
        $runner->expects(self::never())->method('run');

        $step = new EngineShadowStep(
            $this->bundleServiceThatWouldSucceed(),
            $runner,
            $this->loaderWithoutDb(),
            self::SEASON_YEAR,
            enabled: false,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('off', $result->detail);
    }

    #[Test]
    public function emptyScheduleSkips(): void
    {
        $repo = self::createStub(EngineBundleRepositoryInterface::class);
        $repo->method('getUnplayedGames')->willReturn([]); // → EmptyScheduleException

        $runner = $this->createMock(EngineRunnerInterface::class);
        $runner->expects(self::never())->method('run');

        $step = new EngineShadowStep(
            new EngineBundleService($repo, new BundleSerializer()),
            $runner,
            $this->loaderWithoutDb(),
            self::SEASON_YEAR,
            enabled: true,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('Nothing to shadow-sim', $result->detail);
    }

    #[Test]
    public function emptyRosterSkips(): void
    {
        $repo = self::createStub(EngineBundleRepositoryInterface::class);
        $repo->method('getUnplayedGames')->willReturn([new Game(1, 3, '2026-03-10', 2)]);
        $repo->method('getPlayers')->willReturn([]); // → EmptyRosterException

        $step = new EngineShadowStep(
            new EngineBundleService($repo, new BundleSerializer()),
            self::createStub(EngineRunnerInterface::class),
            $this->loaderWithoutDb(),
            self::SEASON_YEAR,
            enabled: true,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('Nothing to shadow-sim', $result->detail);
    }

    #[Test]
    public function successPathLoadsResultAndReportsCounts(): void
    {
        // Recording repository: real insert/loader logic runs (JSON parsing, team
        // ordering, sumOt, pid→teamid), but writes are captured in-memory — no DB.
        $fakeRepo = new class (new \mysqli()) extends EngineShadowRepository {
            public int $playerInserts = 0;
            public int $teamInserts = 0;

            public function getTeamIdsForPids(array $pids): array
            {
                return [901 => 3];
            }

            public function transaction(callable $fn): mixed
            {
                return $fn();
            }

            // Dedupe delete is a no-op here so execute() counts only inserts.
            public function deleteShadowGame(
                string $gameDate,
                int $visitorTeamId,
                int $homeTeamId,
                int $gameOfThatDay,
            ): int {
                return 0;
            }

            protected function execute(string $query, string $types = '', mixed ...$params): int
            {
                if (str_contains($query, 'engine_shadow_teams')) {
                    $this->teamInserts++;
                } elseif (str_contains($query, 'engine_shadow')) {
                    $this->playerInserts++;
                }
                return 1;
            }
        };

        $resultJson = (string) json_encode([
            'seed' => 7,
            'games' => [[
                'date' => '2026-03-10', 'home_team_id' => 1, 'visitor_team_id' => 3,
                'game_of_that_day' => 1, 'sim_game_type' => 2,
                'player_boxes' => [
                    ['pid' => 901, 'pos' => 'PG', 'gameMIN' => 30, 'game2GM' => 5],
                    ['pid' => 902, 'pos' => 'C', 'gameMIN' => 28, 'game2GM' => 6],
                ],
                'team_boxes' => [
                    ['team_id' => 3, 'is_home' => false, 'q1' => 28, 'q2' => 26, 'q3' => 24, 'q4' => 25, 'ot' => [3]],
                    ['team_id' => 1, 'is_home' => true, 'q1' => 30, 'q2' => 27, 'q3' => 26, 'q4' => 24, 'ot' => []],
                ],
            ]],
        ], JSON_THROW_ON_ERROR);

        $runner = self::createStub(EngineRunnerInterface::class);
        $runner->method('run')->willReturn($resultJson);

        $step = new EngineShadowStep(
            $this->bundleServiceThatWouldSucceed(),
            $runner,
            new EngineShadowLoader($fakeRepo),
            self::SEASON_YEAR,
            enabled: true,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertSame(2, $fakeRepo->playerInserts);
        self::assertSame(2, $fakeRepo->teamInserts);
        self::assertStringContainsString('1 games', $result->detail);
        self::assertStringContainsString('2 player rows', $result->detail);
        self::assertStringContainsString('2 team rows', $result->detail);
    }

    #[Test]
    public function passesGameCapThroughServiceToRepository(): void
    {
        // EngineBundleService is final (cannot be mocked), so assert the cap on
        // the seam below it: a createMock repo proves step → service → repo
        // threads SHADOW_MAX_GAMES_PER_RUN as getUnplayedGames' 5th arg.
        $repo = $this->createMock(EngineBundleRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('getUnplayedGames')
            ->with(
                self::SEASON_YEAR,
                null,
                null,
                EngineBundleService::DEFAULT_GAME_TYPE,
                EngineShadowStep::SHADOW_MAX_GAMES_PER_RUN,
            )
            ->willReturn([new Game(1, 3, '2026-03-10', 2)]);
        $repo->method('getPlayers')->willReturn([new Player(['pid' => 1])]);
        $repo->method('getTeams')->willReturn([new Team(1, 'Team One')]);

        // Empty-games result ⇒ the loader (unconnected mysqli) is a clean no-op:
        // collectPids([]) → getTeamIdsForPids([]) short-circuits before any DB.
        $runner = self::createStub(EngineRunnerInterface::class);
        $runner->method('run')->willReturn('{"seed":1,"games":[]}');

        $step = new EngineShadowStep(
            new EngineBundleService($repo, new BundleSerializer()),
            $runner,
            $this->loaderWithoutDb(),
            self::SEASON_YEAR,
            enabled: true,
        );

        $result = $step->execute();

        self::assertTrue($result->success);
    }

    #[Test]
    public function runnerFailureReturnsFailureWithoutThrowing(): void
    {
        $runner = self::createStub(EngineRunnerInterface::class);
        $runner->method('run')->willThrowException(new EngineRunnerException('binary not found'));

        $step = new EngineShadowStep(
            $this->bundleServiceThatWouldSucceed(),
            $runner,
            $this->loaderWithoutDb(),
            self::SEASON_YEAR,
            enabled: true,
        );

        $result = $step->execute();

        self::assertFalse($result->success);
        self::assertStringContainsString('Engine shadow run failed', $result->errorMessage);
        self::assertStringContainsString('binary not found', $result->errorMessage);
    }

    /** A bundle service whose dependencies yield a non-empty, serializable bundle. */
    private function bundleServiceThatWouldSucceed(): EngineBundleService
    {
        $repo = self::createStub(EngineBundleRepositoryInterface::class);
        $repo->method('getUnplayedGames')->willReturn([new Game(1, 3, '2026-03-10', 2)]);
        $repo->method('getPlayers')->willReturn([new Player(['pid' => 1])]);
        $repo->method('getTeams')->willReturn([new Team(1, 'Team One')]);

        $serializer = self::createStub(BundleSerializerInterface::class);
        $serializer->method('serialize')->willReturn('{"league_id":1,"seed":1,"teams":[],"players":[],"schedule":[]}');

        return new EngineBundleService($repo, $serializer);
    }

    /**
     * Real loader wired to a repository with an unconnected mysqli — never
     * exercised in these flag/failure tests (the loader is reached only on the
     * success path, which is covered by the DB-integration suite).
     */
    private function loaderWithoutDb(): EngineShadowLoader
    {
        return new EngineShadowLoader(new EngineShadowRepository(new \mysqli()));
    }
}
