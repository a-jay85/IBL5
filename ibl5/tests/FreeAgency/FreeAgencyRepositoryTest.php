<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyRepository;
use Tests\WideUnit\Mocks\MockDatabase;

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

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testRepositoryCanBeInstantiated(): void
    {
        $repository = new FreeAgencyRepository($this->mockDb);
        
        $this->assertInstanceOf(FreeAgencyRepository::class, $repository);
    }

    public function testRepositoryImplementsCorrectInterface(): void
    {
        $repository = new FreeAgencyRepository($this->mockDb);
        
        $this->assertInstanceOf(
            \FreeAgency\Contracts\FreeAgencyRepositoryInterface::class,
            $repository
        );
    }

    public function testRepositoryExtendsBaseMysqliRepository(): void
    {
        $repository = new FreeAgencyRepository($this->mockDb);
        
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
        $repository = new FreeAgencyRepository($this->mockDb);
        $this->mockDb->setMockData([]);

        $result = $repository->getExistingOffer(1, 1);

        $this->assertNull($result);
    }

    public function testGetExistingOfferReturnsOfferData(): void
    {
        $repository = new FreeAgencyRepository($this->mockDb);
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
        $repository = new FreeAgencyRepository($this->mockDb);
        
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
        $repository = new FreeAgencyRepository($this->mockDb);

        $offerData = [
            'teamid' => 1,
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
        $repository = new FreeAgencyRepository($this->mockDb);
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
        $repository = new FreeAgencyRepository($this->mockDb);
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
        $repository = new FreeAgencyRepository($this->mockDb);
        $this->mockDb->onQuery('SELECT cy, salary_yr1 FROM ibl_plr', [['cy' => 0, 'salary_yr1' => 500]]);

        $result = $repository->isPlayerAlreadySigned(100);

        $this->assertTrue($result);
    }

    public function testIsPlayerAlreadySignedReturnsFalseWhenCy1IsZero(): void
    {
        $repository = new FreeAgencyRepository($this->mockDb);
        $this->mockDb->onQuery('SELECT cy, salary_yr1 FROM ibl_plr', [['cy' => 0, 'salary_yr1' => 0]]);

        $result = $repository->isPlayerAlreadySigned(100);

        $this->assertFalse($result);
    }

    public function testIsPlayerAlreadySignedReturnsFalseWhenPlayerNotFound(): void
    {
        $repository = new FreeAgencyRepository($this->mockDb);
        $this->mockDb->onQuery('SELECT cy, salary_yr1 FROM ibl_plr', []);

        $result = $repository->isPlayerAlreadySigned(999);

        $this->assertFalse($result);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleRepositoriesCanBeInstantiated(): void
    {
        $repo1 = new FreeAgencyRepository($this->mockDb);
        $repo2 = new FreeAgencyRepository($this->mockDb);

        $this->assertInstanceOf(FreeAgencyRepository::class, $repo1);
        $this->assertInstanceOf(FreeAgencyRepository::class, $repo2);
        $this->assertNotSame($repo1, $repo2);
    }
}
