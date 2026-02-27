<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\Contracts\TeamTableServiceInterface;
use Team\TeamTableService;

/**
 * Tests for TeamTableService
 *
 * Validates table rendering, starters extraction, and dropdown group logic
 */
class TeamTableServiceTest extends TestCase
{
    private \MockDatabase $mockDb;
    private TeamTableService $service;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $repository = new \Team\TeamRepository($this->mockDb);
        $this->service = new TeamTableService($this->mockDb, $repository);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(TeamTableServiceInterface::class, $this->service);
    }

    // ============================================
    // extractStartersData() TESTS
    // ============================================

    public function testExtractStartersDataReturnsCorrectStructure(): void
    {
        $roster = [
            ['pid' => 1, 'name' => 'John Doe', 'PGDepth' => 1, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 0],
            ['pid' => 2, 'name' => 'Jane Smith', 'PGDepth' => 0, 'SGDepth' => 1, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 0],
            ['pid' => 3, 'name' => 'Bob Johnson', 'PGDepth' => 0, 'SGDepth' => 0, 'SFDepth' => 1, 'PFDepth' => 0, 'CDepth' => 0],
            ['pid' => 4, 'name' => 'Mike Williams', 'PGDepth' => 0, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 1, 'CDepth' => 0],
            ['pid' => 5, 'name' => 'Tom Brown', 'PGDepth' => 0, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 1],
        ];

        $starters = $this->service->extractStartersData($roster);

        $this->assertIsArray($starters);
        $this->assertArrayHasKey('PG', $starters);
        $this->assertArrayHasKey('SG', $starters);
        $this->assertArrayHasKey('SF', $starters);
        $this->assertArrayHasKey('PF', $starters);
        $this->assertArrayHasKey('C', $starters);

        $this->assertSame('John Doe', $starters['PG']['name']);
        $this->assertSame(1, $starters['PG']['pid']);
        $this->assertSame('Jane Smith', $starters['SG']['name']);
        $this->assertSame(2, $starters['SG']['pid']);
        $this->assertSame('Bob Johnson', $starters['SF']['name']);
        $this->assertSame(3, $starters['SF']['pid']);
        $this->assertSame('Mike Williams', $starters['PF']['name']);
        $this->assertSame(4, $starters['PF']['pid']);
        $this->assertSame('Tom Brown', $starters['C']['name']);
        $this->assertSame(5, $starters['C']['pid']);
    }

    public function testExtractStartersDataHandlesPartialData(): void
    {
        $roster = [
            ['pid' => 1, 'name' => 'John Doe', 'PGDepth' => 1, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 0],
            ['pid' => 3, 'name' => 'Bob Johnson', 'PGDepth' => 0, 'SGDepth' => 0, 'SFDepth' => 1, 'PFDepth' => 0, 'CDepth' => 0],
        ];

        $starters = $this->service->extractStartersData($roster);

        $this->assertSame('John Doe', $starters['PG']['name']);
        $this->assertSame(1, $starters['PG']['pid']);
        $this->assertSame('Bob Johnson', $starters['SF']['name']);
        $this->assertSame(3, $starters['SF']['pid']);

        $this->assertNull($starters['SG']['name']);
        $this->assertNull($starters['SG']['pid']);
        $this->assertNull($starters['PF']['name']);
        $this->assertNull($starters['PF']['pid']);
        $this->assertNull($starters['C']['name']);
        $this->assertNull($starters['C']['pid']);
    }

    public function testExtractStartersDataIgnoresBackups(): void
    {
        $roster = [
            ['pid' => 1, 'name' => 'Starter PG', 'PGDepth' => 1, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 0],
            ['pid' => 2, 'name' => 'Backup PG', 'PGDepth' => 2, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 0],
        ];

        $starters = $this->service->extractStartersData($roster);

        $this->assertSame('Starter PG', $starters['PG']['name']);
        $this->assertSame(1, $starters['PG']['pid']);
    }

    public function testExtractStartersDataHandlesEmptyRoster(): void
    {
        $starters = $this->service->extractStartersData([]);

        foreach (['PG', 'SG', 'SF', 'PF', 'C'] as $position) {
            $this->assertNull($starters[$position]['name']);
            $this->assertNull($starters[$position]['pid']);
        }
    }

    public function testExtractStartersDataUsesStrictComparison(): void
    {
        // Depth values come from the database as strings; verify '1' (int cast) works
        $roster = [
            ['pid' => 10, 'name' => 'String Depth', 'PGDepth' => '1', 'SGDepth' => '0', 'SFDepth' => '0', 'PFDepth' => '0', 'CDepth' => '0'],
        ];

        $starters = $this->service->extractStartersData($roster);

        $this->assertSame('String Depth', $starters['PG']['name']);
    }
}
