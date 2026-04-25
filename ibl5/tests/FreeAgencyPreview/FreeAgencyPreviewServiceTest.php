<?php

declare(strict_types=1);

namespace Tests\FreeAgencyPreview;

use FreeAgencyPreview\Contracts\FreeAgencyPreviewRepositoryInterface;
use FreeAgencyPreview\Contracts\FreeAgencyPreviewServiceInterface;
use FreeAgencyPreview\FreeAgencyPreviewService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FreeAgencyPreview\FreeAgencyPreviewService
 */
#[AllowMockObjectsWithoutExpectations]
class FreeAgencyPreviewServiceTest extends TestCase
{
    /** @var FreeAgencyPreviewRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private FreeAgencyPreviewRepositoryInterface $mockRepository;
    private FreeAgencyPreviewService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(FreeAgencyPreviewRepositoryInterface::class);
        $this->service = new FreeAgencyPreviewService($this->mockRepository);
    }

    public function testImplementsServiceInterface(): void
    {
        $this->assertInstanceOf(FreeAgencyPreviewServiceInterface::class, $this->service);
    }

    public function testExcludesPlayersWithNonZeroNextYearSalary(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer(['cy' => 1, 'salary_yr2' => 600]),
        ]);

        $result = $this->service->getUpcomingFreeAgents(2025);

        $this->assertSame([], $result);
    }

    public function testIncludesPlayersWithZeroNextYearSalary(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer(['cy' => 2, 'salary_yr3' => 0, 'name' => 'Free Agent']),
        ]);

        $result = $this->service->getUpcomingFreeAgents(2025);

        $this->assertCount(1, $result);
        $this->assertSame('Free Agent', $result[0]['name']);
    }

    public function testFiltersOutPlayersUnderContract(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer(['cy' => 1, 'salary_yr2' => 0, 'name' => 'Expiring']),
            self::createActivePlayer(['cy' => 1, 'salary_yr2' => 500, 'name' => 'UnderContract']),
        ]);

        $result = $this->service->getUpcomingFreeAgents(2025);

        $this->assertCount(1, $result);
        $this->assertSame('Expiring', $result[0]['name']);
    }

    public function testIncludesPlayersWithAllZeroContractData(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer([
                'cy' => 0,
                'salary_yr1' => 0,
                'salary_yr2' => 0,
                'salary_yr3' => 0,
                'name' => 'No Contract',
            ]),
        ]);

        $result = $this->service->getUpcomingFreeAgents(2025);

        $this->assertCount(1, $result);
        $this->assertSame('No Contract', $result[0]['name']);
    }

    public function testFreeAgentRowIncludesRatings(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer([
                'cy' => 2,
                'salary_yr3' => 0,
                'r_fga' => 65,
                'oo' => 70,
            ]),
        ]);

        $result = $this->service->getUpcomingFreeAgents(2025);

        $this->assertSame(65, $result[0]['r_fga']);
        $this->assertSame(70, $result[0]['oo']);
    }

    public function testFreeAgentRowDefaultsNullTeamFields(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer([
                'cy' => 2,
                'salary_yr3' => 0,
                'team_city' => null,
                'color1' => null,
                'color2' => null,
            ]),
        ]);

        $result = $this->service->getUpcomingFreeAgents(2025);

        $this->assertSame('', $result[0]['team_city']);
        $this->assertSame('FFFFFF', $result[0]['color1']);
        $this->assertSame('000000', $result[0]['color2']);
    }

    public function testReturnsEmptyWhenNoPlayers(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([]);

        $result = $this->service->getUpcomingFreeAgents(2025);

        $this->assertSame([], $result);
    }

    /**
     * @return array{pid: int, teamid: int, name: string, teamname: string, pos: string, age: int, draftyear: int, exp: int, cy: int, cyt: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int, r_fga: int, r_fgp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_foul: int, oo: int, r_drive_off: int, po: int, r_trans_off: int, od: int, dd: int, pd: int, td: int, loyalty: int, winner: int, playing_time: int, security: int, tradition: int, team_city: string|null, color1: string|null, color2: string|null}
     */
    private static function createActivePlayer(array $overrides = []): array
    {
        /** @var array{pid: int, teamid: int, name: string, teamname: string, pos: string, age: int, draftyear: int, exp: int, cy: int, cyt: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int, r_fga: int, r_fgp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_foul: int, oo: int, r_drive_off: int, po: int, r_trans_off: int, od: int, dd: int, pd: int, td: int, loyalty: int, winner: int, playing_time: int, security: int, tradition: int, team_city: string|null, color1: string|null, color2: string|null} */
        return array_merge([
            'pid' => 1,
            'teamid' => 1,
            'name' => 'Test Player',
            'teamname' => 'Hawks',
            'pos' => 'G',
            'age' => 25,
            'draftyear' => 2020,
            'exp' => 3,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 500,
            'salary_yr2' => 600,
            'salary_yr3' => 700,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'r_fga' => 50,
            'r_fgp' => 50,
            'r_fta' => 50,
            'r_ftp' => 50,
            'r_3ga' => 50,
            'r_3gp' => 50,
            'r_orb' => 50,
            'r_drb' => 50,
            'r_ast' => 50,
            'r_stl' => 50,
            'r_blk' => 50,
            'r_tvr' => 50,
            'r_foul' => 50,
            'oo' => 50,
            'r_drive_off' => 50,
            'po' => 50,
            'r_trans_off' => 50,
            'od' => 50,
            'dd' => 50,
            'pd' => 50,
            'td' => 50,
            'loyalty' => 50,
            'winner' => 50,
            'playing_time' => 50,
            'security' => 50,
            'tradition' => 50,
            'team_city' => 'Atlanta',
            'color1' => 'FF0000',
            'color2' => '000000',
        ], $overrides);
    }
}
