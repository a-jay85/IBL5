<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryServiceInterface;
use DepthChartEntry\Contracts\DepthChartEntryViewInterface;
use League\LeagueContext;
use Security\HtmlSanitizer;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 *
 * @see DepthChartEntryViewInterface
 */
class DepthChartEntryView implements DepthChartEntryViewInterface
{
    private LeagueContext $leagueContext;
    private DepthChartEntryServiceInterface $service;

    private const POSITION_SLOTS = [
        ['label' => 'PG', 'field' => 'pg', 'dbKey' => 'dc_pg_depth'],
        ['label' => 'SG', 'field' => 'sg', 'dbKey' => 'dc_sg_depth'],
        ['label' => 'SF', 'field' => 'sf', 'dbKey' => 'dc_sf_depth'],
        ['label' => 'PF', 'field' => 'pf', 'dbKey' => 'dc_pf_depth'],
        ['label' => 'C',  'field' => 'c',  'dbKey' => 'dc_c_depth'],
    ];

    public function __construct(LeagueContext $leagueContext, DepthChartEntryServiceInterface $service)
    {
        $this->leagueContext = $leagueContext;
        $this->service = $service;
    }

    /**
     * @see DepthChartEntryViewInterface::renderTeamLogo()
     */
    public function renderTeamLogo(int $teamid): void
    {
        $leagueConfig = $this->leagueContext->getConfig();
        /** @var string $imagesPath */
        $imagesPath = $leagueConfig['images_path'];

        echo '<div class="depth-chart-logo"><img src="./' . HtmlSanitizer::e($imagesPath) . 'logo/' . HtmlSanitizer::e($teamid) . '.jpg" alt="Team Logo"></div>';
    }

    /**
     * Render position depth dropdown options (0-5).
     *
     * Label convention:
     *   0 → "No"  (not assigned to this position)
     *   1 → "1st" (starter — highest lineup priority)
     *   2 → "2nd" (first backup)
     *   3 → "3rd" (second backup)
     *   4 → "4th" (third backup)
     *   5 → "ok"  (emergency depth)
     */
    public function renderPositionDepthOptions(int $selectedValue): void
    {
        $labels = ['No', '1st', '2nd', '3rd', '4th', 'ok'];
        for ($i = 0; $i <= 5; $i++) {
            echo '<option value="' . HtmlSanitizer::e($i) . '"' . (($selectedValue === $i) ? ' SELECTED' : '') . '>' . HtmlSanitizer::e($labels[$i]) . '</option>';
        }
    }

    /**
     * Render the help section explaining how depth charts work.
     */
    public function renderHelpSection(): void
    {
        echo '<details class="dc-help-section">
<summary>How Depth Charts Work</summary>
<div class="dc-help-section__content">
<ol>
<li>Each row in the table is one of your players.</li>
<li>The five columns &ndash; <strong>PG SG SF PF C</strong> &ndash; are the five lineup slots you fill.</li>
<li>For each slot, set the player&rsquo;s depth priority:</li>
</ol>
<table class="ibl-data-table dc-help-table">
<thead><tr><th>Option</th><th>Meaning</th></tr></thead>
<tbody>
<tr><td><strong>1st</strong></td><td>Starter (highest lineup priority)</td></tr>
<tr><td><strong>2nd</strong></td><td>First backup</td></tr>
<tr><td><strong>3rd</strong></td><td>Second backup</td></tr>
<tr><td><strong>4th</strong></td><td>Third backup</td></tr>
<tr><td><strong>ok</strong></td><td>Emergency depth</td></tr>
<tr><td><strong>No</strong></td><td>Not assigned to this position</td></tr>
</tbody>
</table>
<p><strong>To set your lineup:</strong></p>
<ol>
<li>Set <strong>one</strong> player to <strong>1st</strong> for each position.</li>
<li>Set <strong>2nd</strong> for players you want to sub in first.</li>
<li>Set <strong>3rd</strong>/<strong>4th</strong> for deeper backups.</li>
<li>A player can back up multiple positions (e.g. 2nd at PG and 3rd at SG).</li>
<li>Set <strong>Min</strong> to control how long each player is on the floor.</li>
<li>Starters usually want 30&ndash;40; bench players want lower numbers.</li>
</ol>
<p><strong>Projected Lineup:</strong><br></p>
<p>If a name appears in <em>italic gray</em>, it means you didn&rsquo;t assign
enough players to that slot, so the sim is falling back on a backup automatically.
<br>Add a <strong>2nd</strong> or <strong>3rd</strong> to the player you actually want there.</p>
<p><strong>Note:</strong> a starter (1st) only plays their <strong>one</strong> slot &ndash;
starters are locked to that slot and removed from every other slot&rsquo;s
ladder. If you want one backup to cover multiple slots, set <strong>2nd</strong> or
<strong>3rd</strong> on them in several columns and leave <strong>1st</strong> off &mdash;
then they&rsquo;ll appear as a backup in each slot&rsquo;s ladder.</p>
<p>The sim fills slots in order <strong>PG &rarr; SG &rarr; SF &rarr; PF &rarr; C</strong>,
so if two slots both have a viable <strong>1st</strong> pick that includes the same player,
the earlier slot in that order claims them.</p>
</div>
</details>';
    }

