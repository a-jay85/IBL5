<?php

declare(strict_types=1);

namespace Tests\SavedDepthChart;

use SavedDepthChart\SavedDepthChartService;
use SavedDepthChart\Contracts\SavedDepthChartRepositoryInterface;
use Tests\WideUnit\WideUnitTestCase;
use Season\Season;

/**
 * @covers \SavedDepthChart\SavedDepthChartService
 */
class SavedDepthChartServiceTest extends WideUnitTestCase
{
    private SavedDepthChartService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SavedDepthChartService($this->mockDb);
    }

    public function testBuildPlayerSnapshotReturnsCorrectStructure(): void
    {
        $rosterPlayer = ['pid' => 100, 'name' => 'Test Player'];
        $dcSettings = [
            'pg' => 1, 'sg' => 2, 'sf' => 3, 'pf' => 4, 'c' => 5,
            'canPlayInGame' => 1, 'min' => 30, 'of' => 2, 'df' => 3, 'oi' => 1, 'di' => 2, 'bh' => 4,
        ];

        $result = $this->service->buildPlayerSnapshot($rosterPlayer, $dcSettings, 1);

        $this->assertSame(100, $result['pid']);
        $this->assertSame('Test Player', $result['player_name']);
        $this->assertSame(1, $result['ordinal']);
        $this->assertSame(1, $result['dc_pg_depth']);
        $this->assertSame(2, $result['dc_sg_depth']);
        $this->assertSame(3, $result['dc_sf_depth']);
        $this->assertSame(4, $result['dc_pf_depth']);
        $this->assertSame(5, $result['dc_c_depth']);
        $this->assertSame(1, $result['dc_can_play_in_game']);
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
        $this->assertSame(0, $result['dc_pg_depth']);
        $this->assertSame(0, $result['dc_sg_depth']);
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
                'teamid' => 1,
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
                'dc_pg_depth' => 1,
                'dc_sg_depth' => 0,
                'dc_sf_depth' => 0,
                'dc_pf_depth' => 0,
                'dc_c_depth' => 0,
                'dc_can_play_in_game' => 1,
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
                'id' => 1, 'teamid' => 1, 'username' => 'testuser', 'name' => null,
                'phase' => 'Regular Season', 'season_year' => 2024,
                'sim_start_date' => '2024-01-01', 'sim_end_date' => null,
                'sim_number_start' => 1, 'sim_number_end' => null,
                'is_active' => 1, 'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
                'depth_chart_id' => 1, 'pid' => 100, 'player_name' => 'Traded Player',
                'ordinal' => 1, 'dc_pg_depth' => 1, 'dc_sg_depth' => 0,
                'dc_sf_depth' => 0, 'dc_pf_depth' => 0, 'dc_c_depth' => 0,
                'dc_can_play_in_game' => 1, 'dc_minutes' => 30, 'dc_of' => 2,
                'dc_df' => 3, 'dc_oi' => 1, 'dc_di' => 2, 'dc_bh' => 4,
            ],
        ]);

        // Current roster does NOT have pid 100 (traded away), has pid 300 (new)
        $result = $this->service->loadSavedDepthChart(1, 1, [300]);

        $this->assertNotNull($result);
        $this->assertSame([100], $result['tradedPids']);
        $this->assertSame([300], $result['newPlayerPids']);
    }

    // ── saveOnSubmit ─────────────────────────────────────────

    public function testSaveOnSubmitUpdatesExistingDcWhenLoadedDcIdIsPositive(): void
    {
        // getSavedDepthChartById returns existing DC
        $this->mockDb->setMockData([
            [
                'id' => 5, 'teamid' => 1, 'username' => 'testuser', 'name' => 'My DC',
                'phase' => 'Regular Season', 'season_year' => 2024,
                'sim_start_date' => '2024-01-01', 'sim_end_date' => null,
                'sim_number_start' => 1, 'sim_number_end' => null,
                'is_active' => 1, 'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ]);

        $season = new Season($this->mockDb);
        $result = $this->service->saveOnSubmit(1, 'testuser', 'My DC', [], [], 5, $season);

        $this->assertSame(5, $result);
        $this->assertQueryExecuted('ibl_saved_depth_chart_players');
    }

    public function testSaveOnSubmitReactivatesInactiveDc(): void
    {
        // DC exists but is_active = 0
        $this->mockDb->setMockData([
            [
                'id' => 5, 'teamid' => 1, 'username' => 'testuser', 'name' => 'Inactive DC',
                'phase' => 'Regular Season', 'season_year' => 2024,
                'sim_start_date' => '2024-01-01', 'sim_end_date' => null,
                'sim_number_start' => 1, 'sim_number_end' => null,
                'is_active' => 0, 'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ]);

        $season = new Season($this->mockDb);
        $result = $this->service->saveOnSubmit(1, 'testuser', null, [], [], 5, $season);

        $this->assertSame(5, $result);
        // Should have executed reactivate UPDATE
        $this->assertQueryExecuted('ibl_saved_depth_charts');
    }

    public function testSaveOnSubmitReusesUnusedMostRecentDc(): void
    {
        // loadedDcId = 0, so skip first branch
        // getSavedDepthChartById returns null (no loaded DC found)
        // getMostRecentDepthChart returns unused DC (sim_end_date = null)
        $this->mockDb->onQuery('SELECT.*FROM ibl_saved_depth_charts WHERE id', []);
        $this->mockDb->setMockData([
            [
                'id' => 10, 'teamid' => 1, 'username' => 'testuser', 'name' => null,
                'phase' => 'Regular Season', 'season_year' => 2024,
                'sim_start_date' => '2024-01-01', 'sim_end_date' => null,
                'sim_number_start' => 1, 'sim_number_end' => null,
                'is_active' => 0, 'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ]);

        $season = new Season($this->mockDb);
        $result = $this->service->saveOnSubmit(1, 'testuser', null, [], [], 0, $season);

        $this->assertSame(10, $result);
    }

    // ── getWinLossRecord ─────────────────────────────────────────

    public function testGetWinLossRecordReturnsWinsAndLosses(): void
    {
        $this->mockDb->setMockData([
            ['wins' => 10, 'losses' => 5],
        ]);

        $result = $this->service->getWinLossRecord(1, '2024-01-01', '2024-03-31');

        $this->assertSame(['wins' => 10, 'losses' => 5], $result);
    }

    public function testGetWinLossRecordReturnsZerosWhenNoGames(): void
    {
        $this->mockDb->setMockData([
            ['wins' => null, 'losses' => null],
        ]);

        $result = $this->service->getWinLossRecord(1, '2024-01-01', '2024-03-31');

        $this->assertSame(['wins' => 0, 'losses' => 0], $result);
    }

    // ── nameOrCreateActive ─────────────────────────────────────────

    public function testNameOrCreateActiveRenamesExistingActiveDc(): void
    {
        // getActiveDepthChartForTeam returns an active DC
        $this->mockDb->setMockData([
            [
                'id' => 7, 'teamid' => 1, 'username' => 'testuser', 'name' => 'Old Name',
                'phase' => 'Regular Season', 'season_year' => 2024,
                'sim_start_date' => '2024-01-01', 'sim_end_date' => null,
                'sim_number_start' => 1, 'sim_number_end' => null,
                'is_active' => 1, 'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ]);

        $season = new Season($this->mockDb);
        $result = $this->service->nameOrCreateActive(1, 'testuser', 'New Name', $season);

        $this->assertTrue($result['success']);
        $this->assertSame(7, $result['id']);
        $this->assertSame('New Name', $result['name']);
    }

    public function testNameOrCreateActiveReturnsErrorWhenNoPlayersOnRoster(): void
    {
        // getActiveDepthChartForTeam returns null (no active DC)
        // getLiveRosterSettings returns empty (no players)
        $this->mockDb->setMockData([]);

        $season = new Season($this->mockDb);
        $result = $this->service->nameOrCreateActive(1, 'testuser', 'My DC', $season);

        $this->assertFalse($result['success']);
        $this->assertSame('No players found on roster', $result['error']);
    }

    // ── characterization (Phase 1b) ──────────────────────────────────────────

    /** @return array{id:int,teamid:int,username:string,name:string|null,phase:string,season_year:int,sim_start_date:string,sim_end_date:string|null,sim_number_start:int,sim_number_end:int|null,is_active:int,created_at:string,updated_at:string} */
    private function makeActiveDcRow(int $id = 42, string $name = 'Championship DC'): array
    {
        return [
            'id' => $id, 'teamid' => 1, 'username' => 'testuser',
            'name' => $name, 'phase' => 'Regular Season', 'season_year' => 2024,
            'sim_start_date' => '2024-01-01', 'sim_end_date' => null,
            'sim_number_start' => 1, 'sim_number_end' => null, 'is_active' => 1,
            'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00',
        ];
    }

    public function testGetDropdownOptionsOutputUnchangedWithSingleActiveDc(): void
    {
        $activeDcRow = $this->makeActiveDcRow(42);
        $dcPlayerRow = [
            'id' => 1, 'depth_chart_id' => 42, 'pid' => 100, 'player_name' => 'Player One',
            'ordinal' => 1, 'dc_pg_depth' => 1, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0,
            'dc_pf_depth' => 0, 'dc_c_depth' => 0, 'dc_can_play_in_game' => 1,
            'dc_minutes' => 30, 'dc_of' => 1, 'dc_df' => 1, 'dc_oi' => 1, 'dc_di' => 1, 'dc_bh' => 1,
        ];
        $liveRosterRow = [
            'pid' => 200, 'name' => 'Other Player', 'ordinal' => 1,
            'dc_pg_depth' => 0, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0,
            'dc_pf_depth' => 0, 'dc_c_depth' => 0, 'dc_can_play_in_game' => 1,
            'dc_minutes' => 30, 'dc_of' => 1, 'dc_df' => 1, 'dc_oi' => 1, 'dc_di' => 1, 'dc_bh' => 1,
        ];

        $repo = self::createStub(SavedDepthChartRepositoryInterface::class);
        $repo->method('getActiveDepthChartForTeam')->willReturn($activeDcRow);
        $repo->method('getSavedDepthChartsForTeam')->willReturn([$activeDcRow]);
        $repo->method('getPlayersForDepthChart')->willReturn([$dcPlayerRow]);
        $repo->method('getLiveRosterSettings')->willReturn([$liveRosterRow]);
        $repo->method('getWinLossRecord')->willReturn(['wins' => 3, 'losses' => 1]);

        $service = new SavedDepthChartService($this->mockDb, $repo);
        $result = $service->getDropdownOptions(1, new Season($this->mockDb));

        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]['id']);
        $this->assertTrue($result[0]['isActive']);
        $this->assertNotEmpty($result[0]['label']);
    }

    public function testBuildCurrentLiveLabelContainsRecordWithActiveDc(): void
    {
        $activeDcRow = $this->makeActiveDcRow(42, 'Championship DC');

        $repo = self::createStub(SavedDepthChartRepositoryInterface::class);
        $repo->method('getActiveDepthChartForTeam')->willReturn($activeDcRow);
        $repo->method('getWinLossRecord')->willReturn(['wins' => 3, 'losses' => 1]);

        $service = new SavedDepthChartService($this->mockDb, $repo);
        $label = $service->buildCurrentLiveLabel(1, new Season($this->mockDb));

        $this->assertStringContainsString('(3-1)', $label);
        $this->assertStringContainsString('Championship DC', $label);
    }

    public function testBuildCurrentLiveLabelFallsBackWhenNoActiveDc(): void
    {
        $repo = self::createStub(SavedDepthChartRepositoryInterface::class);
        $repo->method('getActiveDepthChartForTeam')->willReturn(null);

        $service = new SavedDepthChartService($this->mockDb, $repo);
        $label = $service->buildCurrentLiveLabel(1, new Season($this->mockDb));

        $this->assertStringContainsString('Current (Live)', $label);
        $this->assertStringContainsString('Sim ', $label);
    }

    // ── memoization + injected-interface seam (Phase 3) ─────────────────────

    public function testActiveDcFetchedOnceForBothDropdownReads(): void
    {
        $activeDcRow = $this->makeActiveDcRow(42);
        $dcPlayerRow = [
            'id' => 1, 'depth_chart_id' => 42, 'pid' => 100, 'player_name' => 'Player One',
            'ordinal' => 1, 'dc_pg_depth' => 1, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0,
            'dc_pf_depth' => 0, 'dc_c_depth' => 0, 'dc_can_play_in_game' => 1,
            'dc_minutes' => 30, 'dc_of' => 1, 'dc_df' => 1, 'dc_oi' => 1, 'dc_di' => 1, 'dc_bh' => 1,
        ];
        $liveRosterRow = [
            'pid' => 200, 'name' => 'Other Player', 'ordinal' => 1,
            'dc_pg_depth' => 0, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0,
            'dc_pf_depth' => 0, 'dc_c_depth' => 0, 'dc_can_play_in_game' => 1,
            'dc_minutes' => 30, 'dc_of' => 1, 'dc_df' => 1, 'dc_oi' => 1, 'dc_di' => 1, 'dc_bh' => 1,
        ];

        $mock = $this->createMock(SavedDepthChartRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('getActiveDepthChartForTeam')
            ->with(1)
            ->willReturn($activeDcRow);
        $mock->method('getSavedDepthChartsForTeam')->willReturn([$activeDcRow]);
        $mock->method('getPlayersForDepthChart')->willReturn([$dcPlayerRow]);
        $mock->method('getLiveRosterSettings')->willReturn([$liveRosterRow]);
        $mock->method('getWinLossRecord')->willReturn(['wins' => 3, 'losses' => 1]);

        $service = new SavedDepthChartService($this->mockDb, $mock);
        $season = new Season($this->mockDb);
        $service->getDropdownOptions(1, $season);
        $service->buildCurrentLiveLabel(1, $season);
    }

    public function testMemoizationIsolatesDistinctTeams(): void
    {
        $dcTeam1 = $this->makeActiveDcRow(101, 'Team 1 DC');
        $dcTeam1['teamid'] = 1;
        $dcTeam2 = $this->makeActiveDcRow(202, 'Team 2 DC');
        $dcTeam2['teamid'] = 2;
        // Different pids in DC vs live → isDepthChartMatchingLive returns false → hideActiveDc = false
        $dcPlayerRow = [
            'id' => 1, 'depth_chart_id' => 0, 'pid' => 100, 'player_name' => 'P1',
            'ordinal' => 1, 'dc_pg_depth' => 1, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0,
            'dc_pf_depth' => 0, 'dc_c_depth' => 0, 'dc_can_play_in_game' => 1,
            'dc_minutes' => 30, 'dc_of' => 1, 'dc_df' => 1, 'dc_oi' => 1, 'dc_di' => 1, 'dc_bh' => 1,
        ];
        $liveRosterRow = [
            'pid' => 200, 'name' => 'P2', 'ordinal' => 1,
            'dc_pg_depth' => 0, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0,
            'dc_pf_depth' => 0, 'dc_c_depth' => 0, 'dc_can_play_in_game' => 1,
            'dc_minutes' => 30, 'dc_of' => 1, 'dc_df' => 1, 'dc_oi' => 1, 'dc_di' => 1, 'dc_bh' => 1,
        ];

        $mock = self::createStub(SavedDepthChartRepositoryInterface::class);
        $mock->method('getActiveDepthChartForTeam')->willReturnMap([
            [1, $dcTeam1],
            [2, $dcTeam2],
        ]);
        $mock->method('getSavedDepthChartsForTeam')->willReturnMap([
            [1, [$dcTeam1]],
            [2, [$dcTeam2]],
        ]);
        $mock->method('getPlayersForDepthChart')->willReturn([$dcPlayerRow]);
        $mock->method('getLiveRosterSettings')->willReturn([$liveRosterRow]);
        $mock->method('getWinLossRecord')->willReturn(['wins' => 0, 'losses' => 0]);

        $service = new SavedDepthChartService($this->mockDb, $mock);
        $season = new Season($this->mockDb);

        $resultTeam1 = $service->getDropdownOptions(1, $season);
        $resultTeam2 = $service->getDropdownOptions(2, $season);

        $ids1 = array_column($resultTeam1, 'id');
        $ids2 = array_column($resultTeam2, 'id');

        $this->assertContains(101, $ids1);
        $this->assertNotContains(202, $ids1);
        $this->assertContains(202, $ids2);
        $this->assertNotContains(101, $ids2);
    }

    public function testActiveDcCacheInvalidatedAfterRename(): void
    {
        $activeDcRow = $this->makeActiveDcRow(42);

        $mock = $this->createMock(SavedDepthChartRepositoryInterface::class);
        $mock->expects($this->exactly(2))
            ->method('getActiveDepthChartForTeam')
            ->with(1)
            ->willReturn($activeDcRow);
        $mock->method('updateName')->willReturn(true);
        $mock->method('deactivateOthersForTeam');
        $mock->method('getWinLossRecord')->willReturn(['wins' => 2, 'losses' => 0]);

        $service = new SavedDepthChartService($this->mockDb, $mock);
        $season = new Season($this->mockDb);

        $service->nameOrCreateActive(1, 'gm', 'New Name', $season);
        $service->buildCurrentLiveLabel(1, $season);
    }

}
