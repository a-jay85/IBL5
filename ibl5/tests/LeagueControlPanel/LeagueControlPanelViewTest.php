<?php

declare(strict_types=1);

namespace Tests\LeagueControlPanel;

use LeagueControlPanel\Contracts\LeagueControlPanelViewInterface;
use LeagueControlPanel\LeagueControlPanelView;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LeagueControlPanel\LeagueControlPanelView
 */
class LeagueControlPanelViewTest extends TestCase
{
    private LeagueControlPanelView $view;

    protected function setUp(): void
    {
        $this->view = new LeagueControlPanelView();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(LeagueControlPanelViewInterface::class, $this->view);
    }

    public function testRenderContainsLeagueSwitcher(): void
    {
        $html = $this->renderWithDefaults();

        $this->assertStringContainsString('league-switcher-admin', $html);
        $this->assertStringContainsString('league-badge', $html);
    }

    public function testRenderShowsSeasonPhaseDropdownForIbl(): void
    {
        $html = $this->renderWithDefaults(['currentLeague' => 'ibl']);

        $this->assertStringContainsString('name="SeasonPhase"', $html);
        $this->assertStringContainsString('Set Season Phase', $html);
    }

    public function testRenderHidesSeasonPhaseForOlympics(): void
    {
        $html = $this->renderWithDefaults(['currentLeague' => 'olympics']);

        $this->assertStringNotContainsString('name="SeasonPhase"', $html);
    }

    public function testRenderSelectedOptionMatchesCurrentPhase(): void
    {
        $html = $this->renderWithDefaults([
            'currentLeague' => 'ibl',
            'panelData' => self::createPanelData(['phase' => 'Playoffs']),
        ]);

        $this->assertStringContainsString('value="Playoffs" selected', $html);
    }

    public function testRenderShowsSuccessFlashMessage(): void
    {
        $html = $this->renderWithDefaults([
            'resultMessage' => 'Phase updated successfully.',
            'resultSuccess' => true,
        ]);

        $this->assertStringContainsString('ibl-alert--success', $html);
        $this->assertStringContainsString('Phase updated successfully.', $html);
    }

    public function testRenderShowsErrorFlashMessage(): void
    {
        $html = $this->renderWithDefaults([
            'resultMessage' => 'Something went wrong.',
            'resultSuccess' => false,
        ]);

        $this->assertStringContainsString('ibl-alert--error', $html);
        $this->assertStringContainsString('Something went wrong.', $html);
    }

    public function testRenderNoFlashOnInitialLoad(): void
    {
        $html = $this->renderWithDefaults([
            'resultMessage' => null,
        ]);

        $this->assertStringNotContainsString('ibl-alert--success', $html);
        $this->assertStringNotContainsString('ibl-alert--error', $html);
    }

