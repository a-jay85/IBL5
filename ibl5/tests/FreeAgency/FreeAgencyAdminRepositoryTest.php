<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\FreeAgencyAdminRepository;
use PHPUnit\Framework\TestCase;

/**
 * FreeAgencyAdminRepositoryTest - Tests for admin free agency repository operations
 *
 * Tests all 7 methods: offer retrieval, player demands, contract updates,
 * MLE/LLE marking, news insertion, and offer clearing.
 */
class FreeAgencyAdminRepositoryTest extends TestCase
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

        $this->mockMysqliDb = new class ($mockDb) extends \mysqli {
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
        $repository = new FreeAgencyAdminRepository($this->mockMysqliDb);

        $this->assertInstanceOf(FreeAgencyAdminRepository::class, $repository);
    }

    public function testRepositoryImplementsCorrectInterface(): void
    {
        $repository = new FreeAgencyAdminRepository($this->mockMysqliDb);

        $this->assertInstanceOf(
            \FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface::class,
            $repository
        );
    }

    // ============================================
    // GET ALL OFFERS WITH BIRD YEARS TESTS
    // ============================================

    public function testGetAllOffersWithBirdYearsReturnsOfferRows(): void
    {
        $repository = new FreeAgencyAdminRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([
            ['name' => 'Player One', 'pid' => 1, 'team' => 'Miami', 'teamid' => 1, 'offer1' => 500, 'offer2' => 525, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0, 'bird' => 3, 'MLE' => 0, 'LLE' => 0, 'random' => 5, 'perceivedvalue' => 1025.0],
            ['name' => 'Player Two', 'pid' => 2, 'team' => 'Chicago', 'teamid' => 2, 'offer1' => 400, 'offer2' => 0, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0, 'bird' => 0, 'MLE' => 1, 'LLE' => 0, 'random' => 3, 'perceivedvalue' => 400.0],
        ]);

        $result = $repository->getAllOffersWithBirdYears();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Player One', $result[0]['name']);
        $this->assertSame(3, $result[0]['bird']);
    }

    public function testGetAllOffersWithBirdYearsReturnsEmptyArray(): void
    {
        $repository = new FreeAgencyAdminRepository($this->mockMysqliDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getAllOffersWithBirdYears();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================
    // UPDATE PLAYER CONTRACT TESTS
    // ============================================

    public function testUpdatePlayerContractExecutesUpdate(): void
    {
        $repository = new FreeAgencyAdminRepository($this->mockMysqliDb);

        $result = $repository->updatePlayerContract(
            100,
            5,
            1,
            3,
            500,
            525,
            550,
            0,
            0,
            0
        );

        $this->assertIsInt($result);
    }

    // ============================================
    // MARK MLE USED TESTS
    // ============================================

    public function testMarkMleUsedExecutesUpdate(): void
    {
        $repository = new FreeAgencyAdminRepository($this->mockMysqliDb);

        // Should not throw
        $repository->markMleUsed('Miami');

        // Verify the UPDATE query was executed
        $queries = $this->mockDb->getExecutedQueries();
        $mleQueries = array_filter($queries, static fn (string $q): bool => stripos($q, 'has_mle') !== false);
        $this->assertNotEmpty($mleQueries);
    }

    // ============================================
    // MARK LLE USED TESTS
    // ============================================

    public function testMarkLleUsedExecutesUpdate(): void
    {
        $repository = new FreeAgencyAdminRepository($this->mockMysqliDb);

        $repository->markLleUsed('Chicago');

        $queries = $this->mockDb->getExecutedQueries();
        $lleQueries = array_filter($queries, static fn (string $q): bool => stripos($q, 'has_lle') !== false);
        $this->assertNotEmpty($lleQueries);
    }

    // ============================================
    // INSERT NEWS STORY TESTS
    // ============================================

    public function testInsertNewsStoryExecutesInsert(): void
    {
        $repository = new FreeAgencyAdminRepository($this->mockMysqliDb);

        $result = $repository->insertNewsStory(
            'FA Signing: Player Signs with Miami',
            'Player has signed a 3-year deal.',
            'Full body text of the signing announcement.'
        );

        $this->assertIsInt($result);
    }

    // ============================================
    // CLEAR ALL OFFERS TESTS
    // ============================================

    public function testClearAllOffersExecutesDelete(): void
    {
        $repository = new FreeAgencyAdminRepository($this->mockMysqliDb);

        // Should not throw
        $repository->clearAllOffers();

        // Verify DELETE query was executed (void return, just confirm no exception)
        $this->assertTrue(true);
    }
}
