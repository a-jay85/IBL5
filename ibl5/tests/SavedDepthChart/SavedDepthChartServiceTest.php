<?php

declare(strict_types=1);

namespace Tests\SavedDepthChart;

use SavedDepthChart\Contracts\SavedDepthChartServiceInterface;
use SavedDepthChart\SavedDepthChartService;
use Tests\Integration\IntegrationTestCase;

/**
 * @covers \SavedDepthChart\SavedDepthChartService
 */
class SavedDepthChartServiceTest extends IntegrationTestCase
{
    private SavedDepthChartService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SavedDepthChartService($this->mockDb);
    }

    public function testImplementsServiceInterface(): void
    {
        $this->assertInstanceOf(SavedDepthChartServiceInterface::class, $this->service);
    }

    public function testBuildPlayerSnapshotReturnsCorrectStructure(): void
    {
        $rosterPlayer = ['pid' => 100, 'name' => 'Test Player'];
        $dcSettings = [
            'pg' => 1, 'sg' => 2, 'sf' => 3, 'pf' => 4, 'c' => 5,
            'active' => 1, 'min' => 30, 'of' => 2, 'df' => 3, 'oi' => 1, 'di' => 2, 'bh' => 4,
        ];

        $result = $this->service->buildPlayerSnapshot($rosterPlayer, $dcSettings, 1);

        $this->assertSame(100, $result['pid']);
        $this->assertSame('Test Player', $result['player_name']);
        $this->assertSame(1, $result['ordinal']);
        $this->assertSame(1, $result['dc_PGDepth']);
        $this->assertSame(2, $result['dc_SGDepth']);
        $this->assertSame(3, $result['dc_SFDepth']);
        $this->assertSame(4, $result['dc_PFDepth']);
        $this->assertSame(5, $result['dc_CDepth']);
        $this->assertSame(1, $result['dc_active']);
        $this->assertSame(30, $result['dc_minutes']);
        $this->assertSame(2, $result['dc_of']);
        $this->assertSame(3, $result['dc_df']);
        $this->assertSame(1, $result['dc_oi']);
        $this->assertSame(2, $result['dc_di']);
        $this->assertSame(4, $result['dc_bh']);
    }

    public function testBuildPlayerSnapshotHandlesMissingSettings(): void
    {
        $rosterPlayer = ['pid' => 200, 'name' => 'Another Player'];
        $dcSettings = [];

        $result = $this->service->buildPlayerSnapshot($rosterPlayer, $dcSettings, 5);

        $this->assertSame(200, $result['pid']);
        $this->assertSame('Another Player', $result['player_name']);
        $this->assertSame(5, $result['ordinal']);
        $this->assertSame(0, $result['dc_PGDepth']);
        $this->assertSame(0, $result['dc_SGDepth']);
        $this->assertSame(0, $result['dc_minutes']);
        $this->assertSame(0, $result['dc_bh']);
    }

    public function testBuildPlayerSnapshotHandlesStringPid(): void
    {
        $rosterPlayer = ['pid' => '300', 'name' => 'String PID Player'];
        $dcSettings = ['pg' => 1];

        $result = $this->service->buildPlayerSnapshot($rosterPlayer, $dcSettings, 1);

        $this->assertSame(300, $result['pid']);
    }

    public function testBuildPlayerSnapshotHandlesMissingPidDefaultsToZero(): void
    {
        $rosterPlayer = ['name' => 'No PID Player'];
        $dcSettings = [];

        $result = $this->service->buildPlayerSnapshot($rosterPlayer, $dcSettings, 1);

        $this->assertSame(0, $result['pid']);
    }

    public function testBuildPlayerSnapshotHandlesMissingNameDefaultsToEmpty(): void
    {
        $rosterPlayer = ['pid' => 400];
        $dcSettings = [];

        $result = $this->service->buildPlayerSnapshot($rosterPlayer, $dcSettings, 1);

        $this->assertSame('', $result['player_name']);
    }

    public function testLoadSavedDepthChartReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->service->loadSavedDepthChart(999, 1, [100, 200]);

        $this->assertNull($result);
    }

    public function testLoadSavedDepthChartReturnsDataWithRosterComparison(): void
    {
        // Mock data must satisfy both getSavedDepthChartById (fetchOne) and getPlayersForDepthChart (fetchAll)
        $this->mockDb->setMockData([
            [
                // SavedDepthChartRow fields
                'id' => 1,
                'tid' => 1,
                'username' => 'testuser',
                'name' => 'Test DC',
                'phase' => 'Regular Season',
                'season_year' => 2024,
                'sim_start_date' => '2024-01-01',
                'sim_end_date' => '2024-01-15',
                'sim_number_start' => 1,
                'sim_number_end' => 3,
                'is_active' => 1,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-15 00:00:00',
                // SavedDepthChartPlayerRow fields
                'depth_chart_id' => 1,
                'pid' => 100,
                'player_name' => 'Player One',
                'ordinal' => 1,
                'dc_PGDepth' => 1,
                'dc_SGDepth' => 0,
                'dc_SFDepth' => 0,
                'dc_PFDepth' => 0,
                'dc_CDepth' => 0,
                'dc_active' => 1,
                'dc_minutes' => 30,
                'dc_of' => 2,
                'dc_df' => 3,
                'dc_oi' => 1,
                'dc_di' => 2,
                'dc_bh' => 4,
            ],
        ]);

        // Current roster has pid 100 (still on team) and pid 200 (new player)
        $result = $this->service->loadSavedDepthChart(1, 1, [100, 200]);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['depthChart']['id']);
        $this->assertSame('Test DC', $result['depthChart']['name']);
        $this->assertCount(1, $result['players']);
        $this->assertSame(100, $result['players'][0]['pid']);
        $this->assertSame([], $result['tradedPids']);
        $this->assertSame([200], $result['newPlayerPids']);
    }

    public function testLoadSavedDepthChartIdentifiesTradedPlayers(): void
    {
        $this->mockDb->setMockData([
            [
                'id' => 1, 'tid' => 1, 'username' => 'testuser', 'name' => null,
                'phase' => 'Regular Season', 'season_year' => 2024,
                'sim_start_date' => '2024-01-01', 'sim_end_date' => null,
                'sim_number_start' => 1, 'sim_number_end' => null,
                'is_active' => 1, 'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
                'depth_chart_id' => 1, 'pid' => 100, 'player_name' => 'Traded Player',
                'ordinal' => 1, 'dc_PGDepth' => 1, 'dc_SGDepth' => 0,
                'dc_SFDepth' => 0, 'dc_PFDepth' => 0, 'dc_CDepth' => 0,
                'dc_active' => 1, 'dc_minutes' => 30, 'dc_of' => 2,
                'dc_df' => 3, 'dc_oi' => 1, 'dc_di' => 2, 'dc_bh' => 4,
            ],
        ]);

        // Current roster does NOT have pid 100 (traded away), has pid 300 (new)
        $result = $this->service->loadSavedDepthChart(1, 1, [300]);

        $this->assertNotNull($result);
        $this->assertSame([100], $result['tradedPids']);
        $this->assertSame([300], $result['newPlayerPids']);
    }
}
