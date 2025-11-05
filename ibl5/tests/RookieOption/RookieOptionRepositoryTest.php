<?php

use PHPUnit\Framework\TestCase;
use RookieOption\RookieOptionRepository;

/**
 * Tests for RookieOptionRepository
 */
class RookieOptionRepositoryTest extends TestCase
{
    private $repository;
    private $mockDb;
    
    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new RookieOptionRepository($this->mockDb);
    }
    
    /**
     * Test updating player rookie option for first round pick
     */
    public function testUpdatePlayerRookieOptionFirstRound()
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->repository->updatePlayerRookieOption(123, 1, 200);
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
        $this->assertStringContainsString('cy4', $queries[0]);
        $this->assertStringContainsString('200', $queries[0]);
        $this->assertStringContainsString('WHERE pid = 123', $queries[0]);
    }
    
    /**
     * Test updating player rookie option for second round pick
     */
    public function testUpdatePlayerRookieOptionSecondRound()
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->repository->updatePlayerRookieOption(456, 2, 150);
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
        $this->assertStringContainsString('cy3', $queries[0]);
        $this->assertStringContainsString('150', $queries[0]);
        $this->assertStringContainsString('WHERE pid = 456', $queries[0]);
    }
}
