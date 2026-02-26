<?php

declare(strict_types=1);

namespace Tests\Migration;

use Migration\MigrationRepository;
use PHPUnit\Framework\TestCase;

final class MigrationRepositoryTest extends TestCase
{
    private \MockDatabase $db;
    private MigrationRepository $repository;

    protected function setUp(): void
    {
        $this->db = new \MockDatabase();
        $this->repository = new MigrationRepository($this->db);
    }

    public function testGetRanMigrationsReturnsEmptyArrayWhenNoRows(): void
    {
        $this->db->setMockData([]);

        $result = $this->repository->getRanMigrations();

        $this->assertSame([], $result);
    }

    public function testGetRanMigrationsReturnsFilenames(): void
    {
        $this->db->setMockData([
            ['migration' => '001_first.sql'],
            ['migration' => '002_second.sql'],
            ['migration' => 'add_column.sql'],
        ]);

        $result = $this->repository->getRanMigrations();

        $this->assertSame([
            '001_first.sql',
            '002_second.sql',
            'add_column.sql',
        ], $result);
    }

    public function testRecordMigrationExecutesInsert(): void
    {
        $this->repository->recordMigration('003_new.sql', 5);

        $queries = $this->db->getExecutedQueries();
        $this->assertNotEmpty($queries);

        $lastQuery = end($queries);
        $this->assertIsString($lastQuery);
        $this->assertStringContainsString('INSERT INTO migrations', $lastQuery);
        $this->assertStringContainsString('003_new.sql', $lastQuery);
    }

    public function testGetNextBatchNumberReturnsOneWhenEmpty(): void
    {
        $this->db->setMockData([['next_batch' => 1]]);

        $result = $this->repository->getNextBatchNumber();

        $this->assertSame(1, $result);
    }

    public function testGetNextBatchNumberReturnsIncrementedValue(): void
    {
        $this->db->setMockData([['next_batch' => 4]]);

        $result = $this->repository->getNextBatchNumber();

        $this->assertSame(4, $result);
    }

    public function testTruncateExecutesQuery(): void
    {
        $this->repository->truncate();

        $queries = $this->db->getExecutedQueries();
        $this->assertNotEmpty($queries);

        $lastQuery = end($queries);
        $this->assertIsString($lastQuery);
        $this->assertStringContainsString('TRUNCATE TABLE migrations', $lastQuery);
    }
}
