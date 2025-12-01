<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PlayerSearch\PlayerSearchService;
use PlayerSearch\PlayerSearchValidator;
use PlayerSearch\PlayerSearchRepository;

/**
 * Tests for PlayerSearchService
 * 
 * Tests business logic layer for player search functionality.
 */
final class PlayerSearchServiceTest extends TestCase
{
    private PlayerSearchService $service;
    private PlayerSearchValidator $validator;
    /** @var PlayerSearchRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $mockRepository;

    protected function setUp(): void
    {
        $this->validator = new PlayerSearchValidator();
        $this->mockRepository = $this->createMock(PlayerSearchRepository::class);
        $this->service = new PlayerSearchService($this->validator, $this->mockRepository);
    }

    // ========== Search Method Tests ==========

    public function testSearchReturnsEmptyWhenFormNotSubmitted(): void
    {
        $result = $this->service->search([]);

        $this->assertEmpty($result['players']);
        $this->assertEquals(0, $result['count']);
        $this->assertArrayHasKey('params', $result);
    }

    public function testSearchCallsRepositoryWhenFormSubmitted(): void
    {
        $this->mockRepository->expects($this->once())
            ->method('searchPlayers')
            ->willReturn(['results' => [], 'count' => 0]);

        $result = $this->service->search(['submitted' => '1']);

        $this->assertArrayHasKey('players', $result);
        $this->assertArrayHasKey('count', $result);
    }

    public function testSearchReturnsPlayersFromRepository(): void
    {
        $mockPlayers = [
            ['pid' => 1, 'name' => 'Test Player 1'],
            ['pid' => 2, 'name' => 'Test Player 2']
        ];

        $this->mockRepository->expects($this->once())
            ->method('searchPlayers')
            ->willReturn(['results' => $mockPlayers, 'count' => 2]);

        $result = $this->service->search(['submitted' => '1']);

        $this->assertEquals($mockPlayers, $result['players']);
        $this->assertEquals(2, $result['count']);
    }

    // ========== Process Player For Display Tests ==========

    public function testProcessPlayerForDisplayReturnsAllFields(): void
    {
        $player = [
            'pid' => 123,
            'name' => 'Test Player',
            'pos' => 'PG',
            'tid' => 5,
            'teamname' => 'Test Team',
            'retired' => 0,
            'age' => 25,
            'sta' => 80,
            'college' => 'UCLA',
            'exp' => 5,
            'bird' => 3,
            'r_fga' => 60,
            'r_fgp' => 55,
            'r_fta' => 70,
            'r_ftp' => 85,
            'r_tga' => 40,
            'r_tgp' => 38,
            'r_orb' => 45,
            'r_drb' => 50,
            'r_ast' => 75,
            'r_stl' => 65,
            'r_to' => 30,
            'r_blk' => 35,
            'r_foul' => 40,
            'oo' => 80,
            'do' => 75,
            'po' => 60,
            'to' => 85,
            'od' => 70,
            'dd' => 65,
            'pd' => 55,
            'td' => 78,
            'talent' => 85,
            'skill' => 80,
            'intangibles' => 75,
            'Clutch' => 90,
            'Consistency' => 85,
        ];

        $result = $this->service->processPlayerForDisplay($player);

        // Check identification fields
        $this->assertEquals(123, $result['pid']);
        $this->assertEquals('Test Player', $result['name']);
        $this->assertEquals('PG', $result['pos']);
        $this->assertEquals(5, $result['tid']);
        $this->assertEquals('Test Team', $result['teamname']);
        $this->assertEquals(0, $result['retired']);

        // Check attributes
        $this->assertEquals(25, $result['age']);
        $this->assertEquals(80, $result['sta']);
        $this->assertEquals('UCLA', $result['college']);
        
        // Check ratings
        $this->assertEquals(60, $result['r_fga']);
        $this->assertEquals(80, $result['oo']);
        
        // Check meta attributes
        $this->assertEquals(85, $result['talent']);
        $this->assertEquals(90, $result['Clutch']);
    }

    public function testProcessPlayerForDisplayHandlesMissingFields(): void
    {
        $player = ['pid' => 456, 'name' => 'Minimal Player'];

        $result = $this->service->processPlayerForDisplay($player);

        // Should have defaults for missing fields
        $this->assertEquals(456, $result['pid']);
        $this->assertEquals('Minimal Player', $result['name']);
        $this->assertEquals('', $result['pos']); // Empty string default
        $this->assertEquals(0, $result['age']); // Zero default
        $this->assertEquals(0, $result['r_fga']); // Zero default
    }

    public function testProcessPlayerForDisplayCastsTypes(): void
    {
        $player = [
            'pid' => '123', // String that should be int
            'name' => 456,  // Number that should be string
            'age' => '25',  // String that should be int
            'retired' => '0', // String that should be int
        ];

        $result = $this->service->processPlayerForDisplay($player);

        $this->assertIsInt($result['pid']);
        $this->assertIsString($result['name']);
        $this->assertIsInt($result['age']);
        $this->assertIsInt($result['retired']);
    }

}
