<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PlayerSearch\PlayerSearchService;
use PlayerSearch\PlayerSearchValidator;
use PlayerSearch\PlayerSearchRepository;
use Player\PlayerRepository;
use Player\PlayerData;

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
    private $mockSearchRepository;
    /** @var PlayerRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $mockPlayerRepository;

    protected function setUp(): void
    {
        $this->validator = new PlayerSearchValidator();
        $this->mockSearchRepository = $this->createMock(PlayerSearchRepository::class);
        $this->mockPlayerRepository = $this->createMock(PlayerRepository::class);
        $this->service = new PlayerSearchService(
            $this->validator,
            $this->mockSearchRepository,
            $this->mockPlayerRepository
        );
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
        $this->mockSearchRepository->expects($this->once())
            ->method('searchPlayers')
            ->willReturn(['results' => [], 'count' => 0]);

        $result = $this->service->search(['submitted' => '1']);

        $this->assertArrayHasKey('players', $result);
        $this->assertArrayHasKey('count', $result);
    }

    public function testSearchConvertsRawDataToPlayerDataObjects(): void
    {
        $mockPlayerData1 = $this->createMock(PlayerData::class);
        $mockPlayerData1->playerID = 1;
        $mockPlayerData1->name = 'Test Player 1';

        $mockPlayerData2 = $this->createMock(PlayerData::class);
        $mockPlayerData2->playerID = 2;
        $mockPlayerData2->name = 'Test Player 2';

        $rawPlayers = [
            ['pid' => 1, 'name' => 'Test Player 1'],
            ['pid' => 2, 'name' => 'Test Player 2']
        ];

        $this->mockSearchRepository->expects($this->once())
            ->method('searchPlayers')
            ->willReturn(['results' => $rawPlayers, 'count' => 2]);

        $this->mockPlayerRepository->expects($this->exactly(2))
            ->method('fillFromCurrentRow')
            ->willReturnOnConsecutiveCalls($mockPlayerData1, $mockPlayerData2);

        $result = $this->service->search(['submitted' => '1']);

        $this->assertCount(2, $result['players']);
        $this->assertInstanceOf(PlayerData::class, $result['players'][0]);
        $this->assertInstanceOf(PlayerData::class, $result['players'][1]);
        $this->assertEquals(2, $result['count']);
    }

    public function testSearchCallsPlayerRepositoryFillFromCurrentRowForEachPlayer(): void
    {
        $rawPlayers = [
            ['pid' => 1, 'name' => 'Player 1'],
            ['pid' => 2, 'name' => 'Player 2'],
            ['pid' => 3, 'name' => 'Player 3']
        ];

        $mockPlayerData = $this->createMock(PlayerData::class);

        $this->mockSearchRepository->expects($this->once())
            ->method('searchPlayers')
            ->willReturn(['results' => $rawPlayers, 'count' => 3]);

        $this->mockPlayerRepository->expects($this->exactly(3))
            ->method('fillFromCurrentRow')
            ->willReturn($mockPlayerData);

        $result = $this->service->search(['submitted' => '1']);

        $this->assertCount(3, $result['players']);
    }

}
