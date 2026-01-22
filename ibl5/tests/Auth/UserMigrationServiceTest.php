<?php

declare(strict_types=1);

namespace Tests\Auth;

use PHPUnit\Framework\TestCase;
use Auth\UserMigrationService;
use Auth\User;

class UserMigrationServiceTest extends TestCase
{
    /** @var \mysqli&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(\mysqli::class);
    }

    public function testDetermineRoleReturnsCommissionerForSuperAdmin(): void
    {
        // Mock the nuke_authors query in constructor
        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls(
                ['aid' => 'superadmin', 'radminsuper' => 1],
                null
            );

        $this->mockDb->method('query')
            ->with('SELECT aid, radminsuper FROM nuke_authors')
            ->willReturn($mockResult);

        $service = new UserMigrationService($this->mockDb);

        $this->assertEquals(User::ROLE_COMMISSIONER, $service->determineRole('superadmin'));
        $this->assertEquals(User::ROLE_COMMISSIONER, $service->determineRole('SUPERADMIN'));
    }

    public function testDetermineRoleReturnsOwnerForRegularAdmin(): void
    {
        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls(
                ['aid' => 'regularadmin', 'radminsuper' => 0],
                null
            );

        $this->mockDb->method('query')
            ->with('SELECT aid, radminsuper FROM nuke_authors')
            ->willReturn($mockResult);

        $service = new UserMigrationService($this->mockDb);

        $this->assertEquals(User::ROLE_OWNER, $service->determineRole('regularadmin'));
    }

    public function testDetermineRoleReturnsSpectatorForNonAdmin(): void
    {
        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->method('fetch_assoc')->willReturn(null);

        $this->mockDb->method('query')
            ->with('SELECT aid, radminsuper FROM nuke_authors')
            ->willReturn($mockResult);

        $service = new UserMigrationService($this->mockDb);

        $this->assertEquals(User::ROLE_SPECTATOR, $service->determineRole('regularuser'));
    }

    public function testGetStatsReturnsCorrectCounts(): void
    {
        // Mock for constructor's admin cache query
        $mockAuthorsResult = $this->createMock(\mysqli_result::class);
        $mockAuthorsResult->method('fetch_assoc')->willReturn(null);

        // Mock for stats queries
        $mockNukeCountResult = $this->createMock(\mysqli_result::class);
        $mockNukeCountResult->method('fetch_assoc')->willReturn(['cnt' => 100]);

        $mockUsersCountResult = $this->createMock(\mysqli_result::class);
        $mockUsersCountResult->method('fetch_assoc')->willReturn(['cnt' => 50]);

        $mockMigratedCountResult = $this->createMock(\mysqli_result::class);
        $mockMigratedCountResult->method('fetch_assoc')->willReturn(['cnt' => 30]);

        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                $mockAuthorsResult,
                $mockNukeCountResult,
                $mockUsersCountResult,
                $mockMigratedCountResult
            );

        $service = new UserMigrationService($this->mockDb);
        $stats = $service->getStats();

        $this->assertEquals(100, $stats['total_nuke']);
        $this->assertEquals(50, $stats['total_users']);
        $this->assertEquals(30, $stats['migrated']);
        $this->assertEquals(70, $stats['pending']);
    }

    public function testSyncFromNukeWithDryRunDoesNotModifyDatabase(): void
    {
        // Mock for constructor's admin cache query
        $mockAuthorsResult = $this->createMock(\mysqli_result::class);
        $mockAuthorsResult->method('fetch_assoc')->willReturn(null);

        // Mock for nuke_users query
        $mockUsersResult = $this->createMock(\mysqli_result::class);
        $mockUsersResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls(
                [
                    'user_id' => 1,
                    'username' => 'testuser1',
                    'user_email' => 'test1@example.com',
                    'user_password' => md5('password'),
                    'user_ibl_team' => 'CHI',
                    'user_active' => 1,
                ],
                [
                    'user_id' => 2,
                    'username' => 'testuser2',
                    'user_email' => 'test2@example.com',
                    'user_password' => md5('password'),
                    'user_ibl_team' => '',
                    'user_active' => 1,
                ],
                null
            );

        // Mock for isUserMigrated check
        $mockCheckStmt = $this->createMock(\mysqli_stmt::class);
        $mockCheckResult = $this->createMock(\mysqli_result::class);
        $mockCheckResult->method('fetch_assoc')->willReturn(['cnt' => 0]);
        $mockCheckStmt->method('execute')->willReturn(true);
        $mockCheckStmt->method('get_result')->willReturn($mockCheckResult);
        $mockCheckStmt->method('bind_param')->willReturn(true);

        $this->mockDb->method('query')
            ->willReturnOnConsecutiveCalls(
                $mockAuthorsResult,
                $mockUsersResult
            );

        $this->mockDb->method('prepare')->willReturn($mockCheckStmt);

        $service = new UserMigrationService($this->mockDb);
        $result = $service->syncFromNuke(dryRun: true);

        $this->assertEquals(2, $result['migrated']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEmpty($result['errors']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('teamsOwnedParsingProvider')]
    public function testTeamsOwnedParsing(string $input, array $expected): void
    {
        $user = new User(['teams_owned' => $input]);
        $this->assertEquals($expected, $user->getTeamsOwned());
    }

    /**
     * @return array<string, array{string, array<string>}>
     */
    public static function teamsOwnedParsingProvider(): array
    {
        return [
            'empty string' => ['', []],
            'single team' => ['CHI', ['CHI']],
            'comma separated' => ['CHI, LAL, BOS', ['CHI', 'LAL', 'BOS']],
            'json array' => ['["CHI", "LAL"]', ['CHI', 'LAL']],
            'json array with integers' => ['[1, 2, 3]', ['1', '2', '3']],
        ];
    }
}
