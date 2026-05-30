<?php

declare(strict_types=1);

namespace Tests\EngineBundle;

use EngineBundle\BundleSerializer;
use EngineBundle\Contracts\EngineBundleRepositoryInterface;
use EngineBundle\Dto\Game;
use EngineBundle\Dto\Player;
use EngineBundle\Dto\Team;
use EngineBundle\EmptyRosterException;
use EngineBundle\EmptyScheduleException;
use EngineBundle\EngineBundleService;
use PHPUnit\Framework\TestCase;

class EngineBundleServiceTest extends TestCase
{
    private function aGame(int $gameType = 2): Game
    {
        return new Game(homeTeamId: 1, visitorTeamId: 2, date: '2026-03-12', gameType: $gameType);
    }

    private function aPlayer(): Player
    {
        return Player::fromRow(['pid' => 101, 'name' => 'Test Player', 'teamid' => 1]);
    }

    /**
     * @param list<Game>   $games
     * @param list<Player> $players
     */
    private function stubRepo(array $games, array $players, ?Team $team = null): EngineBundleRepositoryInterface
    {
        $repo = self::createStub(EngineBundleRepositoryInterface::class);
        $repo->method('getUnplayedGames')->willReturn($games);
        $repo->method('getPlayers')->willReturn($players);
        $repo->method('getTeams')->willReturn($team !== null ? [$team] : []);
        return $repo;
    }

    /** @return array<string, mixed> */
    private function buildAndDecode(EngineBundleRepositoryInterface $repo, ?int $seed = 42, int $gameType = 2): array
    {
        $service = new EngineBundleService($repo, new BundleSerializer());
        $json = $service->buildBundleJson(2026, null, null, $gameType, $seed);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }

    // ── Empty guards (negative paths) ────────────────────────────────

    public function testThrowsWhenNoGamesToSimulate(): void
    {
        $service = new EngineBundleService($this->stubRepo([], [$this->aPlayer()]), new BundleSerializer());
        $this->expectException(EmptyScheduleException::class);
        $service->buildBundleJson(2026);
    }

    public function testThrowsWhenRosterEmpty(): void
    {
        $service = new EngineBundleService($this->stubRepo([$this->aGame()], []), new BundleSerializer());
        $this->expectException(EmptyRosterException::class);
        $service->buildBundleJson(2026);
    }

    // ── game_type flow ───────────────────────────────────────────────

    public function testForwardsGameTypeToRepositoryAndOutput(): void
    {
        // Verify the service passes the caller's game_type to the repository,
        // and the resulting game carries it in the output.
        $repo = $this->createMock(EngineBundleRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('getUnplayedGames')
            ->with(2026, null, null, 4)
            ->willReturn([$this->aGame(4)]);
        $repo->method('getPlayers')->willReturn([$this->aPlayer()]);
        $repo->method('getTeams')->willReturn([]);

        $decoded = $this->buildAndDecode($repo, seed: 1, gameType: 4);
        /** @var list<array<string, mixed>> $schedule */
        $schedule = $decoded['schedule'];
        self::assertSame(4, $schedule[0]['game_type']);
    }

    public function testDefaultGameTypeIsRegularSeason(): void
    {
        $decoded = $this->buildAndDecode($this->stubRepo([$this->aGame(2)], [$this->aPlayer()]));
        /** @var list<array<string, mixed>> $schedule */
        $schedule = $decoded['schedule'];
        self::assertSame(2, $schedule[0]['game_type']);
        self::assertSame(EngineBundleService::DEFAULT_GAME_TYPE, $schedule[0]['game_type']);
    }

    // ── seed ─────────────────────────────────────────────────────────

    public function testPreservesExplicitSeed(): void
    {
        $decoded = $this->buildAndDecode($this->stubRepo([$this->aGame()], [$this->aPlayer()]), seed: 999);
        self::assertSame(999, $decoded['seed']);
    }

    public function testGeneratesSeedInRangeWhenNull(): void
    {
        $decoded = $this->buildAndDecode($this->stubRepo([$this->aGame()], [$this->aPlayer()]), seed: null);
        self::assertIsInt($decoded['seed']);
        self::assertGreaterThanOrEqual(0, $decoded['seed']);
        self::assertLessThanOrEqual(PHP_INT_MAX, $decoded['seed']);
    }
}
