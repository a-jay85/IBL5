<?php

declare(strict_types=1);

namespace Tests\OneOnOneGame;

use PHPUnit\Framework\TestCase;
use OneOnOneGame\OneOnOneGameRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * OneOnOneGameRepositoryTest - Tests for OneOnOneGameRepository database operations
 */
class OneOnOneGameRepositoryTest extends TestCase
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
        $repo1 = new OneOnOneGameRepository($this->mockDb);
        $repo2 = new OneOnOneGameRepository($this->mockDb);

        $this->assertNotSame($repo1, $repo2);
    }
}
