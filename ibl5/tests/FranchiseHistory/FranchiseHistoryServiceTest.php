<?php

declare(strict_types=1);

namespace Tests\FranchiseHistory;

use FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface;
use FranchiseHistory\FranchiseHistoryService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FranchiseHistoryService.
 *
 * DB-free: the repository is a stub returning canned raw rows, so these tests
 * exercise the assembly branches (windowByTeam merge, winpct derivation via
 * StatsFormatter, absent-source defaults, FranchiseRow build) that previously
 * lived welded to the live database inside the Repository.
 *
 * @covers \FranchiseHistory\FranchiseHistoryService
 */
class FranchiseHistoryServiceTest extends TestCase
{
    /** @var FranchiseHistoryRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private FranchiseHistoryRepositoryInterface $repository;

    private FranchiseHistoryService $service;

    protected function setUp(): void
    {
        $this->repository = self::createStub(FranchiseHistoryRepositoryInterface::class);
        $this->service = new FranchiseHistoryService($this->repository);
    }

    /**
     * @param array{teamid?: int, team_name?: string, color1?: string, color2?: string, totwins?: int, totloss?: int, winpct?: string, playoffs?: int, div_titles?: int, conf_titles?: int, ibl_titles?: int, heat_titles?: int} $overrides
     * @return array{teamid: int, team_name: string, color1: string, color2: string, totwins: int, totloss: int, winpct: string, playoffs: int, div_titles: int, conf_titles: int, ibl_titles: int, heat_titles: int}
     */
    private function summaryRow(array $overrides = []): array
    {
        return array_merge([
            'teamid' => 1,
            'team_name' => 'Metros',
            'color1' => '#000000',
            'color2' => '#FFFFFF',
            'totwins' => 100,
            'totloss' => 50,
            'winpct' => '.667',
            'playoffs' => 5,
            'div_titles' => 2,
            'conf_titles' => 1,
            'ibl_titles' => 1,
            'heat_titles' => 3,
        ], $overrides);
    }

    /**
     * @param list<array{teamid: int, team_name: string, color1: string, color2: string, totwins: int, totloss: int, winpct: string, playoffs: int, div_titles: int, conf_titles: int, ibl_titles: int, heat_titles: int}> $summaryRows
     * @param list<array{currentname: string, five_season_wins: int, five_season_losses: int}> $windowRows
     * @param list<array{team_name: string, total_wins: int, total_losses: int}> $playoffRows
     * @param list<array{currentname: string, total_wins: int|null, total_losses: int|null}> $heatRows
     */
    private function stubRepo(
        array $summaryRows = [],
        array $windowRows = [],
        array $playoffRows = [],
        array $heatRows = [],
    ): void {
        $this->repository->method('getFranchiseSummaryRows')->willReturn($summaryRows);
        $this->repository->method('getFiveSeasonWindowRows')->willReturn($windowRows);
        $this->repository->method('getRawPlayoffTotals')->willReturn($playoffRows);
        $this->repository->method('getRawHeatTotals')->willReturn($heatRows);
    }

    public function testAssemblesMergedRowWithDerivedWinpcts(): void
    {
        $this->stubRepo(
            summaryRows: [$this->summaryRow()],
            windowRows: [['currentname' => 'Metros', 'five_season_wins' => 3, 'five_season_losses' => 2]],
            playoffRows: [['team_name' => 'Metros', 'total_wins' => 12, 'total_losses' => 8]],
            heatRows: [['currentname' => 'Metros', 'total_wins' => 6, 'total_losses' => 4]],
        );

        $result = $this->service->getAllFranchiseHistory(2024);

        self::assertCount(1, $result);
        $row = $result[0];

        // Summary fields passed through verbatim
        self::assertSame(1, $row['teamid']);
        self::assertSame('Metros', $row['team_name']);
        self::assertSame('.667', $row['winpct']);
        self::assertSame(5, $row['playoffs']);

        // Five-season window merged + winpct derived: 3 of 5 = 0.600
        self::assertSame(3, $row['five_season_wins']);
        self::assertSame(2, $row['five_season_losses']);
        self::assertSame(5, $row['totalgames']);
        self::assertSame('0.600', $row['five_season_winpct']);

        // Playoff totals + derived winpct: 12 of 20 = 0.600
        self::assertSame(12, $row['playoff_total_wins']);
        self::assertSame(8, $row['playoff_total_losses']);
        self::assertSame('0.600', $row['playoff_winpct']);

        // HEAT totals + derived winpct: 6 of 10 = 0.600
        self::assertSame(6, $row['heat_total_wins']);
        self::assertSame(4, $row['heat_total_losses']);
        self::assertSame('0.600', $row['heat_winpct']);
    }

