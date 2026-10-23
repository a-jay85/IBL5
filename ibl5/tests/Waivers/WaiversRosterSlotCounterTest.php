<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Season\Season;
use Team\Contracts\TeamQueryRepositoryInterface;
use Waivers\WaiversRosterSlotCounter;

/**
 * @covers \Waivers\WaiversRosterSlotCounter
 */
class WaiversRosterSlotCounterTest extends TestCase
{
    /**
     * @return list<array{pid: int}>
     */
    private static function rows(int $n): array
    {
        return array_fill(0, $n, ['pid' => 1]);
    }

    /**
     * @param list<array{pid: int}> $allActive
     * @param list<array{pid: int}> $healthyOnly
     */
    private static function buildCounter(array $allActive, array $healthyOnly): WaiversRosterSlotCounter
    {
        $repo = self::createStub(TeamQueryRepositoryInterface::class);
        $repo->method('getHealthyAndInjuredPlayersOrderedByName')->willReturn($allActive);
        $repo->method('getHealthyPlayersOrderedByName')->willReturn($healthyOnly);

        return new WaiversRosterSlotCounter($repo);
    }

    public function testCountOpenSlotsSubtractsRosterRowsFromMax(): void
    {
        $counter = self::buildCounter(self::rows(11), self::rows(9));

        self::assertSame(
            ['open' => 4, 'healthy' => 6],
            $counter->countOpenSlots(7, self::createStub(Season::class)),
        );
    }

    public function testFullRosterYieldsZeroOpenSlots(): void
    {
        $counter = self::buildCounter(self::rows(15), self::rows(15));

        $result = $counter->countOpenSlots(7, self::createStub(Season::class));

        self::assertSame(0, $result['open']);
        self::assertSame(0, $result['healthy']);
    }

    public function testFourteenPlayersYieldOneOpenSlot(): void
    {
        $counter = self::buildCounter(self::rows(14), self::rows(14));

        $result = $counter->countOpenSlots(7, self::createStub(Season::class));

        self::assertSame(1, $result['open']);
    }

    public function testOverfullRosterYieldsNegativeOpenSlotsUnclamped(): void
    {
        $counter = self::buildCounter(self::rows(16), self::rows(16));

        $result = $counter->countOpenSlots(7, self::createStub(Season::class));

        self::assertSame(-1, $result['open']);
        self::assertSame(-1, $result['healthy']);
    }

    public function testForwardsTeamIdAndSeasonToBothRepositoryQueries(): void
    {
        $season = self::createStub(Season::class);

        $repo = $this->createMock(TeamQueryRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('getHealthyAndInjuredPlayersOrderedByName')
            ->with(7, $this->identicalTo($season))
            ->willReturn([]);
        $repo->expects($this->once())
            ->method('getHealthyPlayersOrderedByName')
            ->with(7, $this->identicalTo($season))
            ->willReturn([]);

        (new WaiversRosterSlotCounter($repo))->countOpenSlots(7, $season);
    }
}
