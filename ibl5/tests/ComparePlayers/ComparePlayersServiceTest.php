<?php

declare(strict_types=1);

namespace Tests\ComparePlayers;

use PHPUnit\Framework\TestCase;
use ComparePlayers\ComparePlayersService;
use ComparePlayers\Contracts\ComparePlayersRepositoryInterface;

class ComparePlayersServiceTest extends TestCase
{
    private ComparePlayersRepositoryInterface $mockRepository;
    private ComparePlayersService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMockRepository();
        $this->service = new ComparePlayersService($this->mockRepository);
    }

    public function testGetPlayerNamesReturnsArray(): void
    {
        $result = $this->service->getPlayerNames();

        $this->assertIsArray($result);
    }

    public function testComparePlayersReturnsNullForEmptyPlayer1(): void
    {
        $result = $this->service->comparePlayers('', 'Kobe Bryant');

        $this->assertNull($result);
    }

    public function testComparePlayersReturnsNullForEmptyPlayer2(): void
    {
        $result = $this->service->comparePlayers('Michael Jordan', '');

        $this->assertNull($result);
    }

    public function testComparePlayersReturnsNullForBothEmpty(): void
    {
        $result = $this->service->comparePlayers('', '');

        $this->assertNull($result);
    }

    public function testComparePlayersTrimsWhitespace(): void
    {
        $mockRepo = $this->createMock(ComparePlayersRepositoryInterface::class);
        $mockRepo->expects($this->exactly(2))
            ->method('getPlayerByName')
            ->willReturnCallback(function ($name) {
                if ($name === 'Jordan') {
                    return ['pid' => 1, 'name' => 'Jordan'];
                }
                if ($name === 'Bryant') {
                    return ['pid' => 2, 'name' => 'Bryant'];
                }
                return null;
            });

        $service = new ComparePlayersService($mockRepo);
        $result = $service->comparePlayers('  Jordan  ', '  Bryant  ');

        $this->assertNotNull($result);
        $this->assertIsArray($result);
    }

    public function testComparePlayersReturnsNullWhenPlayer1NotFound(): void
    {
        $mockRepo = $this->createMock(ComparePlayersRepositoryInterface::class);
        $mockRepo->method('getPlayerByName')
            ->willReturnCallback(function ($name) {
                if ($name === 'Kobe Bryant') {
                    return ['pid' => 2, 'name' => 'Kobe Bryant'];
                }
                return null;
            });

        $service = new ComparePlayersService($mockRepo);
        $result = $service->comparePlayers('NonExistent Player', 'Kobe Bryant');

        $this->assertNull($result);
    }

    public function testComparePlayersReturnsNullWhenPlayer2NotFound(): void
    {
        $mockRepo = $this->createMock(ComparePlayersRepositoryInterface::class);
        $mockRepo->method('getPlayerByName')
            ->willReturnCallback(function ($name) {
                if ($name === 'Michael Jordan') {
                    return ['pid' => 1, 'name' => 'Michael Jordan'];
                }
                return null;
            });

        $service = new ComparePlayersService($mockRepo);
        $result = $service->comparePlayers('Michael Jordan', 'NonExistent Player');

        $this->assertNull($result);
    }

    public function testComparePlayersReturnsNullWhenBothNotFound(): void
    {
        $mockRepo = $this->createMock(ComparePlayersRepositoryInterface::class);
        $mockRepo->method('getPlayerByName')
            ->willReturn(null);

        $service = new ComparePlayersService($mockRepo);
        $result = $service->comparePlayers('Player A', 'Player B');

        $this->assertNull($result);
    }

    public function testComparePlayersReturnsValidComparisonData(): void
    {
        $player1Data = [
            'pid' => 1,
            'name' => 'Michael Jordan',
            'pos' => 'SG',
            'age' => 28,
            'r_fga' => 85,
            'stats_gm' => 82,
            'car_pts' => 15000
        ];

        $player2Data = [
            'pid' => 2,
            'name' => 'Kobe Bryant',
            'pos' => 'SG',
            'age' => 26,
            'r_fga' => 83,
            'stats_gm' => 80,
            'car_pts' => 12000
        ];

        $mockRepo = $this->createMock(ComparePlayersRepositoryInterface::class);
        $mockRepo->method('getPlayerByName')
            ->willReturnCallback(function ($name) use ($player1Data, $player2Data) {
                if ($name === 'Michael Jordan') {
                    return $player1Data;
                }
                if ($name === 'Kobe Bryant') {
                    return $player2Data;
                }
                return null;
            });

        $service = new ComparePlayersService($mockRepo);
        $result = $service->comparePlayers('Michael Jordan', 'Kobe Bryant');

        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('player1', $result);
        $this->assertArrayHasKey('player2', $result);
        $this->assertEquals($player1Data, $result['player1']);
        $this->assertEquals($player2Data, $result['player2']);
    }

    public function testComparePlayersHandlesApostrophes(): void
    {
        $playerData = [
            'pid' => 3,
            'name' => "Shaquille O'Neal",
            'pos' => 'C',
        ];

        $mockRepo = $this->createMock(ComparePlayersRepositoryInterface::class);
        $mockRepo->method('getPlayerByName')
            ->willReturnCallback(function ($name) use ($playerData) {
                if ($name === "Shaquille O'Neal" || $name === "Tim Duncan") {
                    return $playerData;
                }
                return null;
            });

        $service = new ComparePlayersService($mockRepo);
        $result = $service->comparePlayers("Shaquille O'Neal", "Tim Duncan");

        // Should handle apostrophes without errors
        $this->assertTrue($result === null || is_array($result));
    }

    public function testComparePlayersRejectsSpacesOnlyForPlayer1(): void
    {
        $result = $this->service->comparePlayers('   ', 'Kobe Bryant');
        $this->assertNull($result);
    }

    public function testComparePlayersRejectsTabsOnlyForPlayer1(): void
    {
        $result = $this->service->comparePlayers("\t\t", 'Kobe Bryant');
        $this->assertNull($result);
    }

    public function testComparePlayersRejectsNewlinesOnlyForPlayer1(): void
    {
        $result = $this->service->comparePlayers("\n\n", 'Kobe Bryant');
        $this->assertNull($result);
    }

    public function testComparePlayersRejectsMixedWhitespaceForPlayer1(): void
    {
        $result = $this->service->comparePlayers(" \t\n ", 'Kobe Bryant');
        $this->assertNull($result);
    }

    public function testComparePlayersRejectsBothEmpty(): void
    {
        $result = $this->service->comparePlayers('  ', '  ');
        $this->assertNull($result);
    }

    public function testComparePlayersPreservesAllPlayerData(): void
    {
        $player1Data = [
            'pid' => 1,
            'name' => 'Player 1',
            'pos' => 'PG',
            'age' => 25,
            'r_fga' => 80,
            'r_fgp' => 85,
            'r_fta' => 75,
            'r_ftp' => 90,
            'oo' => 88,
            'do' => 82,
            'stats_gm' => 70,
            'stats_min' => 2500,
            'car_pts' => 8000,
        ];

        $player2Data = [
            'pid' => 2,
            'name' => 'Player 2',
            'pos' => 'SG',
            'age' => 27,
            'r_fga' => 82,
            'r_fgp' => 87,
            'r_fta' => 78,
            'r_ftp' => 88,
            'oo' => 90,
            'do' => 84,
            'stats_gm' => 75,
            'stats_min' => 2700,
            'car_pts' => 9000,
        ];

        $mockRepo = $this->createMock(ComparePlayersRepositoryInterface::class);
        $mockRepo->method('getPlayerByName')
            ->willReturnOnConsecutiveCalls($player1Data, $player2Data);

        $service = new ComparePlayersService($mockRepo);
        $result = $service->comparePlayers('Player 1', 'Player 2');

        // Verify all data is preserved
        $this->assertEquals($player1Data['pid'], $result['player1']['pid']);
        $this->assertEquals($player1Data['r_fga'], $result['player1']['r_fga']);
        $this->assertEquals($player1Data['car_pts'], $result['player1']['car_pts']);
        $this->assertEquals($player2Data['pid'], $result['player2']['pid']);
        $this->assertEquals($player2Data['r_fga'], $result['player2']['r_fga']);
        $this->assertEquals($player2Data['car_pts'], $result['player2']['car_pts']);
    }

    private function createMockRepository(): ComparePlayersRepositoryInterface
    {
        $mock = $this->createMock(ComparePlayersRepositoryInterface::class);
        $mock->method('getAllPlayerNames')
            ->willReturn(['Michael Jordan', 'Kobe Bryant', 'Tim Duncan']);
        $mock->method('getPlayerByName')
            ->willReturn(null);

        return $mock;
    }
}
