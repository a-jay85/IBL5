<?php

declare(strict_types=1);

namespace LeagueConfig;

/**
 * Renders league config import notifications and results.
 */
class LeagueConfigView
{
    /**
     * Render a notification that .lge data is missing for the current season.
     */
    public function renderLgeNeededNotification(int $seasonEndingYear): string
    {
        $beginningYear = $seasonEndingYear - 1;
        $shortEndingYear = substr((string) $seasonEndingYear, 2);
        /** @var string $seasonLabel */
        $seasonLabel = \Utilities\HtmlSanitizer::safeHtmlOutput($beginningYear . '-' . $shortEndingYear);

        return '<div class="ibl-alert ibl-alert--info">'
            . 'No .lge data found for the ' . $seasonLabel . ' season. '
            . 'Place IBL5.lge in the ibl5/ directory and reload to import.'
            . '</div>';
    }

    /**
     * Render the result of a .lge file parse/import operation.
     *
     * @param array{success: bool, season_ending_year: int, teams_stored: int, messages: list<string>, error?: string} $result
     */
    public function renderParseResult(array $result): string
    {
        if (!$result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            /** @var string $errorEscaped */
            $errorEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($error);
            return '<div class="ibl-alert ibl-alert--error">.lge import failed: ' . $errorEscaped . '</div>';
        }

        $seasonEndingYear = $result['season_ending_year'];
        $beginningYear = $seasonEndingYear - 1;
        $shortEndingYear = substr((string) $seasonEndingYear, 2);
        $teamsStored = $result['teams_stored'];

        return '<div class="ibl-alert ibl-alert--success">'
            . '.lge imported: ' . $beginningYear . '-' . $shortEndingYear
            . ' season, ' . $teamsStored . ' teams stored.'
            . '</div>';
    }

    /**
     * Render cross-check discrepancy warnings.
     *
     * @param list<string> $discrepancies
     */
    public function renderCrossCheckResults(array $discrepancies): string
    {
        if ($discrepancies === []) {
            return '';
        }

        $html = '<div class="ibl-alert ibl-alert--warning">'
            . '<strong>League config cross-check warnings:</strong><ul>';

        foreach ($discrepancies as $msg) {
            /** @var string $msgEscaped */
            $msgEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($msg);
            $html .= '<li>' . $msgEscaped . '</li>';
        }

        $html .= '</ul></div>';

        return $html;
    }
}
