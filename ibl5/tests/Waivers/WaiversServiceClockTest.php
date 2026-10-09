<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Season\Season;
use Team\Contracts\TeamQueryRepositoryInterface;
use Tests\Clock\FixedClock;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversViewInterface;
use Waivers\WaiversService;

/**
 * Pins the injected clock so the waiver wait-time calculation receives a deterministic "now".
 */
class WaiversServiceClockTest extends TestCase
{
    public function testBuildPlayerOptionsPassesInjectedClockTimeToWaitCalculation(): void
    {
        $dropTime = 1_700_000_000;
        // One second before the 24-hour waiver window clears.
        $pinnedNow = $dropTime + 86400 - 1;

        $player = self::createStub(Player::class);
        $player->method('getTimeDroppedOnWaivers')->willReturn($dropTime);
        $player->method('getPlayerID')->willReturn(7);
        $player->method('getName')->willReturn('Test Player');

        $processor = $this->createMock(WaiversProcessorInterface::class);
        $processor->method('getPlayerContractDisplay')->willReturn('100');
        $processor->expects(self::once())
            ->method('getWaiverWaitTime')
            ->with($dropTime, $pinnedNow)
            ->willReturn('(Clears in 0 h, 0 m, 1 s)');

        $view = self::createStub(WaiversViewInterface::class);
        $view->method('buildPlayerOption')->willReturn('<option>');

        $service = new WaiversService(
            self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class),
            $processor,
            $view,
            self::createStub(TeamQueryRepositoryInterface::class),
            self::createStub(\mysqli::class),
            null,
            new FixedClock($pinnedNow)
        );

        $method = new \ReflectionMethod($service, 'buildPlayerOptions');
        $options = $method->invoke($service, [$player], 'add', self::createStub(Season::class));

        self::assertSame(['<option>'], $options);
    }
}
