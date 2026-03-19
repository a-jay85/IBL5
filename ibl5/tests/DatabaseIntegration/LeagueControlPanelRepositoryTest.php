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
        $this->db->query("UPDATE ibl_team_info SET Used_Extension_This_Season = 1 WHERE teamid = 1");

        $result = $this->repo->resetAllContractExtensions();
        self::assertTrue($result);

        $stmt = $this->db->prepare("SELECT Used_Extension_This_Season FROM ibl_team_info WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(0, $row['Used_Extension_This_Season']);
    }

    public function testResetAllMlesAndLlesResetsFlags(): void
    {
        // Set to 0 first
        $this->db->query("UPDATE ibl_team_info SET HasMLE = 0, HasLLE = 0 WHERE teamid = 1");

        $result = $this->repo->resetAllMlesAndLles();
        self::assertTrue($result);

        $stmt = $this->db->prepare("SELECT HasMLE, HasLLE FROM ibl_team_info WHERE teamid = 1");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(1, $row['HasMLE']);
        self::assertSame(1, $row['HasLLE']);
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
}
