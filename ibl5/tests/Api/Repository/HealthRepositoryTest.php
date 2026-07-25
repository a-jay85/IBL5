<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Repository\HealthRepository;
use Tests\WideUnit\WideUnitTestCase;

class HealthRepositoryTest extends WideUnitTestCase
{
    public function testIsReachableReturnsTrueWhenDbAnswers(): void
    {
        $this->mockDb->setMockData([['ok' => 1]]);

        $repository = new HealthRepository($this->mockDb);

        self::assertTrue($repository->isReachable());
    }

    public function testIsReachableReturnsFalseWhenFetchOneThrows(): void
    {
        $repository = new class ($this->mockDb) extends HealthRepository {
            protected function fetchOne(string $query, string $types = '', mixed ...$params): ?array
            {
                throw new \RuntimeException('Simulated connection failure');
            }
        };

        self::assertFalse($repository->isReachable());
    }

    public function testIsReachableQueriesSelectOne(): void
    {
        $this->mockDb->setMockData([['ok' => 1]]);

        $repository = new HealthRepository($this->mockDb);
        $repository->isReachable();

        $this->assertQueryExecuted('SELECT 1');
    }
}
