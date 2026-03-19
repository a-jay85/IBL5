<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Player\PlayerRepository;

/**
 * Tests PlayerRepository against real MariaDB — JOINs, PlayerData hydration, native types.
 */
class PlayerRepositoryTest extends DatabaseTestCase
{
    private PlayerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlayerRepository($this->db);
    }

    public function testLoadByIdReturnsPlayerData(): void
    {
        $player = $this->repo->loadByID(1);

        self::assertSame(1, $player->playerID);
        self::assertSame('Test Player One', $player->name);
        self::assertSame(1, $player->teamID);
        self::assertSame('Metros', $player->teamName);
        self::assertSame('PG', $player->position);
        self::assertSame(27, $player->age);
    }

    public function testLoadByIdThrowsForUnknownPlayer(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Player with ID 99999 not found');

        $this->repo->loadByID(99999);
    }

    public function testGetPlayerStatsReturnsRowWithNativeTypes(): void
    {
        $row = $this->repo->getPlayerStats(1);

        self::assertNotNull($row);
        self::assertSame(1, $row['pid']);
        self::assertSame(1, $row['tid']);
        self::assertSame('PG', $row['pos']);
    }

    public function testGetPlayerStatsReturnsNullForUnknownPlayer(): void
    {
        $row = $this->repo->getPlayerStats(99999);

        self::assertNull($row);
    }

    public function testGetFreeAgencyDemandsReturnsZeroesWhenNoDemandRow(): void
    {
        $demands = $this->repo->getFreeAgencyDemands(1);

        self::assertSame(0, $demands['dem1']);
        self::assertSame(0, $demands['dem2']);
        self::assertSame(0, $demands['dem3']);
        self::assertSame(0, $demands['dem4']);
        self::assertSame(0, $demands['dem5']);
        self::assertSame(0, $demands['dem6']);
    }

    public function testGetAwardsReturnsEmptyWhenNoAwards(): void
    {
        $awards = $this->repo->getAwards('Test Player One');

        self::assertSame([], $awards);
    }

    public function testGetAwardsReturnsRowAfterInsert(): void
    {
        $this->insertRow('ibl_awards', [
            'year' => 2025,
            'Award' => 'MVP',
            'name' => 'Test Player One',
        ]);

        $awards = $this->repo->getAwards('Test Player One');

        self::assertCount(1, $awards);
        self::assertSame('MVP', $awards[0]['Award']);
        self::assertSame('Test Player One', $awards[0]['name']);
    }
}
