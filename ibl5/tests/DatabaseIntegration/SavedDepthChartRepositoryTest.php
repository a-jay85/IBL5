<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use SavedDepthChart\SavedDepthChartRepository;

class SavedDepthChartRepositoryTest extends DatabaseTestCase
{
    private SavedDepthChartRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SavedDepthChartRepository($this->db);
    }

    public function testCreateSavedDepthChartReturnsInsertId(): void
    {
        $id = $this->repo->createSavedDepthChart(
            1, 'testgm', 'My DC', 'Regular Season', 2024, '2024-01-15', 10
        );

        self::assertGreaterThan(0, $id);
    }

    public function testCreateSavedDepthChartWithoutNameSucceeds(): void
    {
        $id = $this->repo->createSavedDepthChart(
            1, 'testgm', null, 'Regular Season', 2024, '2024-01-15', 10
        );

        self::assertGreaterThan(0, $id);

        $chart = $this->repo->getSavedDepthChartById($id, 1);
        self::assertNotNull($chart);
        self::assertNull($chart['name']);
    }

    public function testGetSavedDepthChartsForTeamReturnsRows(): void
    {
        $this->repo->createSavedDepthChart(1, 'testgm', 'DC One', 'Regular Season', 2024, '2024-01-15', 10);
        $this->repo->createSavedDepthChart(1, 'testgm', 'DC Two', 'Regular Season', 2024, '2024-02-15', 20);

        $charts = $this->repo->getSavedDepthChartsForTeam(1);

        self::assertGreaterThanOrEqual(2, count($charts));
        // Ordered by created_at DESC — most recent first
        self::assertSame('DC Two', $charts[0]['name']);
    }

    public function testGetSavedDepthChartByIdReturnsRow(): void
    {
        $id = $this->repo->createSavedDepthChart(1, 'testgm', 'Test DC', 'Regular Season', 2024, '2024-01-15', 10);

        $chart = $this->repo->getSavedDepthChartById($id, 1);

        self::assertNotNull($chart);
        self::assertSame('Test DC', $chart['name']);
        self::assertSame(1, $chart['tid']);
        self::assertSame('testgm', $chart['username']);
    }

    public function testGetSavedDepthChartByIdReturnsNullForWrongTeam(): void
    {
        $id = $this->repo->createSavedDepthChart(1, 'testgm', 'Test DC', 'Regular Season', 2024, '2024-01-15', 10);

        $chart = $this->repo->getSavedDepthChartById($id, 2);

        self::assertNull($chart);
    }

    public function testSaveDepthChartPlayersInsertsRows(): void
    {
        $dcId = $this->repo->createSavedDepthChart(1, 'testgm', 'DC', 'Regular Season', 2024, '2024-01-15', 10);

        $snapshots = [
            [
                'pid' => 1,
                'player_name' => 'Test Player One',
                'ordinal' => 1,
                'dc_PGDepth' => 1,
                'dc_SGDepth' => 0,
                'dc_SFDepth' => 0,
                'dc_PFDepth' => 0,
                'dc_CDepth' => 0,
                'dc_canPlayInGame' => 1,
                'dc_minutes' => 32,
                'dc_of' => 5,
                'dc_df' => 5,
                'dc_oi' => 3,
                'dc_di' => 3,
                'dc_bh' => 4,
            ],
        ];

        $this->repo->saveDepthChartPlayers($dcId, $snapshots);

        $players = $this->repo->getPlayersForDepthChart($dcId);

        self::assertCount(1, $players);
        self::assertSame(1, $players[0]['pid']);
        self::assertSame('Test Player One', $players[0]['player_name']);
        self::assertSame(32, $players[0]['dc_minutes']);
    }

    public function testUpdateDepthChartPlayersReplacesRows(): void
    {
        $dcId = $this->repo->createSavedDepthChart(1, 'testgm', 'DC', 'Regular Season', 2024, '2024-01-15', 10);

        $original = [
            [
                'pid' => 1, 'player_name' => 'Test Player One', 'ordinal' => 1,
                'dc_PGDepth' => 1, 'dc_SGDepth' => 0, 'dc_SFDepth' => 0, 'dc_PFDepth' => 0, 'dc_CDepth' => 0,
                'dc_canPlayInGame' => 1, 'dc_minutes' => 32, 'dc_of' => 5, 'dc_df' => 5, 'dc_oi' => 3, 'dc_di' => 3, 'dc_bh' => 4,
            ],
        ];
        $this->repo->saveDepthChartPlayers($dcId, $original);

        $updated = [
            [
                'pid' => 1, 'player_name' => 'Test Player One', 'ordinal' => 1,
                'dc_PGDepth' => 1, 'dc_SGDepth' => 2, 'dc_SFDepth' => 0, 'dc_PFDepth' => 0, 'dc_CDepth' => 0,
                'dc_canPlayInGame' => 1, 'dc_minutes' => 36, 'dc_of' => 6, 'dc_df' => 4, 'dc_oi' => 3, 'dc_di' => 3, 'dc_bh' => 4,
            ],
        ];
        $this->repo->updateDepthChartPlayers($dcId, $updated);

        $players = $this->repo->getPlayersForDepthChart($dcId);

        self::assertCount(1, $players);
        self::assertSame(36, $players[0]['dc_minutes']);
        self::assertSame(2, $players[0]['dc_SGDepth']);
    }

    public function testDeactivateForTeamSetsInactive(): void
    {
        $id = $this->repo->createSavedDepthChart(1, 'testgm', 'DC', 'Regular Season', 2024, '2024-01-15', 10);

        $chart = $this->repo->getSavedDepthChartById($id, 1);
        self::assertNotNull($chart);
        self::assertSame(1, $chart['is_active']);

        $this->repo->deactivateForTeam(1, '2024-02-15', 20);

        $chart = $this->repo->getSavedDepthChartById($id, 1);
        self::assertNotNull($chart);
        self::assertSame(0, $chart['is_active']);
    }

    public function testDeactivateOthersForTeamKeepsOneActive(): void
    {
        $id1 = $this->repo->createSavedDepthChart(1, 'testgm', 'DC One', 'Regular Season', 2024, '2024-01-15', 10);
        $id2 = $this->repo->createSavedDepthChart(1, 'testgm', 'DC Two', 'Regular Season', 2024, '2024-02-15', 20);

        $this->repo->deactivateOthersForTeam(1, $id2, '2024-03-15', 30);

        $chart1 = $this->repo->getSavedDepthChartById($id1, 1);
        $chart2 = $this->repo->getSavedDepthChartById($id2, 1);

        self::assertNotNull($chart1);
        self::assertNotNull($chart2);
        self::assertSame(0, $chart1['is_active']);
        self::assertSame(1, $chart2['is_active']);
    }

    public function testUpdateNameChangesName(): void
    {
        $id = $this->repo->createSavedDepthChart(1, 'testgm', 'Old Name', 'Regular Season', 2024, '2024-01-15', 10);

        $result = $this->repo->updateName($id, 1, 'New Name');

        self::assertTrue($result);

        $chart = $this->repo->getSavedDepthChartById($id, 1);
        self::assertNotNull($chart);
        self::assertSame('New Name', $chart['name']);
    }

    public function testUpdateNameReturnsFalseForWrongTeam(): void
    {
        $id = $this->repo->createSavedDepthChart(1, 'testgm', 'Name', 'Regular Season', 2024, '2024-01-15', 10);

        $result = $this->repo->updateName($id, 2, 'New Name');

        self::assertFalse($result);
    }

    public function testGetMostRecentDepthChartReturnsLatest(): void
    {
        $this->repo->createSavedDepthChart(1, 'testgm', 'DC Old', 'Regular Season', 2024, '2024-01-15', 10);
        $id2 = $this->repo->createSavedDepthChart(1, 'testgm', 'DC New', 'Regular Season', 2024, '2024-02-15', 20);

        $latest = $this->repo->getMostRecentDepthChart(1);

        self::assertNotNull($latest);
        self::assertSame($id2, $latest['id']);
        self::assertSame('DC New', $latest['name']);
    }

    public function testGetActiveDepthChartForTeamReturnsActive(): void
    {
        $id = $this->repo->createSavedDepthChart(1, 'testgm', 'Active DC', 'Regular Season', 2024, '2024-01-15', 10);

        $active = $this->repo->getActiveDepthChartForTeam(1);

        self::assertNotNull($active);
        self::assertSame($id, $active['id']);
        self::assertSame(1, $active['is_active']);
    }

    public function testGetActiveDepthChartReturnsNullWhenAllInactive(): void
    {
        $this->repo->createSavedDepthChart(1, 'testgm', 'DC', 'Regular Season', 2024, '2024-01-15', 10);
        $this->repo->deactivateForTeam(1, '2024-02-15', 20);

        $active = $this->repo->getActiveDepthChartForTeam(1);

        self::assertNull($active);
    }

    public function testExtendActiveDepthChartsUpdatesExpiry(): void
    {
        $id = $this->repo->createSavedDepthChart(1, 'testgm', 'DC', 'Regular Season', 2024, '2024-01-15', 10);

        $affected = $this->repo->extendActiveDepthCharts('2024-03-01', 30);

        self::assertGreaterThan(0, $affected);

        $chart = $this->repo->getSavedDepthChartById($id, 1);
        self::assertNotNull($chart);
        self::assertSame('2024-03-01', $chart['sim_end_date']);
        self::assertSame(30, $chart['sim_number_end']);
    }

    public function testReactivateSetsActive(): void
    {
        $id = $this->repo->createSavedDepthChart(1, 'testgm', 'DC', 'Regular Season', 2024, '2024-01-15', 10);
        $this->repo->deactivateForTeam(1, '2024-02-15', 20);

        $result = $this->repo->reactivate($id, 1);

        self::assertTrue($result);

        $chart = $this->repo->getSavedDepthChartById($id, 1);
        self::assertNotNull($chart);
        self::assertSame(1, $chart['is_active']);
    }

    public function testReactivateReturnsFalseForWrongTeam(): void
    {
        $id = $this->repo->createSavedDepthChart(1, 'testgm', 'DC', 'Regular Season', 2024, '2024-01-15', 10);
        $this->repo->deactivateForTeam(1, '2024-02-15', 20);

        $result = $this->repo->reactivate($id, 2);

        self::assertFalse($result);
    }
}
