<?php

declare(strict_types=1);

namespace Tests\AllStarAppearances;

use AllStarAppearances\AllStarAppearancesRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

class AllStarAppearancesRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testGetAllStarAppearancesReturnsMappedRows(): void
    {
        $this->mockDb->setMockData([
            ['name' => 'Star Player', 'pid' => 10, 'appearances' => 5],
            ['name' => 'Sub Player', 'pid' => 11, 'appearances' => 1],
        ]);
        $repo = new AllStarAppearancesRepository($this->mockDb);

        $result = $repo->getAllStarAppearances();

        $this->assertSame([
            ['name' => 'Star Player', 'pid' => 10, 'appearances' => 5],
            ['name' => 'Sub Player', 'pid' => 11, 'appearances' => 1],
        ], $result);
        $this->assertQueryExecuted('ibl_awards');
    }

    public function testGetAllStarAppearancesReturnsEmptyArrayWhenNoAwards(): void
    {
        $this->mockDb->setMockData([]);
        $repo = new AllStarAppearancesRepository($this->mockDb);

        $this->assertSame([], $repo->getAllStarAppearances());
    }

    private function assertQueryExecuted(string $substring): void
    {
        $queries = $this->mockDb->getExecutedQueries();
        $found = false;
        foreach ($queries as $query) {
            if (str_contains($query, $substring)) {
                $found = true;
                break;
            }
        }
        self::assertTrue(
            $found,
            "Expected a query containing '{$substring}' but none was found. Queries: " . implode("\n", $queries)
        );
    }
}
