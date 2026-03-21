<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Api\Pagination\Paginator;
use Api\Repository\ApiLeadersRepository;

/**
 * Tests ApiLeadersRepository against real MariaDB —
 * statistical leaders from ibl_hist + ibl_plr + ibl_team_info
 * with category-based sorting and season filtering.
 */
class ApiLeadersRepositoryTest extends DatabaseTestCase
{
    private ApiLeadersRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ApiLeadersRepository($this->db);
    }

    // ── getLeaders ──────────────────────────────────────────────

    public function testGetLeadersReturnsResults(): void
    {
        $this->insertTestPlayer(200000090, 'DB Test Leader Player', [
            'uuid' => 'batch7-leader-00000090',
        ]);
        $this->insertHistRow(200000090, 'DB Test Leader Player', 2025, [
            'teamid' => 1,
            'games' => 50,
            'pts' => 1000,
            'fgm' => 300,
            'fga' => 600,
            'ftm' => 100,
            'fta' => 120,
            'tgm' => 50,
            'tga' => 130,
        ]);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '10'],
            'name',
            ['name'],
        );

        $leaders = $this->repo->getLeaders($paginator);

        self::assertNotEmpty($leaders);
    }

    public function testGetLeadersRowHasExpectedStructure(): void
    {
        $this->insertTestPlayer(200000091, 'DB Test Leader Struct', [
            'uuid' => 'batch7-leader-struct-00000091',
        ]);
        $this->insertHistRow(200000091, 'DB Test Leader Struct', 2025, [
            'teamid' => 1,
            'games' => 40,
        ]);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '5'],
            'name',
            ['name'],
        );

        $leaders = $this->repo->getLeaders($paginator);

        self::assertNotEmpty($leaders);
        $row = $leaders[0];

        self::assertArrayHasKey('player_uuid', $row);
        self::assertArrayHasKey('pid', $row);
        self::assertArrayHasKey('name', $row);
        self::assertArrayHasKey('team_uuid', $row);
        self::assertArrayHasKey('team_city', $row);
        self::assertArrayHasKey('team_name', $row);
        self::assertArrayHasKey('games', $row);
        self::assertArrayHasKey('pts', $row);
    }

    public function testGetLeadersFilterBySeason(): void
    {
        $this->insertTestPlayer(200000092, 'DB Test Leader Season', [
            'uuid' => 'batch7-leader-season-00000092',
        ]);
        $this->insertHistRow(200000092, 'DB Test Leader Season', 2020, [
            'teamid' => 1,
            'games' => 30,
        ]);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '100'],
            'name',
            ['name'],
        );

        $leaders = $this->repo->getLeaders($paginator, ['season' => '2020']);

        // Every result should be from year 2020
        foreach ($leaders as $row) {
            self::assertSame(2020, $row['year']);
        }
    }

    public function testGetLeadersFilterByMinGames(): void
    {
        $this->insertTestPlayer(200000093, 'DB Test Low Games', [
            'uuid' => 'batch7-leader-lowgames-00000093',
        ]);
        $this->insertHistRow(200000093, 'DB Test Low Games', 2025, [
            'teamid' => 1,
            'games' => 2,
        ]);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '100'],
            'name',
            ['name'],
        );

        $leaders = $this->repo->getLeaders($paginator, ['min_games' => '40']);

        $pids = array_column($leaders, 'pid');
        self::assertNotContains(200000093, $pids);
    }

    public function testGetLeadersWithCategorySort(): void
    {
        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '10'],
            'name',
            ['name'],
        );

        // Should not throw for any valid category
        $categories = ['ppg', 'rpg', 'apg', 'spg', 'bpg', 'fgp', 'ftp', 'tgp', 'qa'];
        foreach ($categories as $cat) {
            $leaders = $this->repo->getLeaders($paginator, ['category' => $cat]);
            self::assertIsArray($leaders, "Category '{$cat}' should return an array");
        }
    }

    // ── countLeaders ────────────────────────────────────────────

    public function testCountLeadersReturnsPositiveCount(): void
    {
        $count = $this->repo->countLeaders();

        self::assertGreaterThan(0, $count);
    }

    public function testCountLeadersWithSeasonFilter(): void
    {
        $allCount = $this->repo->countLeaders();
        $seasonCount = $this->repo->countLeaders(['season' => '2025']);

        self::assertLessThanOrEqual($allCount, $seasonCount);
    }

    public function testGetLeadersReturnsEmptyForNoMatchingSeason(): void
    {
        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '25'],
            'name',
            ['name'],
        );
        $result = $this->repo->getLeaders($paginator, ['season' => '8888']);

        self::assertSame([], $result);
    }

    public function testCountLeadersReturnsZeroForNoMatchingSeason(): void
    {
        $count = $this->repo->countLeaders(['season' => '8888']);

        self::assertSame(0, $count);
    }

    // ── getAvailableSeasons ─────────────────────────────────────

    public function testGetAvailableSeasonsReturnsYears(): void
    {
        $this->insertTestPlayer(200000094, 'DB Test Seasons Player', [
            'uuid' => 'batch7-seasons-00000094',
        ]);
        $this->insertHistRow(200000094, 'DB Test Seasons Player', 2025);

        $seasons = $this->repo->getAvailableSeasons();

        self::assertNotEmpty($seasons);
        self::assertContains(2025, $seasons);

        // Should be ordered DESC
        for ($i = 1; $i < count($seasons); $i++) {
            self::assertGreaterThanOrEqual($seasons[$i], $seasons[$i - 1]);
        }
    }

    public function testGetAvailableSeasonsIncludesInsertedYear(): void
    {
        $this->insertTestPlayer(200100040, 'API Seasons 8888', [
            'uuid' => 'batch10-seasons-8888',
        ]);
        $this->insertHistRow(200100040, 'API Seasons 8888', 8888);

        $seasons = $this->repo->getAvailableSeasons();

        self::assertContains(8888, $seasons);
    }
}
