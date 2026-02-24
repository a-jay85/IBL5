<?php

declare(strict_types=1);

namespace Tests\FranchiseRecordBook;

use FranchiseRecordBook\Contracts\FranchiseRecordBookRepositoryInterface;
use FranchiseRecordBook\FranchiseRecordBookService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FranchiseRecordBook\FranchiseRecordBookService
 */
class FranchiseRecordBookServiceTest extends TestCase
{
    /** @var FranchiseRecordBookRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private FranchiseRecordBookRepositoryInterface $repository;

    private FranchiseRecordBookService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(FranchiseRecordBookRepositoryInterface::class);
        $this->service = new FranchiseRecordBookService($this->repository);
    }

    /**
     * @return array{id: int, scope: string, team_id: int|null, record_type: string, stat_category: string, ranking: int, player_name: string, car_block_id: int|null, pid: int|null, stat_value: string, stat_raw: int, team_of_record: int|null, season_year: int|null, career_total: int|null}
     */
    private function makeRecord(
        string $statCategory = 'ppg',
        string $recordType = 'single_season',
        int $ranking = 1,
        string $playerName = 'Test Player',
    ): array {
        return [
            'id' => 1,
            'scope' => 'team',
            'team_id' => 1,
            'record_type' => $recordType,
            'stat_category' => $statCategory,
            'ranking' => $ranking,
            'player_name' => $playerName,
            'car_block_id' => 100,
            'pid' => null,
            'stat_value' => '36.11',
            'stat_raw' => 3611,
            'team_of_record' => 1,
            'season_year' => 2005,
            'career_total' => null,
        ];
    }

    public function testGetTeamRecordBookReturnsGroupedData(): void
    {
        $records = [
            $this->makeRecord('ppg', 'single_season', 1, 'Player A'),
            $this->makeRecord('ppg', 'single_season', 2, 'Player B'),
            $this->makeRecord('rpg', 'single_season', 1, 'Player C'),
        ];

        $this->repository->method('getTeamSingleSeasonRecords')->willReturn($records);
        $this->repository->method('getLeagueCareerRecords')->willReturn([]);
        $this->repository->method('getTeamInfo')->willReturn([
            'teamid' => 1, 'team_name' => 'Celtics', 'color1' => '008040', 'color2' => 'FFFFFF',
        ]);
        $this->repository->method('getAllTeams')->willReturn([]);

        $result = $this->service->getTeamRecordBook(1);

        $this->assertSame('team', $result['scope']);
        $this->assertNotNull($result['team']);
        $this->assertSame('Celtics', $result['team']['team_name']);
        $this->assertCount(2, $result['singleSeason']['ppg']);
        $this->assertCount(1, $result['singleSeason']['rpg']);
        $this->assertSame([], $result['singleSeason']['apg']);
    }

    public function testGetLeagueRecordBookReturnsNullTeam(): void
    {
        $this->repository->method('getLeagueSingleSeasonRecords')->willReturn([]);
        $this->repository->method('getLeagueCareerRecords')->willReturn([]);
        $this->repository->method('getAllTeams')->willReturn([]);

        $result = $this->service->getLeagueRecordBook();

        $this->assertSame('league', $result['scope']);
        $this->assertNull($result['team']);
    }

    public function testSingleSeasonCategoriesAreInDisplayOrder(): void
    {
        $this->repository->method('getTeamSingleSeasonRecords')->willReturn([]);
        $this->repository->method('getLeagueCareerRecords')->willReturn([]);
        $this->repository->method('getTeamInfo')->willReturn([
            'teamid' => 1, 'team_name' => 'Celtics', 'color1' => '008040', 'color2' => 'FFFFFF',
        ]);
        $this->repository->method('getAllTeams')->willReturn([]);

        $result = $this->service->getTeamRecordBook(1);

        $expectedOrder = ['ppg', 'rpg', 'apg', 'spg', 'bpg', 'fg_pct', 'ft_pct', 'three_pct'];
        $this->assertSame($expectedOrder, array_keys($result['singleSeason']));
    }

    public function testCareerCategoriesAreInDisplayOrder(): void
    {
        $this->repository->method('getLeagueSingleSeasonRecords')->willReturn([]);
        $this->repository->method('getLeagueCareerRecords')->willReturn([]);
        $this->repository->method('getAllTeams')->willReturn([]);

        $result = $this->service->getLeagueRecordBook();

        $expectedOrder = ['pts', 'trb', 'ast', 'stl', 'blk', 'fg_pct', 'ft_pct', 'three_pct'];
        $this->assertSame($expectedOrder, array_keys($result['career']));
    }

    public function testStatLabelsContainAllCategories(): void
    {
        $allCategories = ['ppg', 'rpg', 'apg', 'spg', 'bpg', 'fg_pct', 'ft_pct', 'three_pct', 'pts', 'trb', 'ast', 'stl', 'blk'];

        foreach ($allCategories as $category) {
            $this->assertArrayHasKey($category, FranchiseRecordBookService::STAT_LABELS, "Missing label for {$category}");
            $this->assertArrayHasKey($category, FranchiseRecordBookService::STAT_ABBREV, "Missing abbreviation for {$category}");
        }
    }
}
