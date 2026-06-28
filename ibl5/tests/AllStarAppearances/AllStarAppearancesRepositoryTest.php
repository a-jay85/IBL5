<?php

declare(strict_types=1);

namespace Tests\AllStarAppearances;

use AllStarAppearances\AllStarAppearancesRepository;
use Tests\WideUnit\WideUnitTestCase;

class AllStarAppearancesRepositoryTest extends WideUnitTestCase
{
    private function repo(): AllStarAppearancesRepository
    {
        $db = $this->mockDb;
        self::assertNotNull($db);
        return new AllStarAppearancesRepository($db);
    }

    public function testGetAllStarAppearancesReturnsMappedRows(): void
    {
        $this->mockDb->setMockData([
            ['name' => 'Star Player', 'pid' => 10, 'appearances' => 5],
            ['name' => 'Sub Player', 'pid' => 11, 'appearances' => 1],
        ]);

        $result = $this->repo()->getAllStarAppearances();

        $this->assertSame([
            ['name' => 'Star Player', 'pid' => 10, 'appearances' => 5],
            ['name' => 'Sub Player', 'pid' => 11, 'appearances' => 1],
        ], $result);
        $this->assertQueryExecuted('ibl_awards');
    }

    public function testGetAllStarAppearancesReturnsEmptyArrayWhenNoAwards(): void
    {
        $this->mockDb->setMockData([]);

        $this->assertSame([], $this->repo()->getAllStarAppearances());
    }
}
