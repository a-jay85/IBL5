<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use LeagueControlPanel\LeagueControlPanelRepository;

class LeagueControlPanelRepositoryTest extends DatabaseTestCase
{
    private LeagueControlPanelRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new LeagueControlPanelRepository($this->db);
    }

    public function testGetSettingReturnsValue(): void
    {
        $value = $this->repo->getSetting('Phase');

        self::assertSame('Regular Season', $value);
    }

    public function testGetSettingReturnsNullForUnknown(): void
    {
        $value = $this->repo->getSetting('Nonexistent Setting');

        self::assertNull($value);
    }

    public function testGetBulkSettingsReturnsMultiple(): void
    {
        $settings = $this->repo->getBulkSettings(['Phase', 'Allow Trades']);

        self::assertCount(2, $settings);
        self::assertSame('Regular Season', $settings['Phase']);
        self::assertSame('Yes', $settings['Allow Trades']);
    }

    public function testGetBulkSettingsReturnsEmptyForEmptyInput(): void
    {
        $settings = $this->repo->getBulkSettings([]);

        self::assertSame([], $settings);
    }

    public function testGetSimLengthInDaysReturnsInt(): void
    {
        $days = $this->repo->getSimLengthInDays();

        self::assertSame(3, $days);
    }

    public function testUpdateSettingChangesValue(): void
    {
        $this->repo->updateSetting('Allow Trades', 'No');

        $value = $this->repo->getSetting('Allow Trades');
        self::assertSame('No', $value);
    }

    public function testSetSeasonPhaseUpdatesSetting(): void
    {
        $this->repo->setSeasonPhase('Playoffs');

        $value = $this->repo->getSetting('Current Season Phase');
        self::assertSame('Playoffs', $value);
    }

    public function testSetSeasonPhasePreseasonDisablesDraftLink(): void
    {
        $this->repo->setSeasonPhase('Preseason');

        $value = $this->repo->getSetting('Current Season Phase');
        self::assertSame('Preseason', $value);

        $draftLink = $this->repo->getSetting('Show Draft Link');
        self::assertSame('Off', $draftLink);
    }

    public function testSetSimLengthInDaysUpdatesValue(): void
    {
        $this->repo->setSimLengthInDays(5);

        $value = $this->repo->getSimLengthInDays();
        self::assertSame(5, $value);
    }

    public function testSetAllowTradesUpdatesValue(): void
    {
        $this->repo->setAllowTrades('No');

        $value = $this->repo->getSetting('Allow Trades');
        self::assertSame('No', $value);
    }

    public function testSetAllowWaiversUpdatesValue(): void
    {
        $this->repo->setAllowWaivers('No');

        $value = $this->repo->getSetting('Allow Waiver Moves');
        self::assertSame('No', $value);
    }

    public function testResetAllStarVotingClearsVotes(): void
    {
        $result = $this->repo->resetAllStarVoting();

        self::assertTrue($result);

        $asgVoting = $this->repo->getSetting('ASG Voting');
        self::assertSame('Yes', $asgVoting);
    }

    public function testResetEndOfYearVotingClearsVotes(): void
    {
        $result = $this->repo->resetEndOfYearVoting();

        self::assertTrue($result);

        $eoyVoting = $this->repo->getSetting('EOY Voting');
        self::assertSame('Yes', $eoyVoting);
    }

    public function testResetAllContractExtensionsZeroesFlags(): void
    {
        // Set a non-zero value first
        $this->db->query("UPDATE ibl_team_info SET used_extension_this_season = 1 WHERE teamid = 1");

        $result = $this->repo->resetAllContractExtensions();
        self::assertTrue($result);

        $stmt = $this->db->prepare("SELECT used_extension_this_season FROM ibl_team_info WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, $row['used_extension_this_season']);
    }

    public function testResetAllMlesAndLlesResetsFlags(): void
    {
        // Set to 0 first
        $this->db->query("UPDATE ibl_team_info SET has_mle = 0, has_lle = 0 WHERE teamid = 1");

        $result = $this->repo->resetAllMlesAndLles();
        self::assertTrue($result);

        $stmt = $this->db->prepare("SELECT has_mle, has_lle FROM ibl_team_info WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(1, $row['has_mle']);
        self::assertSame(1, $row['has_lle']);
    }

    public function testSetFreeAgencyNotificationsUpdatesValue(): void
    {
        $this->repo->setFreeAgencyNotifications('No');

        $value = $this->repo->getSetting('Free Agency Notifications');
        self::assertSame('No', $value);
    }

    public function testActivateTriviaModeUpdatesValue(): void
    {
        $this->repo->activateTriviaMode();

        $value = $this->repo->getSetting('Trivia Mode');
        self::assertSame('On', $value);
    }

    public function testDeactivateTriviaModeUpdatesValue(): void
    {
        $this->repo->activateTriviaMode();
        $this->repo->deactivateTriviaMode();

        $value = $this->repo->getSetting('Trivia Mode');
        self::assertSame('Off', $value);
    }

    // ── setShowDraftLink ────────────────────────────────────────

    public function testSetShowDraftLinkUpdatesSetting(): void
    {
        $this->repo->setShowDraftLink('On');

        $value = $this->repo->getSetting('Show Draft Link');
        self::assertSame('On', $value);
    }

    // ── setWaiversToFreeAgents ──────────────────────────────────

    public function testSetWaiversToFreeAgentsMovesPlayers(): void
    {
        $this->insertTestPlayer(200100030, 'Waiver Player B10', [
            'teamid' => 1,
            'ordinal' => 970,
            'retired' => 0,
            'bird' => 3,
        ]);

        $this->repo->setWaiversToFreeAgents();

        $stmt = $this->db->prepare("SELECT teamid, bird FROM ibl_plr WHERE pid = ?");
        self::assertNotFalse($stmt);
        $pid = 200100030;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, $row['teamid']);
        self::assertSame(0, $row['bird']);
    }

    // ── deleteOutdatedBuyoutsAndCash ─────────────────────────────

    public function testDeleteOutdatedBuyoutsAndCashDeletesFullyPaidBuyout(): void
    {
        // Buyout in final year with all future years zero — all obligations fulfilled, should be deleted
        $id = $this->insertCashConsideration(['cy' => 6, 'salary_yr1' => 500, 'salary_yr2' => 500]);

        $count = $this->repo->deleteOutdatedBuyoutsAndCash();

        self::assertSame(1, $count);
        self::assertNull($this->fetchCashConsiderationById($id));
    }

    public function testDeleteOutdatedBuyoutsAndCashDeletesAllZeroCash(): void
    {
        // Cash consideration with all salary years at zero — should be deleted
        $id = $this->insertCashConsideration(['cy' => 1, 'salary_yr1' => 0]);

        $count = $this->repo->deleteOutdatedBuyoutsAndCash();

        self::assertSame(1, $count);
        self::assertNull($this->fetchCashConsiderationById($id));
    }

    public function testDeleteOutdatedBuyoutsAndCashDeletesCurrentYearOnlyCash(): void
    {
        // Cash with money only in current year (salary_yr1) and no future years — should be deleted
        $id = $this->insertCashConsideration(['cy' => 1, 'salary_yr1' => -500, 'salary_yr2' => 0]);

        $count = $this->repo->deleteOutdatedBuyoutsAndCash();

        self::assertSame(1, $count);
        self::assertNull($this->fetchCashConsiderationById($id));
    }

    public function testDeleteOutdatedBuyoutsAndCashPreservesActiveBuyout(): void
    {
        // Buyout with money still owed in a future year — should NOT be deleted
        $id = $this->insertCashConsideration(['cy' => 2, 'salary_yr1' => 0, 'salary_yr2' => 300, 'salary_yr3' => 300]);

        $count = $this->repo->deleteOutdatedBuyoutsAndCash();

        self::assertSame(0, $count);
        self::assertNotNull($this->fetchCashConsiderationById($id));
    }

    public function testDeleteOutdatedBuyoutsAndCashPreservesActiveCashConsideration(): void
    {
        // Cash consideration with money owed in a future year — should NOT be deleted
        $id = $this->insertCashConsideration(['cy' => 1, 'salary_yr1' => 0, 'salary_yr2' => -500]);

        $count = $this->repo->deleteOutdatedBuyoutsAndCash();

        self::assertSame(0, $count);
        self::assertNotNull($this->fetchCashConsiderationById($id));
    }

    /**
     * Insert a row into ibl_cash_considerations (teamid=1, type='buyout') with given cy fields.
     * Returns the AUTO_INCREMENT id.
     *
     * @param array<string, int> $fields cy, salary_yr1..salary_yr6 overrides
     */
    private function insertCashConsideration(array $fields): int
    {
        $cy  = $fields['cy']  ?? 1;
        $salaryYr1 = $fields['salary_yr1'] ?? 0;
        $salaryYr2 = $fields['salary_yr2'] ?? 0;
        $salaryYr3 = $fields['salary_yr3'] ?? 0;
        $salaryYr4 = $fields['salary_yr4'] ?? 0;
        $salaryYr5 = $fields['salary_yr5'] ?? 0;
        $salaryYr6 = $fields['salary_yr6'] ?? 0;

        $stmt = $this->db->prepare(
            "INSERT INTO ibl_cash_considerations (teamid, type, label, cy, cyt, salary_yr1, salary_yr2, salary_yr3, salary_yr4, salary_yr5, salary_yr6)"
            . " VALUES (1, 'buyout', 'Test Buyout', ?, 6, ?, ?, ?, ?, ?, ?)"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('iiiiiii', $cy, $salaryYr1, $salaryYr2, $salaryYr3, $salaryYr4, $salaryYr5, $salaryYr6);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchCashConsiderationById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id FROM ibl_cash_considerations WHERE id = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return is_array($row) ? $row : null;
    }

    // ── setFreeAgencyFactorsForPfw ──────────────────────────────

    public function testSetFreeAgencyFactorsForPfwUpdatesTeamInfo(): void
    {
        $this->db->query("DELETE FROM ibl_standings WHERE teamid = 1");
        $this->insertRow('ibl_standings', [
            'teamid' => 1,
            'team_name' => 'Metros',
            'wins' => 42,
            'losses' => 8,
            'pct' => 0.840,
            'league_record' => '42-8',
            'conference' => 'East',
            'division' => 'Atlantic',
            'conf_record' => '25-5',
            'conf_gb' => 0.0,
            'div_record' => '12-0',
            'div_gb' => 0.0,
            'home_record' => '25-0',
            'away_record' => '17-8',
            'games_unplayed' => 0,
        ]);

        $this->repo->setFreeAgencyFactorsForPfw();

        $stmt = $this->db->prepare("SELECT contract_wins, contract_losses FROM ibl_team_info WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(42, $row['contract_wins']);
        self::assertSame(8, $row['contract_losses']);
    }
}