    public function testRenderPhaseControlsPreseason(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Preseason']),
        ]);

        $this->assertStringContainsString('Update All The Things', $html);
        $this->assertStringContainsString('value="set_allow_waivers"', $html);
        $this->assertStringContainsString('value="set_waivers_to_free_agents"', $html);
        $this->assertStringContainsString('value="reset_contract_extensions"', $html);
        $this->assertStringContainsString('value="reset_mles_lles"', $html);
    }

    public function testRenderPhaseControlsHeat(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'HEAT']),
        ]);

        $this->assertStringContainsString('Update All The Things', $html);
        $this->assertStringNotContainsString('value="set_allow_waivers"', $html);
    }

    public function testRenderPhaseControlsRegularSeasonIbl(): void
    {
        $html = $this->renderWithDefaults([
            'currentLeague' => 'ibl',
            'panelData' => self::createPanelData(['phase' => 'Regular Season']),
        ]);

        $this->assertStringContainsString('Update All The Things', $html);
        $this->assertStringContainsString('name="SimLengthInDays"', $html);
        $this->assertStringContainsString('value="set_sim_length"', $html);
        $this->assertStringContainsString('value="reset_asg_voting"', $html);
        $this->assertStringContainsString('value="reset_eoy_voting"', $html);
        $this->assertStringContainsString('value="set_allow_trades"', $html);
        $this->assertStringContainsString('value="set_show_draft_link"', $html);
    }

    public function testRenderPhaseControlsRegularSeasonOlympics(): void
    {
        $html = $this->renderWithDefaults([
            'currentLeague' => 'olympics',
            'panelData' => self::createPanelData(['phase' => 'Regular Season']),
        ]);

        $this->assertStringContainsString('name="SimLengthInDays"', $html);
        $this->assertStringNotContainsString('value="reset_asg_voting"', $html);
        $this->assertStringNotContainsString('value="set_allow_trades"', $html);
    }

    public function testRenderPhaseControlsPlayoffs(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Playoffs']),
        ]);

        $this->assertStringContainsString('Update All The Things', $html);
        $this->assertStringContainsString('value="reset_eoy_voting"', $html);
        $this->assertStringContainsString('value="set_allow_trades"', $html);
        $this->assertStringContainsString('value="set_show_draft_link"', $html);
    }

    public function testRenderPhaseControlsDraft(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Draft']),
        ]);

        $this->assertStringContainsString('value="set_allow_waivers"', $html);
    }

    public function testRenderPhaseControlsFreeAgency(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Free Agency']),
        ]);

        $this->assertStringContainsString('value="reset_contract_extensions"', $html);
        $this->assertStringContainsString('value="reset_mles_lles"', $html);
        $this->assertStringContainsString('value="set_fa_factors_pfw"', $html);
        $this->assertStringContainsString('tradition.php', $html);
        $this->assertStringContainsString('value="toggle_fa_notifications"', $html);
        $this->assertStringContainsString('value="set_allow_waivers"', $html);
        $this->assertStringContainsString('value="set_waivers_to_free_agents"', $html);
    }

    // --- Awards Controls ---

    public function testPlayoffsShowsGenerateAwardsButton(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Playoffs']),
        ]);

        $this->assertStringContainsString('value="generate_awards"', $html);
        $this->assertStringContainsString('Generate Season Awards', $html);
    }

    public function testDraftShowsGenerateAwardsButton(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Draft']),
        ]);

        $this->assertStringContainsString('value="generate_awards"', $html);
    }

    public function testRegularSeasonDoesNotShowGenerateAwardsButton(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Regular Season']),
        ]);

        $this->assertStringNotContainsString('value="generate_awards"', $html);
    }

    public function testFreeAgencyDoesNotShowGenerateAwardsButton(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Free Agency']),
        ]);

        $this->assertStringNotContainsString('value="generate_awards"', $html);
    }

    public function testPlayoffsShowsFinalsMvpInputWhenNotSet(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Playoffs', 'hasFinalsMvp' => false]),
        ]);

        $this->assertStringContainsString('name="finals_mvp_name"', $html);
        $this->assertStringContainsString('value="set_finals_mvp"', $html);
    }

    public function testPlayoffsHidesFinalsMvpInputWhenAlreadySet(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Playoffs', 'hasFinalsMvp' => true]),
        ]);

        $this->assertStringNotContainsString('name="finals_mvp_name"', $html);
        $this->assertStringNotContainsString('value="set_finals_mvp"', $html);
    }

    public function testDraftShowsFinalsMvpInputWhenNotSet(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Draft', 'hasFinalsMvp' => false]),
        ]);

        $this->assertStringContainsString('name="finals_mvp_name"', $html);
        $this->assertStringContainsString('value="set_finals_mvp"', $html);
    }

    public function testRenderXssProtectionOnPanelData(): void
    {
        $html = $this->renderWithDefaults([
            'resultMessage' => '<script>alert("xss")</script>',
            'resultSuccess' => true,
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderTriviaButtonsAlwaysPresent(): void
    {
        foreach (['Preseason', 'Regular Season', 'Playoffs', 'Draft', 'Free Agency', 'HEAT'] as $phase) {
            $html = $this->renderWithDefaults([
                'panelData' => self::createPanelData(['phase' => $phase]),
            ]);

            $this->assertStringContainsString('value="activate_trivia"', $html, "Missing activate_trivia for phase: $phase");
            $this->assertStringContainsString('value="deactivate_trivia"', $html, "Missing deactivate_trivia for phase: $phase");
        }
    }

    public function testRenderContainsHiddenCurrentPhase(): void
    {
        $html = $this->renderWithDefaults([
            'panelData' => self::createPanelData(['phase' => 'Draft']),
        ]);

        $this->assertStringContainsString('name="current_phase"', $html);
        $this->assertStringContainsString('value="Draft"', $html);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function renderWithDefaults(array $overrides = []): string
    {
        /** @var array{short_name: string, full_name: string} $leagueConfig */
        $leagueConfig = $overrides['leagueConfig'] ?? ['short_name' => 'ibl', 'full_name' => 'Internet Basketball League'];
        /** @var string $currentLeague */
        $currentLeague = $overrides['currentLeague'] ?? 'ibl';
        /** @var array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} $panelData */
        $panelData = $overrides['panelData'] ?? self::createPanelData();
        /** @var string|null $resultMessage */
        $resultMessage = array_key_exists('resultMessage', $overrides) ? $overrides['resultMessage'] : null;
        /** @var bool $resultSuccess */
        $resultSuccess = $overrides['resultSuccess'] ?? false;

        return $this->view->render($leagueConfig, $currentLeague, $panelData, $resultMessage, $resultSuccess);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool}
     */
    private static function createPanelData(array $overrides = []): array
    {
        /** @var array{phase: string, allowTrades: string, allowWaivers: string, showDraftLink: string, freeAgencyNotifications: string, triviaMode: string, simLengthInDays: int, seasonEndingYear: int, hasFinalsMvp: bool} */
        return array_merge([
            'phase' => 'Regular Season',
            'allowTrades' => 'Yes',
            'allowWaivers' => 'No',
            'showDraftLink' => 'Off',
            'freeAgencyNotifications' => 'Off',
            'triviaMode' => 'Off',
            'simLengthInDays' => 3,
            'seasonEndingYear' => 2026,
            'hasFinalsMvp' => false,
        ], $overrides);
    }
}
