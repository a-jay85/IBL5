<?php

declare(strict_types=1);

namespace Tests\EngineShadow;

use EngineBundle\BundleSerializer;
use EngineBundle\Contracts\EngineBundleRepositoryInterface;
use EngineBundle\Dto\Game;
use EngineBundle\Dto\Player;
use EngineBundle\Dto\Team;
use EngineBundle\EngineBundleService;
use EngineRunner\Contracts\EngineRunnerInterface;
use EngineShadow\EngineShadowLoader;
use EngineShadow\EngineShadowRepository;
use EngineShadow\EngineShadowRunService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for EngineShadowRunService — no DB, no Go binary. A stub runner drives
 * the streaming callback so we can assert the summary reports the callback game
 * count and the seed returned by runStreaming.
 */
final class EngineShadowRunServiceTest extends TestCase
{
    #[Test]
    public function summaryReportsGameCountFromCallbackAndSeedFromRunner(): void
    {
        // Real bundle service over a stub repo so buildBundleJson succeeds.
        $bundleRepo = self::createStub(EngineBundleRepositoryInterface::class);
        $bundleRepo->method('getUnplayedGames')->willReturn([new Game(1, 3, '2026-03-10', 2)]);
        $bundleRepo->method('getPlayers')->willReturn([new Player(['pid' => 1])]);
        $bundleRepo->method('getTeams')->willReturn([new Team(1, 'Team One')]);
        $bundleService = new EngineBundleService($bundleRepo, new BundleSerializer());

        // Fake shadow repo: no DB. getAllTeamIdsByPid → []; writes are no-ops.
        $shadowRepo = new class (new \mysqli()) extends EngineShadowRepository {
            public function getAllTeamIdsByPid(): array
            {
                return [];
            }

            public function transaction(callable $fn): mixed
            {
                return $fn();
            }

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
                return 1;
            }
        };
        $loader = new EngineShadowLoader($shadowRepo);

        // Stub runner: invoke the callback twice (two games), return seed 555.
        $game = [
            'date' => '2026-03-10', 'home_team_id' => 1, 'visitor_team_id' => 3,
            'game_of_that_day' => 1, 'sim_game_type' => 2,
            'player_boxes' => [], 'team_boxes' => [],
        ];
        $runner = self::createStub(EngineRunnerInterface::class);
        $runner->method('runStreaming')->willReturnCallback(
            function (string $bundleJson, callable $onGame) use ($game): int {
                $onGame($game, 555);
                $onGame($game, 555);
                return 555;
            }
        );

        $service = new EngineShadowRunService($bundleService, $runner, $loader, $shadowRepo);
        $summary = $service->runForSeason(2026);

        self::assertSame(2, $summary->gamesLoaded, 'game count comes from the streaming callback');
        self::assertSame(555, $summary->seed, 'seed comes from runStreaming');
    }
}
