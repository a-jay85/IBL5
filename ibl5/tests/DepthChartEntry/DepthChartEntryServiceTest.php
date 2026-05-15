<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryService;

class DepthChartEntryServiceTest extends TestCase
{
    private DepthChartEntryService $service;

    protected function setUp(): void
    {
        $this->service = new DepthChartEntryService();
    }

    // ============================================
    // computeQualityScore()
    // ============================================

    public function testComputeQualityScoreReturnsZeroForZeroGames(): void
    {
        $player = ['stats_gm' => 0];

        $this->assertSame(0.0, $this->service->computeQualityScore($player));
    }

    public function testComputeQualityScoreTypicalPlayer(): void
    {
        $player = [
            'stats_gm' => 40,
            'stats_gs' => 38,
            'stats_min' => 1200,
            'stats_fgm' => 300,
            'stats_fga' => 600,
            'stats_ftm' => 100,
            'stats_fta' => 120,
            'stats_3gm' => 60,
            'stats_3ga' => 150,
            'stats_orb' => 40,
            'stats_drb' => 200,
            'stats_ast' => 160,
            'stats_stl' => 50,
            'stats_tvr' => 80,
            'stats_blk' => 30,
            'od' => 7,
            'dd' => 6,
            'pd' => 5,
            'td' => 8,
        ];

        $this->assertSame(78.43, $this->service->computeQualityScore($player));
    }

    public function testComputeQualityScoreMinimalPlayer(): void
    {
        $player = [
            'stats_gm' => 10,
            'stats_gs' => 0,
            'stats_min' => 100,
            'stats_fgm' => 20,
            'stats_fga' => 50,
            'stats_ftm' => 10,
            'stats_fta' => 15,
            'stats_3gm' => 5,
            'stats_3ga' => 15,
            'stats_orb' => 5,
            'stats_drb' => 20,
            'stats_ast' => 15,
            'stats_stl' => 5,
            'stats_tvr' => 10,
            'stats_blk' => 3,
            'od' => 5,
            'dd' => 5,
            'pd' => 5,
            'td' => 5,
        ];

        $this->assertSame(26.5, $this->service->computeQualityScore($player));
    }

    public function testComputeQualityScoreDefaultRatings(): void
    {
        $player = [
            'stats_gm' => 20,
            'stats_gs' => 10,
            'stats_min' => 400,
            'stats_fgm' => 80,
            'stats_fga' => 180,
            'stats_ftm' => 30,
            'stats_fta' => 40,
            'stats_3gm' => 20,
            'stats_3ga' => 50,
            'stats_orb' => 15,
            'stats_drb' => 60,
            'stats_ast' => 40,
            'stats_stl' => 15,
            'stats_tvr' => 25,
            'stats_blk' => 10,
        ];

        $this->assertSame(51.64, $this->service->computeQualityScore($player));
    }

    public function testComputeQualityScoreEmptyPlayer(): void
    {
        $this->assertSame(0.0, $this->service->computeQualityScore([]));
    }

    // ============================================
    // buildFormOverride()
    // ============================================

    public function testBuildFormOverrideMapsPidToSubmittedValues(): void
    {
        $postData = [
            'pid1' => '42',
            'pg1' => '1',
            'sg1' => '2',
            'sf1' => '3',
            'pf1' => '4',
            'c1' => '5',
            'canPlayInGame1' => '1',
            'min1' => '32',
        ];

        $override = $this->service->buildFormOverride($postData);

        $this->assertArrayHasKey(42, $override);
        $this->assertSame(
            [
                'dc_pg_depth' => 1,
                'dc_sg_depth' => 2,
                'dc_sf_depth' => 3,
                'dc_pf_depth' => 4,
                'dc_c_depth' => 5,
                'dc_can_play_in_game' => 1,
                'dc_minutes' => 32,
            ],
            $override[42],
        );
    }

    public function testBuildFormOverrideClampsOutOfRangeDepth(): void
    {
        $postData = [
            'pid1' => '7',
            'pg1' => '-3',
            'sg1' => '99',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '0',
            'min1' => '12',
        ];

        $override = $this->service->buildFormOverride($postData);

        $this->assertSame(0, $override[7]['dc_pg_depth'], 'depth < 0 clamps to 0');
        $this->assertSame(5, $override[7]['dc_sg_depth'], 'depth > 5 clamps to 5');
    }

    public function testBuildFormOverrideClampsMinutesTo0_40(): void
    {
        $postData = [
            'pid1' => '7',
            'pg1' => '0', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'canPlayInGame1' => '0',
            'min1' => '99',
            'pid2' => '8',
            'pg2' => '0', 'sg2' => '0', 'sf2' => '0', 'pf2' => '0', 'c2' => '0',
            'canPlayInGame2' => '0',
            'min2' => '-5',
        ];

        $override = $this->service->buildFormOverride($postData);

        $this->assertSame(40, $override[7]['dc_minutes'], 'minutes > 40 clamps to 40');
        $this->assertSame(0, $override[8]['dc_minutes'], 'minutes < 0 clamps to 0');
    }

    public function testBuildFormOverrideTreatsActiveFlagAsBoolean(): void
    {
        $postDataActive = [
            'pid1' => '7',
            'pg1' => '0', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '0',
        ];
        $postDataInactive = [
            'pid1' => '7',
            'pg1' => '0', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'canPlayInGame1' => '0',
            'min1' => '0',
        ];

        $active = $this->service->buildFormOverride($postDataActive);
        $inactive = $this->service->buildFormOverride($postDataInactive);

        $this->assertSame(1, $active[7]['dc_can_play_in_game']);
        $this->assertSame(0, $inactive[7]['dc_can_play_in_game']);
    }

    public function testBuildFormOverrideSkipsRowsWithoutPid(): void
    {
        $postData = [
            'Team_Name' => 'Metros',
            'pg1' => '3',
        ];

        $override = $this->service->buildFormOverride($postData);

        $this->assertSame([], $override);
    }

    public function testBuildFormOverrideSkipsRowsWithZeroOrInvalidPid(): void
    {
        $postData = [
            'pid1' => '0',
            'pg1' => '1',
            'pid2' => 'abc',
            'pg2' => '2',
        ];

        $override = $this->service->buildFormOverride($postData);

        $this->assertSame([], $override);
    }

    public function testBuildFormOverrideHandlesMultipleRows(): void
    {
        $postData = [
            'pid1' => '100', 'pg1' => '1', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'canPlayInGame1' => '1', 'min1' => '30',
            'pid2' => '200', 'pg2' => '0', 'sg2' => '2', 'sf2' => '0', 'pf2' => '0', 'c2' => '0',
            'canPlayInGame2' => '1', 'min2' => '25',
            'pid3' => '300', 'pg3' => '0', 'sg3' => '0', 'sf3' => '3', 'pf3' => '0', 'c3' => '0',
            'canPlayInGame3' => '0', 'min3' => '0',
        ];

        $override = $this->service->buildFormOverride($postData);

        $this->assertSame([100, 200, 300], array_keys($override));
        $this->assertSame(1, $override[100]['dc_pg_depth']);
        $this->assertSame(2, $override[200]['dc_sg_depth']);
        $this->assertSame(3, $override[300]['dc_sf_depth']);
        $this->assertSame(0, $override[300]['dc_can_play_in_game']);
    }

    // ============================================
    // Interface compliance
    // ============================================

    public function testServiceImplementsInterface(): void
    {
        $this->assertInstanceOf(
            \DepthChartEntry\Contracts\DepthChartEntryServiceInterface::class,
            $this->service,
        );
    }
}
