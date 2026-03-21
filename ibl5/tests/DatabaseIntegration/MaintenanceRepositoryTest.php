<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Scripts\MaintenanceRepository;

/**
 * Tests MaintenanceRepository against real MariaDB — team listings,
 * tradition updates, and settings lookups used by maintenance scripts.
 */
class MaintenanceRepositoryTest extends DatabaseTestCase
{
    private MaintenanceRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MaintenanceRepository($this->db);
    }

    // ── getAllTeams ──────────────────────────────────────────────

    public function testGetAllTeamsReturns28TeamNames(): void
    {
        $teams = $this->repo->getAllTeams();

        self::assertCount(28, $teams);
        self::assertArrayHasKey('team_name', $teams[0]);
        self::assertIsString($teams[0]['team_name']);
    }

    // ── getTeamRecentCompleteSeasons ─────────────────────────────

    public function testGetTeamRecentCompleteSeasonsReturnsArray(): void
    {
        // View ibl_team_win_loss is derived from ibl_box_scores_teams — may be
        // empty in CI (needs 82-game seasons). Verify structure, not specific data.
        $seasons = $this->repo->getTeamRecentCompleteSeasons('Metros');

        self::assertIsArray($seasons);
        if ($seasons !== []) {
            self::assertArrayHasKey('wins', $seasons[0]);
            self::assertArrayHasKey('losses', $seasons[0]);
        }
    }

    // ── updateTeamTradition ─────────────────────────────────────

    public function testUpdateTeamTraditionSetsContractFields(): void
    {
        $result = $this->repo->updateTeamTradition('Metros', 45, 37);

        self::assertTrue($result);

        $stmt = $this->db->prepare('SELECT Contract_AvgW, Contract_AvgL FROM ibl_team_info WHERE team_name = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $tn);
        $tn = 'Metros';
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(45, $row['Contract_AvgW']);
        self::assertSame(37, $row['Contract_AvgL']);
    }

    // ── getSetting ──────────────────────────────────────────────

    public function testGetSettingReturnsValueForKnownSetting(): void
    {
        // CI seed has 'Allow Trades' = 'Yes'
        $result = $this->repo->getSetting('Allow Trades');

        self::assertSame('Yes', $result);
    }

    public function testGetSettingReturnsNullForUnknownSetting(): void
    {
        $result = $this->repo->getSetting('NonexistentSetting999');

        self::assertNull($result);
    }
}
