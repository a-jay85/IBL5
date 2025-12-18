<?php

use PHPUnit\Framework\TestCase;
use Waivers\WaiversRepository;

class WaiversRepositoryTest extends TestCase
{
    private $mockDb;
    private $repository;
    
    protected function setUp(): void
    {
        // Create MockDatabase that duck-types mysqli for testing
        $this->mockDb = new MockDatabase();
        $this->repository = new WaiversRepository($this->mockDb);
    }
    
    // Tests for getUserByUsername, getTeamByName, getTeamTotalSalary, and getPlayerByID
    // have been moved to CommonRepositoryTest as these methods now delegate to CommonRepository
    
    public function testDropPlayerToWaiversExecutesCorrectQuery()
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
        $this->assertStringContainsString('WHERE `pid` = 123', $queries[0]);
    }
    
    public function testSignPlayerFromWaiversWithNewContract()
    {
        $this->mockDb->setReturnTrue(true);
        
        $team = [
            'teamname' => 'Boston Celtics',
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
        $this->assertStringContainsString('cy1', $queries[0]);
        $this->assertStringContainsString('103', $queries[0]);
        $this->assertStringContainsString('`cy` = 0', $queries[0]);
        $this->assertStringContainsString('`cyt` = 1', $queries[0]);
        $this->assertStringContainsString('droptime', $queries[0]);
        $this->assertStringContainsString('= 0', $queries[0]);
    }
    
    public function testSignPlayerFromWaiversWithExistingContract()
    {
        $this->mockDb->setReturnTrue(true);
        
        $team = [
            'teamname' => 'Boston Celtics',
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
        $this->assertStringNotContainsString('cy1', $queries[0]);
    }
    
    public function testSignPlayerFromWaiversWithNewContractDuringFreeAgency()
    {
        $this->mockDb->setReturnTrue(true);
        
        $team = [
            'teamname' => 'Los Angeles Lakers',
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
        $this->assertStringContainsString('`cy1` = 76', $queries[0]);
        $this->assertStringContainsString('`cy` = 0', $queries[0]);
        $this->assertStringContainsString('`cyt` = 1', $queries[0]);
        $this->assertStringContainsString('droptime', $queries[0]);
        $this->assertStringContainsString('= 0', $queries[0]);
    }
    
}
