<?php

declare(strict_types=1);

namespace Tests\Injuries;

use PHPUnit\Framework\TestCase;
use Injuries\InjuriesRepository;
use League\League;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * InjuriesRepositoryTest - Tests for InjuriesRepository
 *
 * @covers \Injuries\InjuriesRepository
 */
class InjuriesRepositoryTest extends TestCase
{
    public function testGetInjuredPlayersDelegatesToLeague(): void
    {
        $rows = [
            ['pid' => 1, 'name' => 'Player One'],
            ['pid' => 2, 'name' => 'Player Two'],
        ];

        $league = $this->createMock(League::class);
        $league->expects($this->once())
            ->method('getInjuredPlayersResult')
            ->willReturn($rows);

        $repository = new InjuriesRepository(new MockDatabase(), $league);

        $this->assertSame($rows, $repository->getInjuredPlayers());
    }

    public function testGetInjuredPlayersReturnsEmptyWhenLeagueReportsNone(): void
    {
        $league = $this->createMock(League::class);
        $league->expects($this->once())
            ->method('getInjuredPlayersResult')
            ->willReturn([]);

        $repository = new InjuriesRepository(new MockDatabase(), $league);

        $this->assertSame([], $repository->getInjuredPlayers());
    }
}
