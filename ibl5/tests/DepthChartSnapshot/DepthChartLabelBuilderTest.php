<?php

declare(strict_types=1);

namespace Tests\DepthChartSnapshot;

use DepthChartSnapshot\DepthChartLabelBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Season\Season;

/**
 * @covers \DepthChartSnapshot\DepthChartLabelBuilder
 */
class DepthChartLabelBuilderTest extends TestCase
{
    private const ENDED = [
        'name' => 'Playoff Push',
        'id' => 7,
        'sim_end_date' => '2024-01-14',
        'sim_number_start' => 3,
        'sim_number_end' => 5,
        'is_active' => 0,
    ];

    private DepthChartLabelBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new DepthChartLabelBuilder();
    }

    private function makeSeasonStub(): Season
    {
        $season = self::createStub(Season::class);
        $season->phase = 'Regular Season';
        $season->lastSimNumber = 4;
        $season->lastSimStartDate = '2024-01-15';
        $season->lastSimEndDate = '2024-01-20';
        $season->projectedNextSimEndDate = new \DateTimeImmutable('2024-01-27');
        $season->method('getPhaseSpecificSimNumber')->willReturn(4);
        // Identity mapping: overall sim number == phase sim number, so expected strings stay readable.
        $season->method('calculatePhaseSimNumber')
            ->willReturnCallback(static fn (int $n, string $phase, int $year): int => $n);
        return $season;
    }

    /**
     * @param array{id?:int,name?:string|null,sim_start_date?:string,sim_end_date?:string|null,sim_number_start?:int,sim_number_end?:int|null,is_active?:int} $overrides
     * @return array{id:int,teamid:int,username:string,name:string|null,phase:string,season_year:int,sim_start_date:string,sim_end_date:string|null,sim_number_start:int,sim_number_end:int|null,is_active:int,created_at:string,updated_at:string}
     */
    private function makeDcRow(array $overrides = []): array
    {
        $row = [
            'id' => 42, 'teamid' => 1, 'username' => 'testuser',
            'name' => 'Championship DC', 'phase' => 'Regular Season', 'season_year' => 2024,
            'sim_start_date' => '2024-01-01', 'sim_end_date' => null,
            'sim_number_start' => 1, 'sim_number_end' => null, 'is_active' => 1,
            'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00',
        ];

        return array_merge($row, $overrides);
    }

    public function testBuildDropdownLabelNamedEndedDc(): void
    {
        $this->assertSame(
            'Playoff Push | Sims 3-5 | Jan 1 - Jan 14 | (5-2)',
            $this->builder->buildDropdownLabel($this->makeDcRow(self::ENDED), $this->makeSeasonStub(), ['wins' => 5, 'losses' => 2])
        );
    }

    #[DataProvider('unusableNameProvider')]
    public function testBuildDropdownLabelOmitsUnusableName(?string $name): void
    {
        $dc = $this->makeDcRow(array_merge(self::ENDED, ['name' => $name]));

        $this->assertSame(
            'Sims 3-5 | Jan 1 - Jan 14 | (5-2)',
            $this->builder->buildDropdownLabel($dc, $this->makeSeasonStub(), ['wins' => 5, 'losses' => 2])
        );
    }

    /** @return array<string, array{string|null}> */
    public static function unusableNameProvider(): array
    {
        return [
            'null name' => [null],
            'empty name' => [''],
        ];
    }

    public function testBuildDropdownLabelOpenDcShowsQuestionMark(): void
    {
        $dc = $this->makeDcRow(array_merge(self::ENDED, ['sim_end_date' => null, 'sim_number_end' => null]));

        $this->assertSame(
            'Playoff Push | Sim 3 | Jan 1 - ? | (5-2)',
            $this->builder->buildDropdownLabel($dc, $this->makeSeasonStub(), ['wins' => 5, 'losses' => 2])
        );
    }

    public function testBuildDropdownLabelSingularSimWhenRangeCollapses(): void
    {
        $dc = $this->makeDcRow(array_merge(self::ENDED, ['sim_number_end' => 3]));

        $this->assertSame(
            'Playoff Push | Sim 3 | Jan 1 - Jan 14 | (5-2)',
            $this->builder->buildDropdownLabel($dc, $this->makeSeasonStub(), ['wins' => 5, 'losses' => 2])
        );
    }

    public function testBuildDropdownLabelUsesPhaseMappedSimNumbers(): void
    {
        $season = self::createStub(Season::class);
        $season->phase = 'Regular Season';
        $season->lastSimNumber = 4;
        $season->lastSimStartDate = '2024-01-15';
        $season->lastSimEndDate = '2024-01-20';
        $season->projectedNextSimEndDate = new \DateTimeImmutable('2024-01-27');
        $season->method('getPhaseSpecificSimNumber')->willReturn(4);
        $season->method('calculatePhaseSimNumber')->willReturnMap([
            [3, 'Regular Season', 2024, 1],
            [5, 'Regular Season', 2024, 3],
        ]);

        $this->assertSame(
            'Playoff Push | Sims 1-3 | Jan 1 - Jan 14 | (5-2)',
            $this->builder->buildDropdownLabel($this->makeDcRow(self::ENDED), $season, ['wins' => 5, 'losses' => 2])
        );
    }

    public function testBuildLiveLabelNamedActiveDcWithRecord(): void
    {
        $this->assertSame(
            'Championship DC (Live) ∙ Sims 1-4 ∙ Jan 1 - Jan 20 ∙ (3-1)',
            $this->builder->buildLiveLabel($this->makeDcRow(), $this->makeSeasonStub(), ['wins' => 3, 'losses' => 1])
        );
    }

    #[DataProvider('unusableNameProvider')]
    public function testBuildLiveLabelUnnamedActiveDcUsesCurrent(?string $name): void
    {
        $this->assertSame(
            'Current (Live) ∙ Sims 1-4 ∙ Jan 1 - Jan 20 ∙ (3-1)',
            $this->builder->buildLiveLabel($this->makeDcRow(['name' => $name]), $this->makeSeasonStub(), ['wins' => 3, 'losses' => 1])
        );
    }

    #[DataProvider('liveEndDateBranchProvider')]
    public function testBuildLiveLabelEndDateBranch(string $simStartDate, string $expected): void
    {
        $dc = $this->makeDcRow(['name' => 'Next Up', 'sim_number_start' => 4, 'sim_start_date' => $simStartDate]);

        $this->assertSame(
            $expected,
            $this->builder->buildLiveLabel($dc, $this->makeSeasonStub(), ['wins' => 0, 'losses' => 0])
        );
    }

    /** @return array<string, array{string, string}> */
    public static function liveEndDateBranchProvider(): array
    {
        return [
            'starts after last sim end: projected end' => ['2024-01-21', 'Next Up (Live) ∙ Sim 4 ∙ Jan 21 - Jan 27 ∙ (0-0)'],
            'starts on last sim end: boundary keeps last sim end' => ['2024-01-20', 'Next Up (Live) ∙ Sim 4 ∙ Jan 20 - Jan 20 ∙ (0-0)'],
        ];
    }

    public function testBuildLiveLabelWithoutActiveDc(): void
    {
        $this->assertSame(
            'Current (Live) ∙ Sim 4 ∙ Jan 15 - Jan 20',
            $this->builder->buildLiveLabel(null, $this->makeSeasonStub(), null)
        );
    }

    public function testBuildLiveLabelOmitsRecordWhenRecordNull(): void
    {
        $this->assertSame(
            'Championship DC (Live) ∙ Sims 1-4 ∙ Jan 1 - Jan 20',
            $this->builder->buildLiveLabel($this->makeDcRow(), $this->makeSeasonStub(), null)
        );
    }
}
