<?php

declare(strict_types=1);

namespace LeagueControlPanel;

use LeagueControlPanel\Contracts\AwardGenerationServiceInterface;
use LeagueControlPanel\Contracts\LeagueControlPanelProcessorInterface;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use Discord\Discord;

/**
 * @see LeagueControlPanelProcessorInterface
 */
class LeagueControlPanelProcessor implements LeagueControlPanelProcessorInterface
{
    private const VALID_PHASES = [
        'Preseason',
        'HEAT',
        'Regular Season',
        'Playoffs',
        'Draft',
        'Free Agency',
    ];

    private const VALID_YES_NO = ['Yes', 'No'];
    private const VALID_ON_OFF = ['On', 'Off'];

    private const SIM_LENGTH_MIN = 1;
    private const SIM_LENGTH_MAX = 180;

    private LeagueControlPanelRepositoryInterface $repository;
    private AwardGenerationServiceInterface $awardGenerationService;

    public function __construct(
        LeagueControlPanelRepositoryInterface $repository,
        AwardGenerationServiceInterface $awardGenerationService,
    ) {
        $this->repository = $repository;
        $this->awardGenerationService = $awardGenerationService;
    }

    /**
     * @see LeagueControlPanelProcessorInterface::dispatch()
     */
    public function dispatch(string $action, array $postData): array
    {
        return match ($action) {
            'set_season_phase' => $this->setSeasonPhase($postData),
            'set_sim_length' => $this->setSimLength($postData),
            'set_allow_trades' => $this->setAllowTrades($postData),
            'set_allow_waivers' => $this->setAllowWaivers($postData),
            'set_show_draft_link' => $this->setShowDraftLink($postData),
            'toggle_fa_notifications' => $this->toggleFreeAgencyNotifications($postData),
            'activate_trivia' => $this->activateTrivia(),
            'deactivate_trivia' => $this->deactivateTrivia(),
            'delete_draft_placeholders' => $this->deleteDraftPlaceholders(),
            'reset_contract_extensions' => $this->resetContractExtensions(),
            'reset_mles_lles' => $this->resetMlesLles(),
            'reset_asg_voting' => $this->resetAsgVoting(),
            'reset_eoy_voting' => $this->resetEoyVoting(),
            'set_waivers_to_free_agents' => $this->setWaiversToFreeAgents(),
            'set_fa_factors_pfw' => $this->setFaFactorsPfw($postData),
            'generate_awards' => $this->generateAwards($postData),
            'set_finals_mvp' => $this->setFinalsMvp($postData),
            default => ['success' => false, 'message' => 'Unknown action: ' . $action],
        };
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, message: string}
     */
    public function setSeasonPhase(array $postData): array
    {
        $phase = $postData['SeasonPhase'] ?? null;
        if (!is_string($phase) || !in_array($phase, self::VALID_PHASES, true)) {
            return ['success' => false, 'message' => 'Invalid season phase.'];
        }

        $this->repository->setSeasonPhase($phase);

        return ['success' => true, 'message' => 'Season Phase has been set to ' . $phase . '.'];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, message: string}
     */
    public function setSimLength(array $postData): array
    {
        $raw = $postData['SimLengthInDays'] ?? null;
        if (!is_numeric($raw)) {
            return ['success' => false, 'message' => 'Sim length must be a number.'];
        }

        $days = (int) $raw;
        if ($days < self::SIM_LENGTH_MIN || $days > self::SIM_LENGTH_MAX) {
            return ['success' => false, 'message' => 'Sim length must be between ' . self::SIM_LENGTH_MIN . ' and ' . self::SIM_LENGTH_MAX . '.'];
        }

        $this->repository->setSimLengthInDays($days);

        return ['success' => true, 'message' => 'Sim Length in Days has been set to ' . $days . '.'];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, message: string}
     */
    public function setAllowTrades(array $postData): array
    {
        $value = $postData['Trades'] ?? null;
        if (!is_string($value) || !in_array($value, self::VALID_YES_NO, true)) {
            return ['success' => false, 'message' => 'Invalid Allow Trades value.'];
        }

        $this->repository->setAllowTrades($value);

        return ['success' => true, 'message' => 'Allow Trades Status has been set to ' . $value . '.'];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, message: string}
     */
    public function setAllowWaivers(array $postData): array
    {
        $value = $postData['Waivers'] ?? null;
        if (!is_string($value) || !in_array($value, self::VALID_YES_NO, true)) {
            return ['success' => false, 'message' => 'Invalid Allow Waivers value.'];
        }

        $this->repository->setAllowWaivers($value);

        return ['success' => true, 'message' => 'Allow Waiver Moves Status has been set to ' . $value . '.'];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, message: string}
     */
    public function setShowDraftLink(array $postData): array
    {
        $value = $postData['ShowDraftLink'] ?? null;
        if (!is_string($value) || !in_array($value, self::VALID_ON_OFF, true)) {
            return ['success' => false, 'message' => 'Invalid Show Draft Link value.'];
        }

        $this->repository->setShowDraftLink($value);

        return ['success' => true, 'message' => 'Show Draft Link has been set to ' . $value . '.'];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, message: string}
     */
    public function toggleFreeAgencyNotifications(array $postData): array
    {
        $value = $postData['FANotifs'] ?? null;
        if (!is_string($value) || !in_array($value, self::VALID_ON_OFF, true)) {
            return ['success' => false, 'message' => 'Invalid Free Agency Notifications value.'];
        }

        $this->repository->setFreeAgencyNotifications($value);

        $message = 'Free Agency Notifications are now ' . $value . '.';
        Discord::postToChannel('#free-agency', $message);

        return ['success' => true, 'message' => $message];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function activateTrivia(): array
    {
        $this->repository->activateTriviaMode();

        return ['success' => true, 'message' => 'Trivia Mode has been turned on. Player and Season Leaders modules are now hidden.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deactivateTrivia(): array
    {
        $this->repository->deactivateTriviaMode();

        return ['success' => true, 'message' => 'Trivia Mode has been turned off. Player and Season Leaders modules are now accessible.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteDraftPlaceholders(): array
    {
        $count = $this->repository->deleteDraftPlaceholders();

        return ['success' => true, 'message' => 'Deleted ' . $count . ' draft placeholder(s) from ibl_plr.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function resetContractExtensions(): array
    {
        $this->repository->resetAllContractExtensions();

        return ['success' => true, 'message' => "All teams' contract extensions have been reset."];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function resetMlesLles(): array
    {
        $this->repository->resetAllMlesAndLles();

        return ['success' => true, 'message' => "All teams' MLEs and LLEs have been reset."];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function resetAsgVoting(): array
    {
        $this->repository->resetAllStarVoting();

        return ['success' => true, 'message' => 'ASG Voting has been reset!'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function resetEoyVoting(): array
    {
        $this->repository->resetEndOfYearVoting();

        return ['success' => true, 'message' => 'EOY Voting has been reset!'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function setWaiversToFreeAgents(): array
    {
        $this->repository->setWaiversToFreeAgents();

        return ['success' => true, 'message' => 'All players currently on waivers have their teamname set to Free Agents and 0 Bird years.'];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, message: string}
     */
    public function setFaFactorsPfw(array $postData): array
    {
        $currentPhase = $postData['current_phase'] ?? '';
        if (!is_string($currentPhase)) {
            $currentPhase = '';
        }

        if ($currentPhase !== 'Draft' && $currentPhase !== 'Free Agency') {
            return [
                'success' => false,
                'message' => "Sorry, that button can only be used during the Draft or Free Agency.\nThe FA demands formula requires the current season to be finished before calculating factors.",
            ];
        }

        $this->repository->setFreeAgencyFactorsForPfw();

        return ['success' => true, 'message' => "The columns that affect each team's Play For Winner demand factor have been updated."];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, message: string}
     */
    private function generateAwards(array $postData): array
    {
        $currentPhase = $this->repository->getSetting('Current Season Phase') ?? '';

        if ($currentPhase !== 'Playoffs' && $currentPhase !== 'Draft') {
            return [
                'success' => false,
                'message' => 'Season awards can only be generated during Playoffs or Draft phase.',
            ];
        }

        // Resolve season year from settings
        $yearSetting = $this->repository->getSetting('Current Season Ending Year');
        if ($yearSetting === null) {
            return ['success' => false, 'message' => 'Current Season Ending Year setting not found.'];
        }
        $year = (int) $yearSetting;

        // Resolve Leaders.htm path
        $raw = defined('IBL5_ROOT') ? IBL5_ROOT : null;
        $ibl5Root = is_string($raw) ? $raw : dirname(__DIR__);
        $leadersHtmPath = $ibl5Root . '/Leaders.htm';

        if (!file_exists($leadersHtmPath)) {
            return ['success' => false, 'message' => 'Leaders.htm not found at: ' . $leadersHtmPath];
        }

        $result = $this->awardGenerationService->generateSeasonAwards($year, $leadersHtmPath);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
        ];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: bool, message: string}
     */
    private function setFinalsMvp(array $postData): array
    {
        $name = $postData['finals_mvp_name'] ?? '';
        if (!is_string($name)) {
            $name = '';
        }
        $name = trim($name);

        if ($name === '') {
            return ['success' => false, 'message' => 'Finals MVP name cannot be empty.'];
        }

        $yearSetting = $this->repository->getSetting('Current Season Ending Year');
        if ($yearSetting === null) {
            return ['success' => false, 'message' => 'Current Season Ending Year setting not found.'];
        }
        $year = (int) $yearSetting;

        $this->repository->upsertAward($year, 'IBL Finals MVP', $name);

        return ['success' => true, 'message' => 'IBL Finals MVP set to ' . $name . ' for ' . $year . '.'];
    }
}
