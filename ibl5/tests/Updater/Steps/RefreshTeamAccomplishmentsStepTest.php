<?php

declare(strict_types=1);

namespace Tests\Updater\Steps;

use Cache\Contracts\DatabaseCacheInterface;
use PHPUnit\Framework\TestCase;
use Team\Contracts\TeamRepositoryInterface;
use Team\TeamAccomplishmentsCache;
use Tests\Clock\FixedClock;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\RefreshTeamAccomplishmentsStep;

/**
 * @covers \Updater\Steps\RefreshTeamAccomplishmentsStep
 */
final class RefreshTeamAccomplishmentsStepTest extends TestCase
{
    private const STARTED_AT = 1000.0;

    public function testImplementsPipelineStepInterface(): void
    {
        self::assertContains(
            PipelineStepInterface::class,
            (array) class_implements(RefreshTeamAccomplishmentsStep::class),
        );
    }

    public function testRebuildsEveryRealTeamWithinBudget(): void
    {
        $repository = self::createMock(TeamRepositoryInterface::class);
        $repository->expects($this->exactly(2))->method('getTeamAccomplishments')->willReturn([]);
        $cache = self::createMock(DatabaseCacheInterface::class);
        $cache->expects($this->exactly(2))->method('set');

        $result = $this->buildStep($repository, $cache, ['Bulls', 'Aces'], self::STARTED_AT + 10.0)->execute();

        self::assertTrue($result->success);
        self::assertFalse($result->skipped);
        self::assertStringContainsString('2 teams', $result->detail);
    }

    public function testOverBudgetInvalidatesEveryTeamAndSkips(): void
    {
        $repository = self::createMock(TeamRepositoryInterface::class);
        $repository->expects($this->never())->method('getTeamAccomplishments');
        $cache = self::createMock(DatabaseCacheInterface::class);
        $cache->expects($this->exactly(2))->method('delete');
        $cache->expects($this->never())->method('set');

        $result = $this->buildStep($repository, $cache, ['Bulls', 'Aces'], self::STARTED_AT + 250.0)->execute();

        self::assertTrue($result->skipped);
        self::assertStringContainsString('cleared 2', $result->detail);
    }

    public function testRebuildFailureInvalidatesAndReturnsFailure(): void
    {
        $repository = self::createMock(TeamRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getTeamAccomplishments')
            ->willThrowException(new \RuntimeException('db gone'));
        $cache = self::createMock(DatabaseCacheInterface::class);
        $cache->expects($this->exactly(2))->method('delete');
        $cache->expects($this->never())->method('set');

        $result = $this->buildStep($repository, $cache, ['Bulls', 'Aces'], self::STARTED_AT + 10.0)->execute();

        self::assertFalse($result->success);
        self::assertStringContainsString('db gone', $result->errorMessage);
        self::assertStringContainsString('cleared 2', $result->errorMessage);
    }

    public function testTeamListFailureReturnsFailureWithoutTouchingCache(): void
    {
        $repository = self::createMock(TeamRepositoryInterface::class);
        $repository->expects($this->never())->method('getTeamAccomplishments');
        $cache = self::createMock(DatabaseCacheInterface::class);
        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('getStale');
        $cache->expects($this->never())->method('set');
        $cache->expects($this->never())->method('delete');

        $step = new RefreshTeamAccomplishmentsStep(
            static fn (): TeamAccomplishmentsCache => new TeamAccomplishmentsCache($repository, $cache),
            static function (): array {
                throw new \RuntimeException('team list unavailable');
            },
            new FixedClock(0, self::STARTED_AT + 10.0),
            self::STARTED_AT,
        );

        $result = $step->execute();

        self::assertFalse($result->success);
        self::assertStringContainsString('team list unavailable', $result->errorMessage);
    }

    public function testEmptyTeamListSucceedsWithZeroTeams(): void
    {
        $repository = self::createStub(TeamRepositoryInterface::class);
        $cache = self::createMock(DatabaseCacheInterface::class);
        $cache->expects($this->never())->method('set');

        $result = $this->buildStep($repository, $cache, [], self::STARTED_AT + 10.0)->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('0 teams', $result->detail);
    }

    /**
     * @param list<string> $teamNames
     */
    private function buildStep(
        TeamRepositoryInterface $repository,
        DatabaseCacheInterface $cache,
        array $teamNames,
        float $now,
    ): RefreshTeamAccomplishmentsStep {
        return new RefreshTeamAccomplishmentsStep(
            static fn (): TeamAccomplishmentsCache => new TeamAccomplishmentsCache($repository, $cache),
            static fn (): array => $teamNames,
            new FixedClock(0, $now),
            self::STARTED_AT,
        );
    }
}
