<?php

declare(strict_types=1);

namespace Tests\SeasonLeaders;

use PHPUnit\Framework\TestCase;
use SeasonLeaders\SeasonLeadersRepository;

/**
 * SeasonLeadersRepositoryTest - Tests for SeasonLeadersRepository database operations
 */
class SeasonLeadersRepositoryTest extends TestCase
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
        $repository = new SeasonLeadersRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(SeasonLeadersRepository::class, $repository);
    }

    public function testRepositoryImplementsCorrectInterface(): void
    {
        $repository = new SeasonLeadersRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \SeasonLeaders\Contracts\SeasonLeadersRepositoryInterface::class,
            $repository
        );
    }

    public function testRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new SeasonLeadersRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(\BaseMysqliRepository::class, $repository);
    }



    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new SeasonLeadersRepository($this->mockMysqliDb);
        $repo2 = new SeasonLeadersRepository($this->mockMysqliDb);
        
        $this->assertNotSame($repo1, $repo2);
    }
}
