<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\PlayerStatsRepository;
use Player\Contracts\PlayerStatsRepositoryInterface;

/**
 * Tests for PlayerStatsRepository
 */
class PlayerStatsRepositoryTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        
        $repository = new PlayerStatsRepository($mockDb);
        
        $this->assertInstanceOf(PlayerStatsRepositoryInterface::class, $repository);
    }

    public function testGetPlayerStatsReturnsNullForNonExistentPlayer(): void
    {
        // Create a mock mysqli that returns null
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($this->createMockEmptyResult());
        $mockStmt->method('close')->willReturn(true);
        
        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);
        
        $repository = new PlayerStatsRepository($mockDb);
        $result = $repository->getPlayerStats(99999);
        
        $this->assertNull($result);
    }

    public function testGetHistoricalStatsReturnsEmptyArrayForNoData(): void
    {
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($this->createMockEmptyResult());
        $mockStmt->method('close')->willReturn(true);
        
        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);
        
        $repository = new PlayerStatsRepository($mockDb);
        $result = $repository->getHistoricalStats(99999, 'regular');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetHistoricalStatsNormalizesData(): void
    {
        // Create mock data with raw database column names
        $rawData = [
            [
                'year' => '2025',
                'team' => 'BOS',
                'gm' => 82,
                'min' => 2460,
                'fgm' => 500,
                'fga' => 1000,
                'ftm' => 200,
                'fta' => 250,
                '3gm' => 100,
                '3ga' => 250,
                'orb' => 100,
                'reb' => 500,
                'ast' => 300,
                'stl' => 100,
                'blk' => 50,
                'tvr' => 150,
                'pf' => 200
            ]
        ];
        
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($this->createMockResultWithData($rawData));
        $mockStmt->method('close')->willReturn(true);
        
        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);
        
        $repository = new PlayerStatsRepository($mockDb);
        $result = $repository->getHistoricalStats(1, 'regular');
        
        $this->assertCount(1, $result);
        $this->assertEquals('2025', $result[0]['year']);
        $this->assertEquals('BOS', $result[0]['team']);
        $this->assertEquals(82, $result[0]['games']);
        $this->assertEquals(2460, $result[0]['minutes']);
        $this->assertEquals(500, $result[0]['fgm']);
        $this->assertEquals(1000, $result[0]['fga']);
        $this->assertEquals(200, $result[0]['ftm']);
        $this->assertEquals(250, $result[0]['fta']);
        $this->assertEquals(100, $result[0]['tgm']); // Normalized from '3gm'
        $this->assertEquals(250, $result[0]['tga']); // Normalized from '3ga'
        $this->assertEquals(300, $result[0]['ast']);
        $this->assertEquals(100, $result[0]['stl']);
        $this->assertEquals(50, $result[0]['blk']);
        $this->assertEquals(150, $result[0]['tovr']); // Normalized from 'tvr'
        // Points = (2 * fgm) + ftm + tgm = (2*500) + 200 + 100 = 1300
        $this->assertEquals(1300, $result[0]['pts']);
    }

    public function testGetPlayerBoxScoresReturnsEmptyArrayForNoData(): void
    {
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($this->createMockEmptyResult());
        $mockStmt->method('close')->willReturn(true);
        
        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);
        
        $repository = new PlayerStatsRepository($mockDb);
        $result = $repository->getPlayerBoxScores(1, '2025-01-01', '2025-06-30');
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetSimAggregatedStatsReturnsZeroesForNoData(): void
    {
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($this->createMockEmptyResult());
        $mockStmt->method('close')->willReturn(true);
        
        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);
        
        $repository = new PlayerStatsRepository($mockDb);
        $result = $repository->getSimAggregatedStats(1, '2025-01-01', '2025-01-15');
        
        $this->assertIsArray($result);
        $this->assertEquals(0, $result['games']);
        $this->assertEquals(0, $result['minutes']);
        $this->assertEquals(0, $result['points']);
    }

    public function testGetSimAggregatedStatsAggregatesCorrectly(): void
    {
        $boxScoreData = [
            [
                'gameMIN' => 36,
                'game2GM' => 8,
                'game2GA' => 15,
                'gameFTM' => 5,
                'gameFTA' => 6,
                'game3GM' => 3,
                'game3GA' => 8,
                'gameORB' => 2,
                'gameDRB' => 5,
                'gameAST' => 6,
                'gameSTL' => 2,
                'gameTOV' => 3,
                'gameBLK' => 1,
                'gamePF' => 2
            ],
            [
                'gameMIN' => 32,
                'game2GM' => 6,
                'game2GA' => 12,
                'gameFTM' => 4,
                'gameFTA' => 5,
                'game3GM' => 2,
                'game3GA' => 6,
                'gameORB' => 1,
                'gameDRB' => 4,
                'gameAST' => 5,
                'gameSTL' => 1,
                'gameTOV' => 2,
                'gameBLK' => 0,
                'gamePF' => 3
            ]
        ];
        
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($this->createMockResultWithData($boxScoreData));
        $mockStmt->method('close')->willReturn(true);
        
        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);
        
        $repository = new PlayerStatsRepository($mockDb);
        $result = $repository->getSimAggregatedStats(1, '2025-01-01', '2025-01-15');
        
        $this->assertEquals(2, $result['games']);
        $this->assertEquals(68, $result['minutes']); // 36 + 32
        $this->assertEquals(14, $result['fg2Made']); // 8 + 6
        $this->assertEquals(27, $result['fg2Attempted']); // 15 + 12
        $this->assertEquals(9, $result['ftMade']); // 5 + 4
        $this->assertEquals(5, $result['fg3Made']); // 3 + 2
        // Points = (2*8 + 5 + 3*3) + (2*6 + 4 + 2*3) = (16+5+9) + (12+4+6) = 30 + 22 = 52
        $this->assertEquals(52, $result['points']);
    }

    public function testGetCareerTotalsReturnsNullForNonExistentPlayer(): void
    {
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($this->createMockEmptyResult());
        $mockStmt->method('close')->willReturn(true);
        
        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);
        
        $repository = new PlayerStatsRepository($mockDb);
        $result = $repository->getCareerTotals(99999);
        
        $this->assertNull($result);
    }

    /**
     * Create a mock result that returns no rows
     */
    private function createMockEmptyResult(): \mysqli_result
    {
        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->method('fetch_assoc')->willReturn(null);
        return $mockResult;
    }

    /**
     * Create a mock result that returns the given data
     */
    private function createMockResultWithData(array $data): \mysqli_result
    {
        $index = 0;
        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->method('fetch_assoc')->willReturnCallback(function () use (&$index, $data) {
            if ($index < count($data)) {
                return $data[$index++];
            }
            return null;
        });
        return $mockResult;
    }
}
