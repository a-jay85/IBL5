<?php

declare(strict_types=1);

namespace Tests\AwardHistory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use AwardHistory\AwardHistoryRepository;

/**
 * Tests for AwardHistoryRepository
 * 
 * Verifies database operations for player awards search using prepared statements.
 */
final class AwardHistoryRepositoryTest extends TestCase
{
    private \MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
    }

    // ==================== searchAwards Tests ====================

    public function testSearchAwardsReturnsResultsArray(): void
    {
        $this->mockDb->setMockData([
            ['year' => 2025, 'Award' => 'MVP', 'name' => 'Johnson', 'table_ID' => 1],
            ['year' => 2024, 'Award' => 'MVP', 'name' => 'Smith', 'table_ID' => 2],
        ]);

        $repository = new AwardHistoryRepository($this->mockDb);
        
        $result = $repository->searchAwards([
            'name' => null,
            'award' => null,
            'year' => null,
            'sortby' => 3,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(2, $result['count']);
    }

    public function testSearchAwardsWithNameFilter(): void
    {
        $this->mockDb->setMockData([
            ['year' => 2025, 'Award' => 'MVP', 'name' => 'Johnson', 'table_ID' => 1],
        ]);

        $repository = new AwardHistoryRepository($this->mockDb);
        
        $result = $repository->searchAwards([
            'name' => 'Johnson',
            'award' => null,
            'year' => null,
            'sortby' => 1,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['count']);
    }

    public function testSearchAwardsWithAwardFilter(): void
    {
        $this->mockDb->setMockData([
            ['year' => 2025, 'Award' => 'MVP', 'name' => 'Johnson', 'table_ID' => 1],
            ['year' => 2024, 'Award' => 'MVP', 'name' => 'Smith', 'table_ID' => 2],
        ]);

        $repository = new AwardHistoryRepository($this->mockDb);
        
        $result = $repository->searchAwards([
            'name' => null,
            'award' => 'MVP',
            'year' => null,
            'sortby' => 2,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(2, $result['count']);
    }

    public function testSearchAwardsWithYearFilter(): void
    {
        $this->mockDb->setMockData([
            ['year' => 2025, 'Award' => 'MVP', 'name' => 'Johnson', 'table_ID' => 1],
        ]);

        $repository = new AwardHistoryRepository($this->mockDb);
        
        $result = $repository->searchAwards([
            'name' => null,
            'award' => null,
            'year' => 2025,
            'sortby' => 3,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['count']);
    }

    public function testSearchAwardsWithMultipleFilters(): void
    {
        $this->mockDb->setMockData([
            ['year' => 2025, 'Award' => 'MVP', 'name' => 'Johnson', 'table_ID' => 1],
        ]);

        $repository = new AwardHistoryRepository($this->mockDb);
        
        $result = $repository->searchAwards([
            'name' => 'Johnson',
            'award' => 'MVP',
            'year' => 2025,
            'sortby' => 1,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
    }

    public function testSearchAwardsWithNoResults(): void
    {
        $this->mockDb->setMockData([]);

        $repository = new AwardHistoryRepository($this->mockDb);
        
        $result = $repository->searchAwards([
            'name' => 'NonExistent',
            'award' => null,
            'year' => null,
            'sortby' => 3,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['results']);
    }

    #[DataProvider('validSortByProvider')]
    public function testSearchAwardsSortColumnWhitelist(int $sortBy): void
    {
        $this->mockDb->setMockData([
            ['year' => 2025, 'Award' => 'MVP', 'name' => 'Johnson', 'table_ID' => 1],
        ]);

        $repository = new AwardHistoryRepository($this->mockDb);

        // Should not throw exception for valid sortby value
        $result = $repository->searchAwards([
            'name' => null,
            'award' => null,
            'year' => null,
            'sortby' => $sortBy,
        ]);

        $this->assertIsArray($result);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function validSortByProvider(): array
    {
        return [
            'sort by name' => [1],
            'sort by award' => [2],
            'sort by year' => [3],
        ];
    }
}
