<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Maintenance\MaintenanceRepository;

/**
 * Tests MaintenanceRepository against real MariaDB — team listings,
 * tradition updates, and settings lookups used by maintenance scripts.
 */
#[Group('database')]
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

    // ── getTeamSeasonRecords ─────────────────────────────────────

    public function testGetTeamSeasonRecordsReturnsArray(): void
    {
        // View ibl_team_win_loss is derived from ibl_box_scores_teams — may be
        // empty in CI (needs 82-game seasons). Verify structure, not specific data.
        $seasons = $this->repo->getTeamSeasonRecords('Metros', 9999);

        self::assertIsArray($seasons);
        if ($seasons !== []) {
            self::assertArrayHasKey('year', $seasons[0]);
            self::assertArrayHasKey('wins', $seasons[0]);
            self::assertArrayHasKey('losses', $seasons[0]);
        }
    }

    public function testGetTeamSeasonRecordsExcludesSeasonsAfterTheGivenYear(): void
    {
        // Fetch all available rows to derive a year pivot from live data.
        $all = $this->repo->getTeamSeasonRecords('Metros', 9999, 100);

        self::assertIsArray($all);

        // Verify DESC ordering regardless of whether data exists.
        $years = array_column($all, 'year');
        $sorted = $years;
        rsort($sorted);
        self::assertSame($sorted, $years, 'Seasons must be ordered by year DESC');

        if ($all !== []) {
            // Exclude the newest year and assert none of those rows appear in the result.
            $newest = $all[0]['year'];
            $excluded = $this->repo->getTeamSeasonRecords('Metros', $newest - 1, 100);

            foreach ($excluded as $row) {
                self::assertLessThanOrEqual($newest - 1, $row['year']);
            }
            self::assertNotContains($newest, array_column($excluded, 'year'));
        }
    }

    // ── updateTeamTradition ─────────────────────────────────────

    public function testUpdateTeamTraditionSetsContractFields(): void
    {
        $result = $this->repo->updateTeamTradition('Metros', 45, 37);

        self::assertTrue($result);

        $stmt = $this->db->prepare('SELECT contract_avg_w, contract_avg_l FROM ibl_team_info WHERE team_name = ?');
        self::assertNotFalse($stmt);
        $tn = 'Metros';
        $stmt->bind_param('s', $tn);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(45, $row['contract_avg_w']);
        self::assertSame(37, $row['contract_avg_l']);
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
