<?php

declare(strict_types=1);

namespace Tests\OneOnOne;

use PHPUnit\Framework\TestCase;
use OneOnOne\OneOnOneRepository;

/**
 * OneOnOneRepositoryTest - Tests for OneOnOneRepository database operations
 */
class OneOnOneRepositoryTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;
        
        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                return false;
            }

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };
        
        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    public function testRepositoryCanBeInstantiated(): void
    {
        $repository = new OneOnOneRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(OneOnOneRepository::class, $repository);
    }

    public function testRepositoryImplementsCorrectInterface(): void
    {
        $repository = new OneOnOneRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \OneOnOne\Contracts\OneOnOneRepositoryInterface::class,
            $repository
        );
    }

    public function testRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new OneOnOneRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(\BaseMysqliRepository::class, $repository);
    }



    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new OneOnOneRepository($this->mockMysqliDb);
        $repo2 = new OneOnOneRepository($this->mockMysqliDb);
        
        $this->assertNotSame($repo1, $repo2);
    }
}
