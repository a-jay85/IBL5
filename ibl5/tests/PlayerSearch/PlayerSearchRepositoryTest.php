<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PlayerSearch\PlayerSearchRepository;

/**
 * Tests for PlayerSearchRepository
 * 
 * Tests database operations and query building with prepared statements.
 */
final class PlayerSearchRepositoryTest extends TestCase
{
    /** @var mysqli&\PHPUnit\Framework\MockObject\MockObject */
    private $mockDb;
    private PlayerSearchRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(mysqli::class);
        $this->repository = new PlayerSearchRepository($this->mockDb);
    }

    // ========== Query Building Tests ==========

    public function testSearchPlayersPreparesQueryWithNoFilters(): void
    {
        $mockStmt = $this->createMock(mysqli_stmt::class);
        $mockResult = $this->createMock(mysqli_result::class);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($query) {
                // Check base query structure
                return strpos($query, 'SELECT * FROM ibl_plr') !== false
                    && strpos($query, 'WHERE pid > 0') !== false
                    && strpos($query, 'ORDER BY retired ASC, ordinal ASC') !== false;
            }))
            ->willReturn($mockStmt);

        $mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $mockStmt->expects($this->once())
            ->method('get_result')
            ->willReturn($mockResult);

        $mockResult->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn(null);

        $mockStmt->expects($this->once())
            ->method('close');

        $params = [
            'pos' => null,
            'age' => null,
            'search_name' => null,
            'college' => null,
            'exp' => null,
            'exp_max' => null,
            'bird' => null,
            'bird_max' => null,
            'active' => null,
            'r_fga' => null,
            'r_fgp' => null,
            'r_fta' => null,
            'r_ftp' => null,
            'r_tga' => null,
            'r_tgp' => null,
            'r_orb' => null,
            'r_drb' => null,
            'r_ast' => null,
            'r_stl' => null,
            'r_blk' => null,
            'r_to' => null,
            'r_foul' => null,
            'Clutch' => null,
            'Consistency' => null,
            'talent' => null,
            'skill' => null,
            'intangibles' => null,
            'oo' => null,
            'do' => null,
            'po' => null,
            'to' => null,
            'od' => null,
            'dd' => null,
            'pd' => null,
            'td' => null,
        ];

        $result = $this->repository->searchPlayers($params);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('count', $result);
    }

    public function testSearchPlayersExcludesRetiredWhenActiveIs0(): void
    {
        $mockStmt = $this->createMock(mysqli_stmt::class);
        $mockResult = $this->createMock(mysqli_result::class);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($query) {
                return strpos($query, 'retired = 0') !== false;
            }))
            ->willReturn($mockStmt);

        $mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $mockStmt->expects($this->once())
            ->method('get_result')
            ->willReturn($mockResult);

        $mockResult->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn(null);

        $mockStmt->expects($this->once())
            ->method('close');

        $params = $this->createEmptyParams();
        $params['active'] = 0;

        $this->repository->searchPlayers($params);
    }

    public function testSearchPlayersUsesLikeForNameSearch(): void
    {
        $mockResult = $this->createMock(mysqli_result::class);
        $mockStmt = $this->createMockStmt($mockResult, 's');

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($query) {
                return strpos($query, 'name LIKE ?') !== false;
            }))
            ->willReturn($mockStmt);

        $mockResult->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn(null);

        $params = $this->createEmptyParams();
        $params['search_name'] = 'Jordan';

        $this->repository->searchPlayers($params);
    }

    public function testSearchPlayersUsesLikeForCollegeSearch(): void
    {
        $mockResult = $this->createMock(mysqli_result::class);
        $mockStmt = $this->createMockStmt($mockResult, 's');

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($query) {
                return strpos($query, 'college LIKE ?') !== false;
            }))
            ->willReturn($mockStmt);

        $mockResult->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn(null);

        $params = $this->createEmptyParams();
        $params['college'] = 'UCLA';

        $this->repository->searchPlayers($params);
    }

    public function testSearchPlayersUsesExactMatchForPosition(): void
    {
        $mockResult = $this->createMock(mysqli_result::class);
        $mockStmt = $this->createMockStmt($mockResult, 's');

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($query) {
                return strpos($query, 'pos = ?') !== false;
            }))
            ->willReturn($mockStmt);

        $mockResult->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn(null);

        $params = $this->createEmptyParams();
        $params['pos'] = 'PG';

        $this->repository->searchPlayers($params);
    }

    public function testSearchPlayersUsesLessThanOrEqualForAge(): void
    {
        $mockResult = $this->createMock(mysqli_result::class);
        $mockStmt = $this->createMockStmt($mockResult, 'i');

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($query) {
                return strpos($query, 'age <= ?') !== false;
            }))
            ->willReturn($mockStmt);

        $mockResult->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn(null);

        $params = $this->createEmptyParams();
        $params['age'] = 25;

        $this->repository->searchPlayers($params);
    }

    public function testSearchPlayersUsesGreaterThanOrEqualForRatings(): void
    {
        $mockResult = $this->createMock(mysqli_result::class);
        $mockStmt = $this->createMockStmt($mockResult, 'i');

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($query) {
                return strpos($query, 'oo >= ?') !== false;
            }))
            ->willReturn($mockStmt);

        $mockResult->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn(null);

        $params = $this->createEmptyParams();
        $params['oo'] = 80;

        $this->repository->searchPlayers($params);
    }

    // Note: testSearchPlayersThrowsExceptionOnPrepareFailure removed because mysqli::$error
    // is a read-only property that cannot be set on mock objects in newer PHP versions.
    // The exception handling is still tested through integration tests.

    // ========== Get Player By ID Tests ==========

    public function testGetPlayerByIdReturnsPlayerWhenFound(): void
    {
        $mockResult = $this->createMock(mysqli_result::class);
        $mockStmt = $this->createMockStmt($mockResult, 'i');

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function ($query) {
                return strpos($query, 'WHERE pid = ?') !== false;
            }))
            ->willReturn($mockStmt);

        $playerData = ['pid' => 123, 'name' => 'Test Player'];
        $mockResult->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn($playerData);

        $result = $this->repository->getPlayerById(123);

        $this->assertEquals($playerData, $result);
    }

    public function testGetPlayerByIdReturnsNullWhenNotFound(): void
    {
        $mockResult = $this->createMock(mysqli_result::class);
        $mockStmt = $this->createMockStmt($mockResult, 'i');

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($mockStmt);

        $mockResult->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn(null);

        $result = $this->repository->getPlayerById(999);

        $this->assertNull($result);
    }

    // ========== Helper Methods ==========

    /**
     * Create a mock mysqli_stmt that properly handles the BaseMysqliRepository flow
     * 
     * BaseMysqliRepository calls: prepare -> bind_param -> execute -> get_result -> close
     * PHPUnit's mysqli_stmt mock tracks "closed" state, so we need to configure
     * the mock to not enforce strict ordering.
     * 
     * @param mysqli_result&\PHPUnit\Framework\MockObject\MockObject $mockResult
     * @param string|null $expectedBindTypes Expected type string for bind_param
     * @return mysqli_stmt&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockStmt(
        \PHPUnit\Framework\MockObject\MockObject $mockResult,
        ?string $expectedBindTypes = null
    ): \PHPUnit\Framework\MockObject\MockObject {
        $mockStmt = $this->getMockBuilder(mysqli_stmt::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['bind_param', 'execute', 'get_result', 'close'])
            ->getMock();

        if ($expectedBindTypes !== null) {
            $mockStmt->expects($this->once())
                ->method('bind_param')
                ->with($expectedBindTypes, $this->anything())
                ->willReturn(true);
        }

        $mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $mockStmt->expects($this->once())
            ->method('get_result')
            ->willReturn($mockResult);

        $mockStmt->expects($this->once())
            ->method('close')
            ->willReturn(true);

        return $mockStmt;
    }

    /**
     * Create empty params array for testing
     * 
     * @return array<string, mixed>
     */
    private function createEmptyParams(): array
    {
        return [
            'pos' => null,
            'age' => null,
            'search_name' => null,
            'college' => null,
            'exp' => null,
            'exp_max' => null,
            'bird' => null,
            'bird_max' => null,
            'active' => null,
            'r_fga' => null,
            'r_fgp' => null,
            'r_fta' => null,
            'r_ftp' => null,
            'r_tga' => null,
            'r_tgp' => null,
            'r_orb' => null,
            'r_drb' => null,
            'r_ast' => null,
            'r_stl' => null,
            'r_blk' => null,
            'r_to' => null,
            'r_foul' => null,
            'Clutch' => null,
            'Consistency' => null,
            'talent' => null,
            'skill' => null,
            'intangibles' => null,
            'oo' => null,
            'do' => null,
            'po' => null,
            'to' => null,
            'od' => null,
            'dd' => null,
            'pd' => null,
            'td' => null,
        ];
    }
}
