<?php

declare(strict_types=1);

namespace Tests\LeagueControlPanel;

use HistArchiver\Contracts\HistArchiverServiceInterface;
use HistArchiver\HistArchiveResult;
use HistArchiver\PlrValidationReport;
use LeagueControlPanel\Contracts\LeagueControlPanelProcessorInterface;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use LeagueControlPanel\LeagueControlPanelProcessor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LeagueControlPanel\LeagueControlPanelProcessor
 */
class LeagueControlPanelProcessorTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $processor = new LeagueControlPanelProcessor($stub);

        $this->assertInstanceOf(LeagueControlPanelProcessorInterface::class, $processor);
    }

    public function testDispatchUnknownActionReturnsFailure(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('nonexistent_action', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unknown action', $result['message']);
    }

    // --- Set Season Phase ---

    public function testSetSeasonPhaseMissingValue(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_season_phase', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid season phase', $result['message']);
    }

    public function testSetSeasonPhaseInvalidValue(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_season_phase', ['SeasonPhase' => 'InvalidPhase']);

        $this->assertFalse($result['success']);
    }

    public function testSetSeasonPhaseValidValue(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setSeasonPhase')
            ->with('Regular Season');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('set_season_phase', ['SeasonPhase' => 'Regular Season']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Regular Season', $result['message']);
    }

    // --- Set Sim Length ---

    public function testSimLengthNonNumeric(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_sim_length', ['SimLengthInDays' => 'abc']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('number', $result['message']);
    }

    public function testSimLengthBelowMin(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_sim_length', ['SimLengthInDays' => '0']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('between', $result['message']);
    }

    public function testSimLengthAboveMax(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_sim_length', ['SimLengthInDays' => '999']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('between', $result['message']);
    }

    public function testSimLengthValid(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setSimLengthInDays')
            ->with(7);

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('set_sim_length', ['SimLengthInDays' => '7']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('7', $result['message']);
    }

    // --- Allow Trades ---

    public function testAllowTradesInvalidValue(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_allow_trades', ['Trades' => 'Maybe']);

        $this->assertFalse($result['success']);
    }

    public function testAllowTradesValidValue(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setAllowTrades')
            ->with('Yes');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('set_allow_trades', ['Trades' => 'Yes']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Yes', $result['message']);
    }

    // --- Allow Waivers ---

    public function testAllowWaiversInvalidValue(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_allow_waivers', ['Waivers' => 'Maybe']);

        $this->assertFalse($result['success']);
    }

    public function testAllowWaiversValidValue(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setAllowWaivers')
            ->with('No');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('set_allow_waivers', ['Waivers' => 'No']);

        $this->assertTrue($result['success']);
    }

    // --- Show Draft Link ---

    public function testShowDraftLinkInvalidValue(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_show_draft_link', ['ShowDraftLink' => 'Yes']);

        $this->assertFalse($result['success']);
    }

    public function testShowDraftLinkValidValue(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setShowDraftLink')
            ->with('On');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('set_show_draft_link', ['ShowDraftLink' => 'On']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('On', $result['message']);
    }

    // --- FA Notifications ---

    public function testFaNotificationsInvalidValue(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('toggle_fa_notifications', ['FANotifs' => 'Yes']);

        $this->assertFalse($result['success']);
    }

    public function testFaNotificationsValidValue(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setFreeAgencyNotifications')
            ->with('On');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('toggle_fa_notifications', ['FANotifs' => 'On']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('On', $result['message']);
    }

    // --- Trivia ---

    public function testActivateTrivia(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('activateTriviaMode');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('activate_trivia', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('turned on', $result['message']);
    }

    public function testDeactivateTrivia(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('deactivateTriviaMode');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('deactivate_trivia', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('turned off', $result['message']);
    }

    // --- Reset actions ---

    public function testResetContractExtensions(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('resetAllContractExtensions');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('reset_contract_extensions', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('contract extensions', $result['message']);
    }

    public function testResetMlesLles(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('resetAllMlesAndLles');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('reset_mles_lles', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('MLEs and LLEs', $result['message']);
    }

    public function testResetAsgVoting(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('resetAllStarVoting');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('reset_asg_voting', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('ASG Voting', $result['message']);
    }

    public function testResetEoyVoting(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('resetEndOfYearVoting');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('reset_eoy_voting', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('EOY Voting', $result['message']);
    }

    public function testSetWaiversToFreeAgents(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setWaiversToFreeAgents');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('set_waivers_to_free_agents', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Free Agents', $result['message']);
    }

    // --- PFW ---

    public function testPfwFailsOutsideDraftOrFreeAgency(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_fa_factors_pfw', ['current_phase' => 'Regular Season']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Draft or Free Agency', $result['message']);
    }

    public function testPfwSucceedsDuringDraft(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setFreeAgencyFactorsForPfw');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('set_fa_factors_pfw', ['current_phase' => 'Draft']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Play For Winner', $result['message']);
    }

    public function testPfwSucceedsDuringFreeAgency(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setFreeAgencyFactorsForPfw');

        $processor = new LeagueControlPanelProcessor($mock);
        $result = $processor->dispatch('set_fa_factors_pfw', ['current_phase' => 'Free Agency']);

        $this->assertTrue($result['success']);
    }

    // --- Archive Season Hist ---

    public function testArchiveSeasonHistWhenArchiverNotConfigured(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('archive_season_hist', ['season_year' => '2026']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not configured', $result['message']);
    }

    public function testArchiveSeasonHistMissingSeasonYear(): void
    {
        $processor = $this->createProcessorWithArchiver($this->createStub(HistArchiverServiceInterface::class));

        $result = $processor->dispatch('archive_season_hist', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid season year', $result['message']);
    }

    public function testArchiveSeasonHistNonDigitSeasonYear(): void
    {
        $processor = $this->createProcessorWithArchiver($this->createStub(HistArchiverServiceInterface::class));

        $result = $processor->dispatch('archive_season_hist', ['season_year' => '2026a']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid season year', $result['message']);
    }

    public function testArchiveSeasonHistSkipsWhenNoChampion(): void
    {
        $archiver = $this->createStub(HistArchiverServiceInterface::class);
        $archiver->method('archiveSeason')->willReturn(HistArchiveResult::skipped());

        $processor = $this->createProcessorWithArchiver($archiver);
        $result = $processor->dispatch('archive_season_hist', ['season_year' => '2026']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No champion found', $result['message']);
    }

    public function testArchiveSeasonHistFailsWhenZeroPlayers(): void
    {
        $archiver = $this->createStub(HistArchiverServiceInterface::class);
        $archiver->method('archiveSeason')->willReturn(HistArchiveResult::completed(0, 0, []));

        $processor = $this->createProcessorWithArchiver($archiver);
        $result = $processor->dispatch('archive_season_hist', ['season_year' => '2026']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No players archived', $result['message']);
    }

    public function testArchiveSeasonHistSuccessNoWarnings(): void
    {
        $archiver = $this->createStub(HistArchiverServiceInterface::class);
        $archiver->method('archiveSeason')->willReturn(HistArchiveResult::completed(3, 3, []));

        $processor = $this->createProcessorWithArchiver($archiver);
        $result = $processor->dispatch('archive_season_hist', ['season_year' => '2026']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('3 players archived', $result['message']);
        $this->assertStringNotContainsString('warnings', $result['message']);
    }

    public function testArchiveSeasonHistSuccessWithWarnings(): void
    {
        $archiver = $this->createStub(HistArchiverServiceInterface::class);
        $archiver->method('archiveSeason')->willReturn(
            HistArchiveResult::completed(2, 2, ['WARNING: x', 'WARNING: y']),
        );

        $processor = $this->createProcessorWithArchiver($archiver);
        $result = $processor->dispatch('archive_season_hist', ['season_year' => '2026']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('2 players archived', $result['message']);
        $this->assertStringContainsString('2 warnings', $result['message']);
    }

    // --- Validate PLR Accuracy ---

    public function testValidatePlrAccuracyWhenArchiverNotConfigured(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('validate_plr_accuracy', ['season_year' => '2026']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not configured', $result['message']);
    }

    public function testValidatePlrAccuracyInvalidSeasonYear(): void
    {
        $processor = $this->createProcessorWithArchiver($this->createStub(HistArchiverServiceInterface::class));

        $result = $processor->dispatch('validate_plr_accuracy', ['season_year' => 'abc']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid season year', $result['message']);
    }

    public function testValidatePlrAccuracyZeroDiscrepancies(): void
    {
        $archiver = $this->createStub(HistArchiverServiceInterface::class);
        $archiver->method('validatePlrVsBoxScores')->willReturn(
            new PlrValidationReport(totalPlayers: 5, matchCount: 5, discrepancies: []),
        );

        $processor = $this->createProcessorWithArchiver($archiver);
        $result = $processor->dispatch('validate_plr_accuracy', ['season_year' => '2026']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('All 5 players match', $result['message']);
    }

    public function testValidatePlrAccuracyWithDiscrepancies(): void
    {
        $archiver = $this->createStub(HistArchiverServiceInterface::class);
        $archiver->method('validatePlrVsBoxScores')->willReturn(
            new PlrValidationReport(
                totalPlayers: 5,
                matchCount: 3,
                discrepancies: [
                    ['pid' => 1, 'name' => 'A', 'column' => 'pts', 'hist_value' => 100, 'box_score_value' => 102],
                    ['pid' => 2, 'name' => 'B', 'column' => 'ast', 'hist_value' => 50, 'box_score_value' => 48],
                ],
            ),
        );

        $processor = $this->createProcessorWithArchiver($archiver);
        $result = $processor->dispatch('validate_plr_accuracy', ['season_year' => '2026']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('2 discrepancies', $result['message']);
        $this->assertStringContainsString('3/5 match', $result['message']);
    }

    private function createProcessorWithStub(): LeagueControlPanelProcessor
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        return new LeagueControlPanelProcessor($stub);
    }

    private function createProcessorWithArchiver(HistArchiverServiceInterface $archiver): LeagueControlPanelProcessor
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        return new LeagueControlPanelProcessor($stub, $archiver);
    }
}
