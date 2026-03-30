<?php

declare(strict_types=1);

namespace Tests\LeagueControlPanel;

use LeagueControlPanel\Contracts\AwardGenerationServiceInterface;
use LeagueControlPanel\Contracts\LeagueControlPanelProcessorInterface;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use LeagueControlPanel\LeagueControlPanelProcessor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LeagueControlPanel\LeagueControlPanelProcessor
 */
class LeagueControlPanelProcessorTest extends TestCase
{
    private static string $tempRoot;

    public static function setUpBeforeClass(): void
    {
        if (!defined('IBL5_ROOT')) {
            $dir = sys_get_temp_dir() . '/lcp_processor_test_' . uniqid();
            mkdir($dir);
            define('IBL5_ROOT', $dir);
        }
        self::$tempRoot = (string) constant('IBL5_ROOT');
    }

    protected function tearDown(): void
    {
        $htm = self::$tempRoot . '/Leaders.htm';
        if (file_exists($htm)) {
            unlink($htm);
        }
    }

    public function testImplementsInterface(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $awardStub = $this->createStub(AwardGenerationServiceInterface::class);
        $processor = new LeagueControlPanelProcessor($stub, $awardStub);

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

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
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

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
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

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
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

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
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

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
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

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
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

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('activate_trivia', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('turned on', $result['message']);
    }

    public function testDeactivateTrivia(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('deactivateTriviaMode');

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('deactivate_trivia', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('turned off', $result['message']);
    }

    // --- Delete Draft Placeholders ---

    public function testDeleteDraftPlaceholders(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('deleteDraftPlaceholders')
            ->willReturn(5);

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('delete_draft_placeholders', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('5 draft placeholder(s)', $result['message']);
    }

    // --- Reset actions ---

    public function testResetContractExtensions(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('resetAllContractExtensions');

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('reset_contract_extensions', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('contract extensions', $result['message']);
    }

    public function testResetMlesLles(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('resetAllMlesAndLles');

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('reset_mles_lles', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('MLEs and LLEs', $result['message']);
    }

    public function testResetAsgVoting(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('resetAllStarVoting');

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('reset_asg_voting', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('ASG Voting', $result['message']);
    }

    public function testResetEoyVoting(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('resetEndOfYearVoting');

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('reset_eoy_voting', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('EOY Voting', $result['message']);
    }

    public function testSetWaiversToFreeAgents(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setWaiversToFreeAgents');

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
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

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('set_fa_factors_pfw', ['current_phase' => 'Draft']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Play For Winner', $result['message']);
    }

    public function testPfwSucceedsDuringFreeAgency(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('setFreeAgencyFactorsForPfw');

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('set_fa_factors_pfw', ['current_phase' => 'Free Agency']);

        $this->assertTrue($result['success']);
    }

    // --- Generate Awards ---

    public function testGenerateAwardsFailsOutsidePlayoffsOrDraft(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getSetting')->willReturnMap([
            ['Current Season Phase', 'Regular Season'],
        ]);

        $processor = new LeagueControlPanelProcessor($stub, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('generate_awards', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Playoffs or Draft', $result['message']);
    }

    public function testGenerateAwardsFailsWhenYearSettingMissing(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getSetting')->willReturnMap([
            ['Current Season Phase', 'Playoffs'],
            ['Current Season Ending Year', null],
        ]);

        $processor = new LeagueControlPanelProcessor($stub, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('generate_awards', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Season Ending Year', $result['message']);
    }

    public function testGenerateAwardsFailsWhenLeadersHtmMissing(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getSetting')->willReturnMap([
            ['Current Season Phase', 'Playoffs'],
            ['Current Season Ending Year', '2026'],
        ]);

        $processor = new LeagueControlPanelProcessor($stub, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('generate_awards', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Leaders.htm not found', $result['message']);
    }

    public function testGenerateAwardsSuccessDelegatesToService(): void
    {
        file_put_contents(self::$tempRoot . '/Leaders.htm', '<html></html>');

        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getSetting')->willReturnMap([
            ['Current Season Phase', 'Playoffs'],
            ['Current Season Ending Year', '2026'],
        ]);

        $expectedPath = self::$tempRoot . '/Leaders.htm';
        $awardMock = $this->createMock(AwardGenerationServiceInterface::class);
        $awardMock->expects($this->once())
            ->method('generateSeasonAwards')
            ->with(2026, $expectedPath)
            ->willReturn(['success' => true, 'message' => 'Awards generated: 92 inserted, 0 skipped.', 'inserted' => 92, 'skipped' => 0]);

        $processor = new LeagueControlPanelProcessor($stub, $awardMock);
        $result = $processor->dispatch('generate_awards', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Awards generated', $result['message']);
    }

    public function testGenerateAwardsSucceedsDuringDraft(): void
    {
        file_put_contents(self::$tempRoot . '/Leaders.htm', '<html></html>');

        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getSetting')->willReturnMap([
            ['Current Season Phase', 'Draft'],
            ['Current Season Ending Year', '2026'],
        ]);

        $awardStub = $this->createStub(AwardGenerationServiceInterface::class);
        $awardStub->method('generateSeasonAwards')
            ->willReturn(['success' => true, 'message' => 'Awards generated.', 'inserted' => 5, 'skipped' => 0]);

        $processor = new LeagueControlPanelProcessor($stub, $awardStub);
        $result = $processor->dispatch('generate_awards', []);

        $this->assertTrue($result['success']);
    }

    // --- Set Finals MVP ---

    public function testSetFinalsMvpFailsWhenNameEmpty(): void
    {
        $processor = $this->createProcessorWithStub();

        $result = $processor->dispatch('set_finals_mvp', ['finals_mvp_name' => '']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('cannot be empty', $result['message']);
    }

    public function testSetFinalsMvpFailsWhenYearSettingMissing(): void
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getSetting')->willReturnMap([
            ['Current Season Ending Year', null],
        ]);

        $processor = new LeagueControlPanelProcessor($stub, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('set_finals_mvp', ['finals_mvp_name' => 'James Harden']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Season Ending Year', $result['message']);
    }

    public function testSetFinalsMvpSuccessCallsUpsertAward(): void
    {
        $mock = $this->createMock(LeagueControlPanelRepositoryInterface::class);
        $mock->method('getSetting')->willReturnMap([
            ['Current Season Ending Year', '2026'],
        ]);
        $mock->expects($this->once())
            ->method('upsertAward')
            ->with(2026, 'IBL Finals MVP', 'James Harden');

        $processor = new LeagueControlPanelProcessor($mock, $this->createStub(AwardGenerationServiceInterface::class));
        $result = $processor->dispatch('set_finals_mvp', ['finals_mvp_name' => 'James Harden']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('James Harden', $result['message']);
        $this->assertStringContainsString('2026', $result['message']);
    }

    private function createProcessorWithStub(): LeagueControlPanelProcessor
    {
        $stub = $this->createStub(LeagueControlPanelRepositoryInterface::class);
        $awardStub = $this->createStub(AwardGenerationServiceInterface::class);
        return new LeagueControlPanelProcessor($stub, $awardStub);
    }
}