    public function testTitlesAreSurfacedInAssembledRow(): void
    {
        $this->stubRepo(
            summaryRows: [$this->summaryRow([
                'heat_titles' => 4,
                'div_titles' => 7,
                'conf_titles' => 3,
                'ibl_titles' => 2,
            ])],
        );

        $row = $this->service->getAllFranchiseHistory(2024)[0];

        self::assertSame(4, $row['heat_titles']);
        self::assertSame(7, $row['div_titles']);
        self::assertSame(3, $row['conf_titles']);
        self::assertSame(2, $row['ibl_titles']);
    }

    public function testTeamAbsentFromPlayoffAndHeatTotalsDefaultsToZero(): void
    {
        // Summary present, but no playoff/heat rows for this team
        $this->stubRepo(
            summaryRows: [$this->summaryRow(['team_name' => 'Sharks'])],
            windowRows: [['currentname' => 'Sharks', 'five_season_wins' => 4, 'five_season_losses' => 1]],
            playoffRows: [],
            heatRows: [],
        );

        $row = $this->service->getAllFranchiseHistory(2024)[0];

        self::assertSame(0, $row['playoff_total_wins']);
        self::assertSame(0, $row['playoff_total_losses']);
        self::assertSame('.000', $row['playoff_winpct']);
        self::assertSame(0, $row['heat_total_wins']);
        self::assertSame(0, $row['heat_total_losses']);
        self::assertSame('.000', $row['heat_winpct']);
    }

    public function testZeroGamesInWindowYieldsNullFiveSeasonWinpct(): void
    {
        // Team present in window map but with zero games → null (distinct from '.000')
        $this->stubRepo(
            summaryRows: [$this->summaryRow()],
            windowRows: [['currentname' => 'Metros', 'five_season_wins' => 0, 'five_season_losses' => 0]],
        );

        $row = $this->service->getAllFranchiseHistory(2024)[0];

        self::assertSame(0, $row['totalgames']);
        self::assertNull($row['five_season_winpct']);
    }

    public function testTeamAbsentFromWindowMapHasZeroFiveSeasonRecord(): void
    {
        // No window row for this team at all
        $this->stubRepo(
            summaryRows: [$this->summaryRow()],
            windowRows: [['currentname' => 'OtherTeam', 'five_season_wins' => 9, 'five_season_losses' => 1]],
        );

        $row = $this->service->getAllFranchiseHistory(2024)[0];

        self::assertSame(0, $row['five_season_wins']);
        self::assertSame(0, $row['five_season_losses']);
        self::assertNull($row['five_season_winpct']);
    }

    public function testEmptyRepositoryReturnsEmptyArray(): void
    {
        $this->stubRepo();

        self::assertSame([], $this->service->getAllFranchiseHistory(2024));
    }

    public function testPreservesSummaryRowOrder(): void
    {
        $this->stubRepo(
            summaryRows: [
                $this->summaryRow(['teamid' => 1, 'team_name' => 'Metros']),
                $this->summaryRow(['teamid' => 2, 'team_name' => 'Sharks']),
                $this->summaryRow(['teamid' => 3, 'team_name' => 'Bears']),
            ],
        );

        $result = $this->service->getAllFranchiseHistory(2024);

        self::assertSame([1, 2, 3], array_column($result, 'teamid'));
    }

    public function testPlayoffTotalsWithZeroGamesYieldZeroWinpct(): void
    {
        // Team present in raw playoff totals but with zero games → '.000' via the
        // buildPlayoffTotals ternary (distinct from the absent-team '??' default).
        $this->stubRepo(
            summaryRows: [$this->summaryRow()],
            playoffRows: [['team_name' => 'Metros', 'total_wins' => 0, 'total_losses' => 0]],
        );

        $row = $this->service->getAllFranchiseHistory(2024)[0];

        self::assertSame(0, $row['playoff_total_wins']);
        self::assertSame(0, $row['playoff_total_losses']);
        self::assertSame('.000', $row['playoff_winpct']);
    }

    public function testHeatTotalsWithNullSumsTreatedAsZero(): void
    {
        // SUM() over no matching rows can surface NULL; Service coalesces to 0
        $this->stubRepo(
            summaryRows: [$this->summaryRow()],
            heatRows: [['currentname' => 'Metros', 'total_wins' => null, 'total_losses' => null]],
        );

        $row = $this->service->getAllFranchiseHistory(2024)[0];

        self::assertSame(0, $row['heat_total_wins']);
        self::assertSame(0, $row['heat_total_losses']);
        self::assertSame('.000', $row['heat_winpct']);
    }
}
