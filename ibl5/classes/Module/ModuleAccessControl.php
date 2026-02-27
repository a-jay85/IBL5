<?php

declare(strict_types=1);

namespace Module;

use League\LeagueContext;

/**
 * ModuleAccessControl - Derives module availability from Season phase and settings
 *
 * Replaces the legacy nuke_modules active/view flags with phase-based access control.
 * Combines season phase restrictions, trivia mode, and league context checks.
 */
class ModuleAccessControl
{
    private \Season $season;
    private LeagueContext $leagueContext;

    /** @var array<string, string> */
    private array $settings;

    /**
     * Phase-restricted modules: only accessible during their corresponding phase
     *
     * @var array<string, string>
     */
    private const PHASE_RESTRICTED_MODULES = [
        'Draft' => 'Draft',
        'FreeAgency' => 'Free Agency',
    ];

    /**
     * Modules hidden when Trivia Mode is on
     *
     * @var list<string>
     */
    private const TRIVIA_HIDDEN_MODULES = [
        'CareerLeaderboards',
        'Player',
        'SeasonLeaderboards',
    ];

    public function __construct(\Season $season, LeagueContext $leagueContext, \mysqli $db)
    {
        $this->season = $season;
        $this->leagueContext = $leagueContext;

        $this->settings = [];
        $settingName = 'Trivia Mode';
        $stmt = $db->prepare("SELECT value FROM ibl_settings WHERE name = ? LIMIT 1");
        if ($stmt !== false) {
            $stmt->bind_param('s', $settingName);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result !== false) {
                /** @var array{value: string}|null $row */
                $row = $result->fetch_assoc();
                if ($row !== null) {
                    $this->settings[$settingName] = $row['value'];
                }
            }
            $stmt->close();
        }
    }

    /**
     * Check if a module is accessible to the current user
     *
     * Checks in order:
     * 1. League context (Olympics disables certain IBL-only modules)
     * 2. Phase restrictions (Draft/FreeAgency only during their phases)
     * 3. Trivia mode (Player/SeasonLeaderboards hidden when Trivia is on)
     */
    public function isModuleAccessible(string $moduleName): bool
    {
        // League context check (Olympics disables IBL-only modules)
        if (!$this->leagueContext->isModuleEnabled($moduleName)) {
            return false;
        }

        // Phase-restricted modules
        if (isset(self::PHASE_RESTRICTED_MODULES[$moduleName])) {
            $requiredPhase = self::PHASE_RESTRICTED_MODULES[$moduleName];
            if ($this->season->phase !== $requiredPhase) {
                // Draft module can be made accessible outside Draft phase via "Show Draft Link" toggle
                if ($moduleName === 'Draft' && $this->season->showDraftLink === 'On') {
                    // Allow access — admin has explicitly enabled the Draft link
                } else {
                    return false;
                }
            }
        }

        // Trivia mode check
        $triviaMode = $this->settings['Trivia Mode'] ?? 'Off';
        if ($triviaMode === 'On' && in_array($moduleName, self::TRIVIA_HIDDEN_MODULES, true)) {
            return false;
        }

        return true;
    }
}
