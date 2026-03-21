<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Api\Pagination\Paginator;
use Api\Repository\ApiPlayerRepository;

/**
 * Tests ApiPlayerRepository against real MariaDB —
 * player listing, counting, and UUID lookup via vw_player_current view.
 */
class ApiPlayerRepositoryTest extends DatabaseTestCase
{
    private ApiPlayerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ApiPlayerRepository($this->db);
    }

    // ── getPlayers ──────────────────────────────────────────────

    public function testGetPlayersReturnsResults(): void
    {
        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '10'],
            'name',
            ['name', 'pid'],
        );

        $players = $this->repo->getPlayers($paginator);

        self::assertNotEmpty($players);
        self::assertLessThanOrEqual(10, count($players));
    }

    public function testGetPlayersRowHasExpectedStructure(): void
    {
        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '1'],
            'name',
            ['name'],
        );

        $players = $this->repo->getPlayers($paginator);

        self::assertNotEmpty($players);
        $player = $players[0];

        self::assertArrayHasKey('player_uuid', $player);
        self::assertArrayHasKey('pid', $player);
        self::assertArrayHasKey('name', $player);
        self::assertArrayHasKey('position', $player);
        self::assertArrayHasKey('age', $player);
        self::assertArrayHasKey('current_salary', $player);
    }

    public function testGetPlayersFilterByPosition(): void
    {
        $this->insertTestPlayer(200000080, 'DB Test PG Player', [
            'pos' => 'PG',
            'stats_gm' => 10,
        ]);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '100'],
            'name',
            ['name'],
        );

        $players = $this->repo->getPlayers($paginator, ['position' => 'PG']);

        self::assertNotEmpty($players);
        foreach ($players as $player) {
            self::assertSame('PG', $player['position']);
        }
    }

    public function testGetPlayersFilterBySearch(): void
    {
        $this->insertTestPlayer(200000081, 'DB UniqueSearchName Batch7', [
            'stats_gm' => 5,
        ]);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '100'],
            'name',
            ['name'],
        );

        $players = $this->repo->getPlayers($paginator, ['search' => 'UniqueSearchName']);

        self::assertNotEmpty($players);
        $names = array_column($players, 'name');
        self::assertContains('DB UniqueSearchName Batch7', $names);
    }

    // ── countPlayers ────────────────────────────────────────────

    public function testCountPlayersReturnsPositiveCount(): void
    {
        $count = $this->repo->countPlayers();

        self::assertGreaterThan(0, $count);
    }

    public function testCountPlayersWithPositionFilter(): void
    {
        $allCount = $this->repo->countPlayers();
        $pgCount = $this->repo->countPlayers(['position' => 'PG']);

        self::assertGreaterThan(0, $pgCount);
        self::assertLessThanOrEqual($allCount, $pgCount);
    }

    // ── getPlayerByUuid ─────────────────────────────────────────

    public function testGetPlayerByUuidReturnsPlayer(): void
    {
        $this->insertTestPlayer(200000082, 'DB Test UUID Player', [
            'uuid' => 'batch7-test-uuid-00000082',
        ]);

        $player = $this->repo->getPlayerByUuid('batch7-test-uuid-00000082');

        self::assertNotNull($player);
        self::assertSame('DB Test UUID Player', $player['name']);
        self::assertSame('batch7-test-uuid-00000082', $player['player_uuid']);
    }

    public function testGetPlayerByUuidReturnsNullForUnknownUuid(): void
    {
        $result = $this->repo->getPlayerByUuid('nonexistent-uuid-value');

        self::assertNull($result);
    }
}
