<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use ComparePlayers\ComparePlayersRepository;

/**
 * Tests ComparePlayersRepository against real MariaDB — player name listings
 * with pipe/no-starter filtering, and player lookups with team color JOINs.
 */
class ComparePlayersRepositoryTest extends DatabaseTestCase
{
    private ComparePlayersRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ComparePlayersRepository($this->db);
    }

    // ── getAllPlayerNames ────────────────────────────────────────

    public function testGetAllPlayerNamesReturnsNonEmptyArray(): void
    {
        $this->insertTestPlayer(200090001, 'Compare Plyr 1', ['ordinal' => 1]);

        $names = $this->repo->getAllPlayerNames();

        self::assertNotEmpty($names);
        self::assertContains('Compare Plyr 1', $names);
    }

    public function testGetAllPlayerNamesExcludesNoStarterAndZeroOrdinal(): void
    {
        $this->insertTestPlayer(200090003, '(no starter)', ['ordinal' => 1]);
        $this->insertTestPlayer(200090004, 'Zero Ordinal', ['ordinal' => 0]);

        $names = $this->repo->getAllPlayerNames();

        self::assertNotContains('(no starter)', $names);
        self::assertNotContains('Zero Ordinal', $names);
    }

    // ── getPlayerByName ─────────────────────────────────────────

    public function testGetPlayerByNameReturnsPlayerWithTeamColors(): void
    {
        $this->insertTestPlayer(200090005, 'Compare Lookup', ['tid' => 1]);

        $result = $this->repo->getPlayerByName('Compare Lookup');

        self::assertNotNull($result);
        self::assertSame(200090005, $result['pid']);
        self::assertSame('Compare Lookup', $result['name']);
        self::assertArrayHasKey('team_city', $result);
        self::assertArrayHasKey('color1', $result);
        self::assertArrayHasKey('color2', $result);
    }

    public function testGetPlayerByNameReturnsNullForUnknown(): void
    {
        $result = $this->repo->getPlayerByName('NoSuchPlayer999999');

        self::assertNull($result);
    }
}
