<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\WaiversRepository;
use Tests\WideUnit\Mocks\MockDatabase;

class WaiversRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private WaiversRepository $repository;

    protected function setUp(): void
    {
        // Create MockDatabase that duck-types mysqli for testing
        $this->mockDb = new MockDatabase();
        $this->repository = new WaiversRepository($this->mockDb);
    }

    // Tests for getUserByUsername, getTeamByName, getTeamTotalSalary, and getPlayerByID
    // have been moved to CommonRepositoryTest as these methods now delegate to CommonRepository

    public function testDropPlayerToWaiversExecutesCorrectQuery(): void
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->repository->dropPlayerToWaivers(123, 1234567890);
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
        $this->assertStringContainsString('ordinal', $queries[0]);
        $this->assertStringContainsString('1000', $queries[0]);
        $this->assertStringContainsString('droptime', $queries[0]);
        $this->assertStringContainsString('1234567890', $queries[0]);
        $this->assertStringContainsString('WHERE pid = 123', $queries[0]);
    }
    
    public function testSignPlayerFromWaiversWithNewContract(): void
    {
        $this->mockDb->setReturnTrue(true);
        
        $team = [
            'team_name' => 'Boston Celtics',
            'teamid' => 2
        ];

        $contractData = [
            'hasExistingContract' => false,
            'salary' => 103
        ];
        
        $result = $this->repository->signPlayerFromWaivers(
            123,
            $team,
            $contractData
        );
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
        $this->assertStringContainsString('ordinal', $queries[0]);
        $this->assertStringContainsString('800', $queries[0]);
        $this->assertStringContainsString('salary_yr1', $queries[0]);
        $this->assertStringContainsString('103', $queries[0]);
        $this->assertStringContainsString('cy = 0', $queries[0]);
        $this->assertStringContainsString('cyt = 1', $queries[0]);
        $this->assertStringContainsString('droptime', $queries[0]);
        $this->assertStringContainsString('= 0', $queries[0]);
    }
    
    public function testSignPlayerFromWaiversWithExistingContract(): void
    {
        $this->mockDb->setReturnTrue(true);
        
        $team = [
            'team_name' => 'Boston Celtics',
            'teamid' => 2
        ];

        $contractData = [
            'hasExistingContract' => true,
            'salary' => 500
        ];
        
        $result = $this->repository->signPlayerFromWaivers(
            123,
            $team,
            $contractData
        );
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
        $this->assertStringContainsString('ordinal', $queries[0]);
        $this->assertStringContainsString('800', $queries[0]);
        $this->assertStringNotContainsString('salary_yr1', $queries[0]);
    }
    
    public function testSignPlayerFromWaiversWithNewContractDuringFreeAgency(): void
    {
        $this->mockDb->setReturnTrue(true);

        $team = [
            'team_name' => 'Los Angeles Lakers',
            'teamid' => 14
        ];

        $contractData = [
            'hasExistingContract' => false,
            'salary' => 76
        ];

        $result = $this->repository->signPlayerFromWaivers(
            456,
            $team,
            $contractData
        );

        $this->assertTrue($result);

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
        $this->assertStringContainsString('ordinal', $queries[0]);
        $this->assertStringContainsString('800', $queries[0]);
        $this->assertStringContainsString('salary_yr1 = 76', $queries[0]);
        $this->assertStringContainsString('cy = 0', $queries[0]);
        $this->assertStringContainsString('cyt = 1', $queries[0]);
        $this->assertStringContainsString('droptime', $queries[0]);
        $this->assertStringContainsString('= 0', $queries[0]);
    }

    // ==================== DI Seam ====================

    public function testInjectedChannelLoggerReceivesCallOnDbError(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setReturnTrue(false); // make the UPDATE fail → RuntimeException → logger path

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with('Failed to sign player from waivers', self::arrayHasKey('error'));

        $repo = new WaiversRepository($mockDb, $logger);
        $result = $repo->signPlayerFromWaivers(
            123,
            ['teamid' => 1, 'team_name' => 'Test'],
            ['hasExistingContract' => false, 'salary' => 100]
        );

        $this->assertFalse($result);
    }

    public function testConstructsWithoutLoggerArgAndFallbackFires(): void
    {
        // no logger arg → fallback fires; prove the repo is usable (no TypeError)
        $mockDb = new MockDatabase();
        $repo = new WaiversRepository($mockDb);
        $mockDb->setReturnTrue(true);
        $result = $repo->dropPlayerToWaivers(1, time());
        $this->assertTrue($result);
    }

}
