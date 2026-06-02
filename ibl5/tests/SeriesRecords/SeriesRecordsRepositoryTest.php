<?php

declare(strict_types=1);

namespace Tests\SeriesRecords;

use PHPUnit\Framework\TestCase;
use SeriesRecords\SeriesRecordsRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * SeriesRecordsRepositoryTest - Tests for SeriesRecordsRepository database operations
 */
class SeriesRecordsRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new SeriesRecordsRepository($this->mockDb);
        $repo2 = new SeriesRecordsRepository($this->mockDb);

        $this->assertNotSame($repo1, $repo2);
    }
}
