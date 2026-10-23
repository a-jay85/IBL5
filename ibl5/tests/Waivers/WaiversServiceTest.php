<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Season\Season;
use Team\Contracts\TeamQueryRepositoryInterface;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\WaiversService;
use Waivers\WaiversView;

class WaiversServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $interfaces = class_implements(WaiversService::class);
        self::assertContains(
            WaiversServiceInterface::class,
            $interfaces ? $interfaces : [],
        );
    }

    public function testAcceptsInjectedSlotCounter(): void
    {
        $service = new WaiversService(
            self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class),
            self::createStub(WaiversProcessorInterface::class),
            self::createStub(TeamQueryRepositoryInterface::class),
            self::createStub(\mysqli::class),
            null,
            self::createStub(\Waivers\Contracts\WaiversRosterSlotCounterInterface::class)
        );

        self::assertInstanceOf(WaiversServiceInterface::class, $service);
    }

    public function testAddOptionsOmitWaitTimeForRecentlyDroppedPlayer(): void
    {
        $player = self::createStub(Player::class);
        $player->method('getTimeDroppedOnWaivers')->willReturn(time() - 60);
        $player->method('getPlayerID')->willReturn(7);
        $player->method('getName')->willReturn('Test Player');

        $processor = self::createStub(WaiversProcessorInterface::class);
        $processor->method('getPlayerContractDisplay')->willReturn('100');

        $service = new WaiversService(
            self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class),
            $processor,
            self::createStub(TeamQueryRepositoryInterface::class),
            self::createStub(\mysqli::class)
        );

        $method = new \ReflectionMethod($service, 'buildPlayerOptions');
        $players = $method->invoke($service, [$player], self::createStub(Season::class));

        self::assertIsArray($players);
        self::assertArrayHasKey(0, $players);
        self::assertIsArray($players[0]);
        self::assertSame(['playerID', 'playerName', 'contract'], array_keys($players[0]));

        /** @var list<array{playerID: int, playerName: string, contract: string}> $players */
        $html = (new WaiversView())->renderWaiverForm('Test Team', 1, 'add', $players, 5, 5);

        self::assertStringContainsString('Test Player 100', $html);
        self::assertStringNotContainsString('Clears in', $html);
    }
}
