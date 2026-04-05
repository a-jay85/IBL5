<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

/**
 * DepthChartEntryViewInterface - Contract for depth chart view rendering
 *
 * Renders all HTML components for depth chart entry forms and submission results.
 * Uses output buffering for form rendering, direct echo for option rendering.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type ProcessedPlayerData from DepthChartEntryProcessorInterface
 */
interface DepthChartEntryViewInterface
{
    /**
     * Render team logo image centered on page
     *
     * @param int $teamID Team ID used to locate logo file (images/logo/{teamID}.jpg)
     */
    public function renderTeamLogo(int $teamID): void;

    /**
     * Render role priority dropdown options (0 to max)
     *
     * Unified for all 5 role slots. Value 0 displays as "—" (not assigned),
     * values 1+ display as their numeric value.
     *
     * @param int $selectedValue Currently selected value
     * @param int $maxValue Maximum option value (2 for BH/DI/OI, 3 for DF/OF)
     */
    public function renderRolePriorityOptions(int $selectedValue, int $maxValue): void;

    /**
     * Render active/inactive dropdown options (0 or 1)
     *
     * @param int $selectedValue Currently selected value (1=Yes, 0=No)
     */
    public function renderActiveOptions(int $selectedValue): void;

    /**
     * Render the help section explaining how depth charts work.
     * Uses a collapsible <details>/<summary> element, collapsed by default.
     */
    public function renderHelpSection(): void;

    /**
     * Render the empty container for the live lineup preview grid.
     * JavaScript populates this based on current form values.
     */
    public function renderLineupPreview(): void;

    /**
     * Render complete depth chart form header with table structure
     *
     * Renders an 8-column table: Pos, Player, Active, PG, SG, SF, PF, C.
     * The PG-C columns are role slot assignments (mapped to BH/DI/OI/DF/OF form fields).
     *
     * @param string $teamName Team name to embed in hidden form field
     * @param int $teamID Team ID
     * @param array<string> $slotNames Array of 5 position slot names (kept for interface compat)
     */
    public function renderFormHeader(string $teamName, int $teamID, array $slotNames): void;

    /**
     * Render a single player row in the depth chart form
     *
     * Renders an 8-cell row: Pos, Player (with hidden fields for dead fields),
     * Active select, and 5 role slot selects. Position depth columns (pg-c) and
     * minutes are rendered as hidden inputs with value 0.
     *
     * Player array must include 'quality_score' key (float) for the lineup preview.
     *
     * @param PlayerRow $player Player data from database (with quality_score added)
     * @param int $depthCount Row counter for form field names
     */
    public function renderPlayerRow(array $player, int $depthCount): void;

    /**
     * Render complete form footer with reset button, submit button, and JavaScript
     */
    public function renderFormFooter(): void;

    /**
     * Render the saved depth chart dropdown selector
     *
     * @param list<array{id: int, label: string, isActive: bool}> $options
     * @param string $currentLiveLabel Label for the "Current (Live)" entry
     */
    public function renderSavedDepthChartDropdown(array $options, string $currentLiveLabel): void;

    /**
     * Render depth chart submission result page
     *
     * Shows submitted values in a confirmation table with columns:
     * Name, Active, PG, SG, SF, PF, C (role slot values).
     *
     * @param string $teamName Team name displayed at top of result
     * @param list<ProcessedPlayerData> $playerData Player data submitted
     * @param bool $success True if submission succeeded
     * @param string $errorHtml Error messages HTML (if $success is false)
     */
    public function renderSubmissionResult(
        string $teamName,
        array $playerData,
        bool $success,
        string $errorHtml = ''
    ): void;

    /**
     * Render mobile card view for all players
     *
     * Renders a card-based layout with a single 5-column role slot grid per card.
     * All inputs are rendered disabled; JavaScript enables them on mobile viewports.
     *
     * @param list<PlayerRow> $players All players on the team roster
     * @param array<string> $slotNames Position slot names (kept for interface compat)
     */
    public function renderMobileView(array $players, array $slotNames): void;
}
