<?php

declare(strict_types=1);

namespace Tests\SiteStatistics;

use PHPUnit\Framework\TestCase;
use SiteStatistics\StatisticsController;

class StatisticsControllerTest extends TestCase
{
    private \mysqli $mockDb;
    private StatisticsController $controller;

    protected function setUp(): void
    {
        global $prefix, $user_prefix, $startdate;
        $prefix = 'test';
        $user_prefix = 'nuke';
        $startdate = '2024-01-01';

        $this->mockDb = $this->createMockDatabase();
        $this->controller = new StatisticsController($this->mockDb, 'SiteStatistics', 'TestTheme');
    }

    private function createMockDatabase(): \mysqli
    {
        return new class extends \mysqli {
            private array $queryResults = [];
            private int $queryIndex = 0;

            public function __construct()
            {
                // Don't call parent::__construct() to avoid real DB connection
            }

            public function setQueryResults(array $results): void
            {
                $this->queryResults = $results;
                $this->queryIndex = 0;
            }

            public function sql_query(string $query): static
            {
                return $this;
            }

            public function sql_fetchrow(mixed $result): array|false
            {
                if ($this->queryIndex < count($this->queryResults)) {
                    return $this->queryResults[$this->queryIndex++];
                }
                return false;
            }

            public function sql_numrows(mixed $result): int
            {
                if (isset($this->queryResults[0])) {
                    return count($this->queryResults);
                }
                return 0;
            }
        };
    }

    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(StatisticsController::class, $this->controller);
    }

}
