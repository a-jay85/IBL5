<?php

declare(strict_types=1);

namespace CapWhatIf;

use CapWhatIf\Contracts\CapWhatIfViewInterface;
use Security\HtmlSanitizer;

/**
 * CapWhatIfView - HTML rendering for the cap "what-if" sandbox.
 *
 * Emits a GET form (waive select + signing inputs, no CSRF token) and a
 * baseline-vs-scenario result table reusing the shared table classes. Every
 * dynamic value is escaped via {@see HtmlSanitizer::e()}; over-cap scenario
 * years carry the shared {@see self::OVER_CAP_FLAG_CLASS} highlight class.
 *
 * @phpstan-import-type ScenarioResult from \CapWhatIf\Contracts\CapWhatIfServiceInterface
 *
 * @see CapWhatIfViewInterface
 */
class CapWhatIfView implements CapWhatIfViewInterface
{
    /** Shared cell-highlight class flagging a scenario year that exceeds the hard cap. */
    private const OVER_CAP_FLAG_CLASS = 'ibl-stat-highlight';

    /**
     * @see CapWhatIfViewInterface::render()
     *
     * @param ScenarioResult $scenarioData
     * @param list<array<string, mixed>> $rosterPlayers
     */
    public function render(array $scenarioData, array $rosterPlayers, int $beginningYear, int $endingYear): string
    {
        $html = '';
        $html .= '<h2 class="ibl-title">Cap Calculator</h2>';
        $html .= $this->renderForm($scenarioData, $rosterPlayers);

        if ($scenarioData['waivedName'] !== null && $scenarioData['waivedName'] !== '') {
            $html .= '<p>Waiving: ' . HtmlSanitizer::e($scenarioData['waivedName']) . '</p>';
        }

        $html .= $this->renderResultTable($scenarioData, $beginningYear, $endingYear);

        return $html;
    }

    /**
     * Render the GET form: waive select + signing inputs. No CSRF token (GET,
     * mutates no server state).
     *
     * @param ScenarioResult $scenarioData
     * @param list<array<string, mixed>> $rosterPlayers
     */
    private function renderForm(array $scenarioData, array $rosterPlayers): string
    {
        $html = '<form method="get">';
        $html .= '<input type="hidden" name="name" value="CapWhatIf">';

        $html .= '<label>Waive: <select name="waive">';
        $html .= '<option value="">&mdash; none &mdash;</option>';
        foreach ($rosterPlayers as $player) {
            $pid = $player['pid'] ?? '';
            $name = $player['name'] ?? '';
            $html .= '<option value="' . HtmlSanitizer::e($pid) . '">'
                . HtmlSanitizer::e($name) . '</option>';
        }
        $html .= '</select></label>';

        $html .= '<label>Years: <input type="number" name="years" min="1" max="6" value="'
            . HtmlSanitizer::e($scenarioData['years']) . '"></label>';
        $html .= '<label>Salary: <input type="number" name="salary" min="0" max="'
            . HtmlSanitizer::e(\League\League::HARD_CAP_MAX) . '" value="'
            . HtmlSanitizer::e($scenarioData['salary']) . '"></label>';

        $html .= '<button type="submit">Calculate</button>';
        $html .= '</form>';

        return $html;
    }

    /**
     * Render the per-year baseline-vs-scenario table.
     *
     * @param ScenarioResult $scenarioData
     */
    private function renderResultTable(array $scenarioData, int $beginningYear, int $endingYear): string
    {
        $html = '<div class="sticky-scroll-wrapper"><div class="sticky-scroll-container">';
        $html .= '<table class="sortable ibl-data-table sticky-table">';
        $html .= '<thead><tr>';
        $html .= '<th class="sticky-col sticky-corner">Year</th>';
        $html .= '<th>Baseline Spent</th><th>Baseline Space</th>';
        $html .= '<th>Scenario Spent</th><th>Scenario Space</th>';
        $html .= '</tr></thead><tbody>';

        for ($i = 1; $i <= 6; $i++) {
            $key = 'year' . $i;
            $yearLabel = ($beginningYear + $i - 1) . '-' . ($endingYear + $i - 1);
            $isOverCap = $scenarioData['overCap'][$key] ?? false;
            $scenarioCellClass = $isOverCap ? ' class="' . self::OVER_CAP_FLAG_CLASS . '"' : '';

            $html .= '<tr>';
            $html .= '<td class="sticky-col">' . HtmlSanitizer::e($yearLabel) . '</td>';
            $html .= '<td>' . HtmlSanitizer::e($scenarioData['baseline']['spent'][$key] ?? 0) . '</td>';
            $html .= '<td>' . HtmlSanitizer::e($scenarioData['baseline']['space'][$key] ?? 0) . '</td>';
            $html .= '<td' . $scenarioCellClass . '>' . HtmlSanitizer::e($scenarioData['scenario']['spent'][$key] ?? 0) . '</td>';
            $html .= '<td>' . HtmlSanitizer::e($scenarioData['scenario']['space'][$key] ?? 0) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div></div>';

        return $html;
    }
}