    /**
     * Render the empty container for the live lineup preview grid.
     * JavaScript populates this based on current form values.
     */
    public function renderLineupPreview(): void
    {
        echo '<div id="dc-lineup-preview" class="dc-lineup-preview"></div>';
    }

    /**
     * @see DepthChartEntryViewInterface::renderFormHeader()
     */
    public function renderFormHeader(string $teamLogo, int $teamid, array $slotNames): void
    {
        echo '<form name="DepthChartEntry" method="post" action="modules.php?name=DepthChartEntry&amp;op=submit" class="depth-chart-form">
            ' . \Security\CsrfGuard::generateToken('depth_chart') . '
            <input type="hidden" name="Team_Name" value="' . HtmlSanitizer::e($teamLogo) . '">
            <input type="hidden" name="loaded_dc_id" id="loaded_dc_id" value="0">';

        echo '<div class="text-center"><table class="depth-chart-table ibl-data-table" data-no-responsive>
            <thead>
                <tr>
                    <th>Pos</th>
                    <th>Player</th>
                    <th>Active</th>';

        foreach (self::POSITION_SLOTS as $slot) {
            echo '<th>' . HtmlSanitizer::e($slot['label']) . '</th>';
        }

        echo '              <th>Min</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * @see DepthChartEntryViewInterface::renderPlayerRow()
     * @param PlayerRow $player
     */
    public function renderPlayerRow(array $player, int $depthCount): void
    {
        $playerName = $player['name'];
        $jsbProduction = $this->service->computeJsbProduction($player);

        $thumbnail = \Player\PlayerImageHelper::renderThumbnail((int) $player['pid']);

        echo '<tr data-pid="' . HtmlSanitizer::e($player['pid']) . '" data-pos="' . HtmlSanitizer::e($player['pos']) . '" data-jsb-production="' . HtmlSanitizer::e($jsbProduction) . '" data-quality-score="' . HtmlSanitizer::e($player['quality_score'] ?? 0.0) . '">'
            . '<td>' . HtmlSanitizer::e($player['pos']) . '</td>'
            . '<td class="ibl-player-cell">'
            . '<input type="hidden" name="pid' . HtmlSanitizer::e($depthCount) . '" value="' . HtmlSanitizer::e($player['pid']) . '">'
            . '<input type="hidden" name="Injury' . HtmlSanitizer::e($depthCount) . '" value="' . HtmlSanitizer::e($player['injured'] ?? 0) . '">'
            . '<input type="hidden" name="Name' . HtmlSanitizer::e($depthCount) . '" value="' . HtmlSanitizer::e($playerName) . '">'
            . '<a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=' . HtmlSanitizer::e($player['pid']) . '">'
            . HtmlSanitizer::trusted($thumbnail) . HtmlSanitizer::e($playerName)
            . '</a>'
            . '</td>';

        // Active status — hidden input submits "0" when checkbox is unchecked;
        // the checkbox submits "1" when checked. Two fields share the same
        // name so the form posts the right value regardless of checkbox state.
        echo '<td class="dc-active-cell">';
        echo '<input type="hidden" name="canPlayInGame' . HtmlSanitizer::e($depthCount) . '" value="0">';
        echo '<input type="checkbox" name="canPlayInGame' . HtmlSanitizer::e($depthCount) . '" value="1" class="dc-active-cb"' . ((int) ($player['dc_can_play_in_game'] ?? 0) === 1 ? ' checked' : '') . ' aria-label="Active status for ' . HtmlSanitizer::e($playerName) . '">';
        echo '</td>';

        foreach (self::POSITION_SLOTS as $slot) {
            $dcValue = (int) ($player[$slot['dbKey']] ?? 0);
            if ($dcValue < 0) {
                $dcValue = 0;
            }
            if ($dcValue > 5) {
                $dcValue = 5;
            }
            $fieldName = $slot['field'] . $depthCount;
            $ariaLabel = $slot['label'] . ' depth for ' . $playerName;

            echo '<td><select name="' . HtmlSanitizer::e($fieldName) . '" aria-label="' . HtmlSanitizer::e($ariaLabel) . '">';
            $this->renderPositionDepthOptions($dcValue);
            echo '</select><span class="dc-score-debug"></span></td>';
        }

        // Minutes — number input constrained to 0-40 with native browser
        // stepper. The server sanitizes to the same 0-40 range in
        // DepthChartEntryProcessor::sanitizeMinutesValue().
        echo '<td class="dc-minutes-cell"><input type="number" name="min' . HtmlSanitizer::e($depthCount) . '" value="' . HtmlSanitizer::e($player['dc_minutes'] ?? 0) . '" min="0" max="40" step="1" class="dc-minutes-input" aria-label="Minutes for ' . HtmlSanitizer::e($playerName) . '"></td>';

        echo '</tr>';
    }

    /**
     * @see DepthChartEntryViewInterface::renderFormFooter()
     */
    public function renderFormFooter(): void
    {
        echo <<<'JAVASCRIPT'
<script type="text/javascript">
function resetDepthChart() {
    if (!confirm('Are you sure you want to reset all fields to their default values? This will discard any changes you have made.')) {
        return false;
    }

    var form = document.forms['DepthChartEntry'];
    if (!form) return;

    // Reset position depth selects to 0
    var selects = form.getElementsByTagName('select');
    for (var i = 0; i < selects.length; i++) {
        selects[i].value = '0';
    }

    // Mirror the reset into the mobile stepper labels, which are a sibling
    // UI layer over the hidden <select>s.
    if (typeof window.IBL_syncDepthChartStepperLabels === 'function') {
        window.IBL_syncDepthChartStepperLabels();
    }

    // Reset minutes number inputs to blank — the server's extractIntValue()
    // converts blank → 0 on submit, so this leaves the GM with an empty
    // field they can type into rather than a stale "0" they have to clear.
    var minInputs = form.querySelectorAll('input[type="number"][name^="min"]');
    for (var j = 0; j < minInputs.length; j++) {
        minInputs[j].value = '';
    }

    // Reset active checkboxes (canPlayInGame) to checked — both desktop
    // (.dc-active-cb) and mobile (.dc-card__active-cb) are covered by the
    // name prefix selector.
    var activeCheckboxes = form.querySelectorAll('input[type="checkbox"][name^="canPlayInGame"]');
    for (var k = 0; k < activeCheckboxes.length; k++) {
        activeCheckboxes[k].checked = true;
        var card = activeCheckboxes[k].closest('.dc-card');
        if (card) card.classList.remove('dc-card--inactive');
    }

    if (typeof window.IBL_recalculateDepthChartGlows === 'function') {
        window.IBL_recalculateDepthChartGlows();
    }
    if (typeof window.IBL_recalculateLineupPreview === 'function') {
        window.IBL_recalculateLineupPreview();
    }

    return false;
}
</script>
JAVASCRIPT;

        echo '</tbody>
            <tfoot>
                <tr>
                    <td colspan="9" class="depth-chart-buttons">
                        <input type="button" value="Reset" onclick="resetDepthChart();" class="depth-chart-reset-btn">
                        <input type="submit" value="Submit Depth Chart" class="depth-chart-submit-btn">
                    </td>
                </tr>
            </tfoot>
        </table></div>';
    }

    /**
     * Render the saved depth chart dropdown selector
     *
     * @param list<array{id: int, label: string, isActive: bool}> $options
     */
    public function renderSavedDepthChartDropdown(array $options, string $currentLiveLabel): void
    {
        echo '<div class="saved-dc-dropdown-container">';
        echo '<label for="saved-dc-select" class="saved-dc-label">Load Saved Depth Chart:</label>';
        echo '<div class="saved-dc-select-wrapper">';
        echo '<select id="saved-dc-select" class="saved-dc-select">';
        echo '<option value="0">' . HtmlSanitizer::e($currentLiveLabel) . '</option>';
        foreach ($options as $option) {
            echo '<option value="' . (int) $option['id'] . '">' . HtmlSanitizer::e($option['label']) . '</option>';
        }
        echo '</select>';
        echo '<button type="button" id="saved-dc-rename-btn" class="saved-dc-rename-btn hidden" title="Rename selected depth chart">&#9998;</button>';
        echo '</div>';
        echo '<div id="saved-dc-loading" class="saved-dc-loading hidden">Loading...</div>';
        echo '</div>';
    }

    /**
     * @see DepthChartEntryViewInterface::renderMobileView()
     * @param list<PlayerRow> $players
     * @param array<string> $slotNames
     */
    public function renderMobileView(array $players, array $slotNames): void
    {
        echo '<div class="dc-mobile-cards" id="dc-mobile-cards" aria-hidden="true">';

        $depthCount = 1;
        foreach ($players as $player) {
            $this->renderMobilePlayerCard($player, $depthCount);
            $depthCount++;
        }

        echo '<div class="dc-mobile-cards__footer">
            <input type="button" value="Reset" onclick="resetDepthChart();" class="depth-chart-reset-btn">
            <input type="submit" value="Submit Depth Chart" class="depth-chart-submit-btn">
        </div>';
        echo '</div></form>';
    }

    /**
     * Render a single mobile card for a player
     *
     * @param PlayerRow $player Player data from database
     * @param int $depthCount Row counter for form field names
     */
    private function renderMobilePlayerCard(array $player, int $depthCount): void
    {
        $playerName = $player['name'];
        $jsbProduction = $this->service->computeJsbProduction($player);

        $imageUrl = \Player\PlayerImageHelper::getImageUrl((int) $player['pid']);

        echo '<div class="dc-card" data-pid="' . HtmlSanitizer::e($player['pid']) . '" data-pos="' . HtmlSanitizer::e($player['pos']) . '" data-jsb-production="' . HtmlSanitizer::e($jsbProduction) . '" data-quality-score="' . HtmlSanitizer::e($player['quality_score'] ?? 0.0) . '">';

        // Header: photo + pos badge + name + active toggle
        echo '<div class="dc-card__header">';
        echo '<img class="dc-card__photo" src="' . HtmlSanitizer::e($imageUrl) . '" alt="" width="48" height="48" loading="lazy">';
        echo '<span class="dc-card__pos-badge">' . HtmlSanitizer::e($player['pos']) . '</span>';
        echo '<a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=' . HtmlSanitizer::e($player['pid']) . '" class="dc-card__name">' . HtmlSanitizer::e($playerName) . '</a>';

        echo '<input type="hidden" name="pid' . HtmlSanitizer::e($depthCount) . '" value="' . HtmlSanitizer::e($player['pid']) . '" disabled>';
        echo '<input type="hidden" name="Injury' . HtmlSanitizer::e($depthCount) . '" value="' . HtmlSanitizer::e($player['injured'] ?? 0) . '" disabled>';
        echo '<input type="hidden" name="Name' . HtmlSanitizer::e($depthCount) . '" value="' . HtmlSanitizer::e($playerName) . '" disabled>';
        // Active checkbox — native checkbox styled with an orange accent to
        // match the desktop view. Hidden input submits "0" when unchecked; the
        // checkbox submits "1" when checked. Both share the same field name so
        // the form posts the right value regardless of checkbox state.
        echo '<input type="hidden" name="canPlayInGame' . HtmlSanitizer::e($depthCount) . '" value="0" disabled>';
        echo '<input type="checkbox" name="canPlayInGame' . HtmlSanitizer::e($depthCount) . '" value="1" class="dc-card__active-cb"' . ((int) ($player['dc_can_play_in_game'] ?? 0) === 1 ? ' checked' : '') . ' aria-label="Active status for ' . HtmlSanitizer::e($playerName) . '" disabled>';
        echo '</div>';

        // Body — role slots + minutes grid (6 columns). Min sits on the
        // right of the Center column so the PG→C axis reads left-to-right
        // uninterrupted; the Min input is vertically aligned with the C
        // stepper's value element via .dc-card__field--min in CSS.
        echo '<div class="dc-card__body">';
        echo '<div class="dc-card__settings-grid">';

        $depthLabels = ['No', '1st', '2nd', '3rd', '4th', 'ok'];
        foreach (self::POSITION_SLOTS as $slot) {
            $dcValue = (int) ($player[$slot['dbKey']] ?? 0);
            if ($dcValue < 0) {
                $dcValue = 0;
            }
            if ($dcValue > 5) {
                $dcValue = 5;
            }
            $fieldName = $slot['field'] . $depthCount;
            $valueLabel = $depthLabels[$dcValue];
            $slotAriaRaw = $slot['label'] . ' depth for ' . $playerName;

            echo '<div class="dc-card__field">';
            echo '<span class="dc-card__field-label">' . HtmlSanitizer::e($slot['label']) . '</span>';
            echo '<div class="dc-card__stepper">';
            echo '<button type="button" class="dc-card__stepper-arrow dc-card__stepper-arrow--up" aria-label="Previous ' . HtmlSanitizer::e($slotAriaRaw) . '"></button>';
            echo '<span class="dc-card__stepper-value" aria-live="polite">' . HtmlSanitizer::e($valueLabel) . '</span>';
            echo '<button type="button" class="dc-card__stepper-arrow dc-card__stepper-arrow--down" aria-label="Next ' . HtmlSanitizer::e($slotAriaRaw) . '"></button>';
            echo '</div>';
            echo '<select name="' . HtmlSanitizer::e($fieldName)
                . '" class="dc-card__field-select" aria-label="'
                . HtmlSanitizer::e($slotAriaRaw) . '" disabled>';
            $this->renderPositionDepthOptions($dcValue);
            echo '</select></div>';
        }

        // Minutes — number input constrained to 0-40, rendered as the 6th
        // (rightmost) column. Visually wrapped in the same stepper chrome as
        // the role slots so taps on the up/down arrows increment/decrement
        // the minutes value. The input itself remains editable so the GM
        // can still type a precise value directly.
        $minAriaRaw = 'Minutes for ' . $playerName;
        echo '<div class="dc-card__field dc-card__field--min">';
        echo '<span class="dc-card__field-label">Min</span>';
        echo '<div class="dc-card__stepper">';
        echo '<button type="button" class="dc-card__stepper-arrow dc-card__stepper-arrow--up" aria-label="Increase '
            . HtmlSanitizer::e($minAriaRaw) . '"></button>';
        echo '<input type="number" name="min' . HtmlSanitizer::e($depthCount)
            . '" value="' . HtmlSanitizer::e($player['dc_minutes'] ?? 0)
            . '" min="0" max="40" step="1" class="dc-minutes-input dc-card__stepper-input" aria-label="'
            . HtmlSanitizer::e($minAriaRaw) . '" disabled>';
        echo '<button type="button" class="dc-card__stepper-arrow dc-card__stepper-arrow--down" aria-label="Decrease '
            . HtmlSanitizer::e($minAriaRaw) . '"></button>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // end settings grid
        echo '</div>'; // end body
        echo '</div>'; // end card
    }
}
