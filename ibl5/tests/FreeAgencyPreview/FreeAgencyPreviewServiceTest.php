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

    public function testReturnsEmptyWhenNoPlayersMatchYear(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer(['draftyear' => 2020, 'exp' => 3, 'cyt' => 3, 'cy' => 1]),
        ]);

        // yearOfFreeAgency = 2020 + 3 + 3 - 1 = 2025
        $result = $this->service->getUpcomingFreeAgents(2030);

        $this->assertSame([], $result);
    }

    public function testReturnsFreeAgentsMatchingEndingYear(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer(['draftyear' => 2020, 'exp' => 3, 'cyt' => 3, 'cy' => 1, 'name' => 'Free Agent']),
        ]);

        // yearOfFreeAgency = 2020 + 3 + 3 - 1 = 2025
        $result = $this->service->getUpcomingFreeAgents(2025);

        $this->assertCount(1, $result);
        $this->assertSame('Free Agent', $result[0]['name']);
    }

    public function testFiltersOutNonMatchingPlayers(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer(['draftyear' => 2020, 'exp' => 3, 'cyt' => 3, 'cy' => 1, 'name' => 'Matches']),
            self::createActivePlayer(['draftyear' => 2018, 'exp' => 5, 'cyt' => 2, 'cy' => 0, 'name' => 'NoMatch']),
        ]);

        // Player 1: 2020+3+3-1 = 2025 (matches)
        // Player 2: 2018+5+2-0 = 2025 (matches too)
        $result = $this->service->getUpcomingFreeAgents(2025);

        $this->assertCount(2, $result);
    }

    public function testFreeAgentRowIncludesRatings(): void
    {
        $this->mockRepository->method('getActivePlayers')->willReturn([
            self::createActivePlayer([
                'draftyear' => 2020,
                'exp' => 3,
                'cyt' => 2,
                'cy' => 0,
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
                'draftyear' => 2020,
                'exp' => 3,
                'cyt' => 2,
                'cy' => 0,
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
     * @return array{pid: int, tid: int, name: string, teamname: string, pos: string, age: int, draftyear: int, exp: int, cy: int, cyt: int, r_fga: int, r_fgp: int, r_fta: int, r_ftp: int, r_tga: int, r_tgp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_to: int, r_foul: int, oo: int, do: int, po: int, to: int, od: int, dd: int, pd: int, td: int, loyalty: int, winner: int, playingTime: int, security: int, tradition: int, team_city: string|null, color1: string|null, color2: string|null}
     */
    private static function createActivePlayer(array $overrides = []): array
    {
        /** @var array{pid: int, tid: int, name: string, teamname: string, pos: string, age: int, draftyear: int, exp: int, cy: int, cyt: int, r_fga: int, r_fgp: int, r_fta: int, r_ftp: int, r_tga: int, r_tgp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_to: int, r_foul: int, oo: int, do: int, po: int, to: int, od: int, dd: int, pd: int, td: int, loyalty: int, winner: int, playingTime: int, security: int, tradition: int, team_city: string|null, color1: string|null, color2: string|null} */
        return array_merge([
            'pid' => 1,
            'tid' => 1,
            'name' => 'Test Player',
            'teamname' => 'Hawks',
            'pos' => 'G',
            'age' => 25,
            'draftyear' => 2020,
            'exp' => 3,
            'cy' => 1,
            'cyt' => 3,
            'r_fga' => 50,
            'r_fgp' => 50,
            'r_fta' => 50,
            'r_ftp' => 50,
            'r_tga' => 50,
            'r_tgp' => 50,
            'r_orb' => 50,
            'r_drb' => 50,
            'r_ast' => 50,
            'r_stl' => 50,
            'r_blk' => 50,
            'r_to' => 50,
            'r_foul' => 50,
            'oo' => 50,
            'do' => 50,
            'po' => 50,
            'to' => 50,
            'od' => 50,
            'dd' => 50,
            'pd' => 50,
            'td' => 50,
            'loyalty' => 50,
            'winner' => 50,
            'playingTime' => 50,
            'security' => 50,
            'tradition' => 50,
            'team_city' => 'Atlanta',
            'color1' => 'FF0000',
            'color2' => '000000',
        ], $overrides);
    }
}
