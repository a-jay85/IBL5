<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Repositories\PlayerLookupRepository;

/**
 * Tests PlayerLookupRepository against real MariaDB — player+team JOIN row shape
 * (backlog 7.18: shared trait must not change returned data).
 */
#[Group('database')]
class PlayerLookupRepositoryTest extends DatabaseTestCase
{
    private PlayerLookupRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlayerLookupRepository($this->db);
    }

    public function testGetPlayerByIdReturnsPlayerWithTeamJoinFields(): void
    {
        $this->insertTestPlayer(200011001, 'Lookup ById Test', ['teamid' => 1]);

        $result = $this->repo->getPlayerByID(200011001);

        self::assertNotNull($result);
        self::assertSame(200011001, $result['pid']);
        self::assertSame('Lookup ById Test', $result['name']);
        self::assertSame('Metros', $result['teamname']);
        self::assertArrayHasKey('color1', $result);
        self::assertArrayHasKey('color2', $result);
    }

    public function testGetPlayerByIdReturnsNullForUnknown(): void
    {
        self::assertNull($this->repo->getPlayerByID(99999));
    }

    public function testGetPlayerByNameReturnsPlayerWithTeamJoinFields(): void
    {
        $this->insertTestPlayer(200011002, 'Lookup ByName Test', ['teamid' => 1]);

        $result = $this->repo->getPlayerByName('Lookup ByName Test');

        self::assertNotNull($result);
        self::assertSame(200011002, $result['pid']);
        self::assertSame('Lookup ByName Test', $result['name']);
        self::assertSame('Metros', $result['teamname']);
        self::assertArrayHasKey('color1', $result);
        self::assertArrayHasKey('color2', $result);
    }

    public function testGetPlayerByNameReturnsNullForUnknown(): void
    {
        self::assertNull($this->repo->getPlayerByName('NoSuchLookupPlayer999999'));
    }

    public function testGetPlayerIdFromPlayerNameReturnsPid(): void
    {
        $this->insertTestPlayer(200011003, 'Lookup IdFromName Test');

        self::assertSame(200011003, $this->repo->getPlayerIDFromPlayerName('Lookup IdFromName Test'));
    }

    public function testGetPlayerIdFromPlayerNameReturnsNullForUnknown(): void
    {
        self::assertNull($this->repo->getPlayerIDFromPlayerName('NoSuchLookupPlayer999999'));
    }
}
