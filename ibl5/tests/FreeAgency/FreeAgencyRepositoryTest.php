<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyRepository;

/**
 * FreeAgencyRepositoryTest - Tests for FreeAgencyRepository database operations
 *
 * Tests:
 * - Repository instantiation
 * - Interface compliance
 * - Query execution via mock
 */
class FreeAgencyRepositoryTest extends TestCase
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
                $result = $this->mockDb->sql_query($query);
                if ($result instanceof \MockDatabaseResult) {
                    return false;
                }
                return (bool) $result;
            }

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };
        
        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testRepositoryCanBeInstantiated(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(FreeAgencyRepository::class, $repository);
    }

    public function testRepositoryImplementsCorrectInterface(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \FreeAgency\Contracts\FreeAgencyRepositoryInterface::class,
            $repository
        );
    }

    public function testRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \BaseMysqliRepository::class,
            $repository
        );
    }

    // ============================================
    // GET EXISTING OFFER TESTS
    // ============================================

    public function testGetExistingOfferReturnsNullWhenNoOffer(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getExistingOffer(1, 1);

        $this->assertNull($result);
    }

    public function testGetExistingOfferReturnsOfferData(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['offer1' => 500, 'offer2' => 525, 'offer3' => 550, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0]
        ]);

        $result = $repository->getExistingOffer(1, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('offer1', $result);
        $this->assertEquals(500, $result['offer1']);
    }

    // ============================================
    // DELETE OFFER TESTS
    // ============================================

    public function testDeleteOfferExecutesQuery(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        
        // Track that the delete query was executed
        $result = $repository->deleteOffer(1, 1);

        // Should return affected rows (0 or more)
        $this->assertIsInt($result);
    }

    // ============================================
    // SAVE OFFER TESTS
    // ============================================

    public function testSaveOfferReturnsTrueOnSuccessfulInsert(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);

        $offerData = [
            'tid' => 1,
            'pid' => 100,
            'playerName' => 'Test Player',
            'teamName' => 'Miami',
            'offer1' => 500,
            'offer2' => 525,
            'offer3' => 550,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'modifier' => 1,
            'random' => 5,
            'perceivedValue' => 1575.0,
            'mle' => 0,
            'lle' => 0,
            'offerType' => 1,
        ];

        $result = $repository->saveOffer($offerData);

        $this->assertTrue($result);
    }

    // ============================================
    // GET ALL PLAYERS EXCLUDING TEAM TESTS
    // ============================================

    public function testGetAllPlayersExcludingTeamReturnsPlayerRows(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['pid' => 1, 'name' => 'Player One', 'teamname' => 'Chicago', 'retired' => 0],
            ['pid' => 2, 'name' => 'Player Two', 'teamname' => 'Boston', 'retired' => 0],
        ]);

        $result = $repository->getAllPlayersExcludingTeam(5);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Player One', $result[0]['name']);
    }

    public function testGetAllPlayersExcludingTeamReturnsEmptyArrayWhenNone(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getAllPlayersExcludingTeam(5);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================
    // IS PLAYER ALREADY SIGNED TESTS
    // ============================================

    public function testIsPlayerAlreadySignedReturnsTrueWhenCyZeroAndCy1NonZero(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        $this->mockDb->onQuery('SELECT cy, cy1 FROM ibl_plr', [['cy' => 0, 'cy1' => 500]]);

        $result = $repository->isPlayerAlreadySigned(100);

        $this->assertTrue($result);
    }

    public function testIsPlayerAlreadySignedReturnsFalseWhenCy1IsZero(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        $this->mockDb->onQuery('SELECT cy, cy1 FROM ibl_plr', [['cy' => 0, 'cy1' => 0]]);

        $result = $repository->isPlayerAlreadySigned(100);

        $this->assertFalse($result);
    }

    public function testIsPlayerAlreadySignedReturnsFalseWhenPlayerNotFound(): void
    {
        $repository = new FreeAgencyRepository($this->mockMysqliDb);
        $this->mockDb->onQuery('SELECT cy, cy1 FROM ibl_plr', []);

        $result = $repository->isPlayerAlreadySigned(999);

        $this->assertFalse($result);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new FreeAgencyRepository($this->mockMysqliDb);
        $repo2 = new FreeAgencyRepository($this->mockMysqliDb);

        $this->assertInstanceOf(FreeAgencyRepository::class, $repo1);
        $this->assertInstanceOf(FreeAgencyRepository::class, $repo2);
        $this->assertNotSame($repo1, $repo2);
    }
}
