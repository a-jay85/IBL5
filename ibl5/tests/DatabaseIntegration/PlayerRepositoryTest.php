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
        $this->insertTestPlayer(200010002, 'PLR LoadTest', [
            'age' => 25,
            'pos' => 'SF',
            'tid' => 1,
        ]);

        $player = $this->repo->loadByID(200010002);

        self::assertSame(200010002, $player->playerID);
        self::assertSame('PLR LoadTest', $player->name);
        self::assertSame(1, $player->teamID);
        self::assertSame('Metros', $player->teamName);
        self::assertSame('SF', $player->position);
        self::assertSame(25, $player->age);
    }

    public function testLoadByIdThrowsForUnknownPlayer(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Player with ID 99999 not found');

        $this->repo->loadByID(99999);
    }

    public function testGetPlayerStatsReturnsRowWithNativeTypes(): void
    {
        $this->insertTestPlayer(200010003, 'PLR StatsTest', [
            'tid' => 2,
            'pos' => 'SG',
        ]);

        $row = $this->repo->getPlayerStats(200010003);

        self::assertNotNull($row);
        self::assertSame(200010003, $row['pid']);
        self::assertSame(2, $row['tid']);
        self::assertSame('SG', $row['pos']);
    }

    public function testGetPlayerStatsReturnsNullForUnknownPlayer(): void
    {
        $row = $this->repo->getPlayerStats(99999);

        self::assertNull($row);
    }

    public function testGetFreeAgencyDemandsReturnsZeroesWhenNoDemandRow(): void
    {
        $this->insertTestPlayer(200010004, 'PLR DemandTst');

        $demands = $this->repo->getFreeAgencyDemands(200010004);

        self::assertSame(0, $demands['dem1']);
        self::assertSame(0, $demands['dem2']);
        self::assertSame(0, $demands['dem3']);
        self::assertSame(0, $demands['dem4']);
        self::assertSame(0, $demands['dem5']);
        self::assertSame(0, $demands['dem6']);
    }

    public function testGetAwardsReturnsEmptyWhenNoAwards(): void
    {
        $this->insertTestPlayer(200010005, 'PLR NoAwards');

        $awards = $this->repo->getAwards('PLR NoAwards');

        self::assertSame([], $awards);
    }

    public function testGetAwardsReturnsRowAfterInsert(): void
    {
        $this->insertRow('ibl_awards', [
            'year' => 2025,
            'Award' => 'MVP',
            'name' => 'PLR AwardTest',
        ]);

        $awards = $this->repo->getAwards('PLR AwardTest');

        self::assertCount(1, $awards);
        self::assertSame('MVP', $awards[0]['Award']);
        self::assertSame('PLR AwardTest', $awards[0]['name']);
    }
}
