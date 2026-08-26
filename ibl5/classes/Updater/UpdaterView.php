<?php

declare(strict_types=1);

namespace Updater;

use PageLayout\PageLayout;
use Security\HtmlSanitizer;

/**
 * UpdaterView - Renders the admin update page as a clean operations log
 *
 * Provides structured HTML rendering for the updateAllTheThings script,
 * with grouped sections and minimal visual accents.
 */
class UpdaterView implements Contracts\UpdaterViewInterface
{
    /**
     * Render the page opening: doctype, head with fonts/stylesheet, body open, title
     *
     * @param string $stylesheetPath Path to the compiled stylesheet
     * @return string HTML page opening
     */
    public function renderPageOpen(string $stylesheetPath): string
    {
        $safeStylesheet = HtmlSanitizer::safeHtmlOutput($stylesheetPath);

        return '<!DOCTYPE html>'
            . '<html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Update All The Things</title>'
            . PageLayout::renderFontPreconnectLinks()
            . '<link rel="stylesheet" href="' . $safeStylesheet . '">'
            . '</head><body>'
            . str_repeat(' ', 1024)
            . '<div class="updater">'
            . '<h1 class="updater__title">Update All The Things</h1>';
    }

    /**
     * Open a labelled section group
     *
     * @param string $label Section heading (e.g. "Initialization", "Pipeline")
     * @return string HTML section opening with label
     */
    public function renderSectionOpen(string $label): string
    {
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);

        return '<section class="updater-section">'
            . '<div class="updater-section__label">' . $safeLabel . '</div>';
    }

    /**
     * Close a section group
     *
     * @return string HTML section closing tag
     */
    public function renderSectionClose(): string
    {
        return '</section>';
    }

    /**
     * Render an initialization confirmation line
     *
     * @param string $label Description of what was initialized
     * @return string HTML init status line
     */
    public function renderInitStatus(string $label): string
    {
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);

        return '<div class="updater-init">'
            . '<span class="updater-init__check">&#10003;</span> '
            . $safeLabel
            . '</div>';
    }

    /**
     * Render a step-in-progress indicator with spinner
     *
     * @param string $label Step description
     * @return string HTML step start indicator
     */
    public function renderStepStart(string $label): string
    {
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);

        return '<div class="updater-step updater-step--running">'
            . '<span class="updater-step__spinner"></span>'
            . '<span class="updater-step__label">' . $safeLabel . '</span>'
            . '</div>';
    }

    /**
     * Render a completed step with green checkmark
     *
     * @param string $label Step description
     * @param string $detail Optional detail text (e.g. "3 active DCs extended")
     * @return string HTML step complete indicator
     */
    public function renderStepComplete(string $label, string $detail = ''): string
    {
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);

        $html = '<div class="updater-step updater-step--success">'
            . '<span class="updater-step__icon">&#10003;</span>'
            . '<span class="updater-step__label">' . $safeLabel . '</span>';

        if ($detail !== '') {
            $safeDetail = HtmlSanitizer::safeHtmlOutput($detail);
            $html .= '<span class="updater-step__detail">' . $safeDetail . '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a failed step with red X and error message
     *
     * @param string $label Step description
     * @param string $error Error message
     * @return string HTML step error indicator
     */
    public function renderStepError(string $label, string $error): string
    {
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
        $safeError = HtmlSanitizer::safeHtmlOutput($error);

        return '<div class="updater-step updater-step--error">'
            . '<span class="updater-step__icon">&#10007;</span>'
            . '<span class="updater-step__label">' . $safeLabel . '</span>'
            . '<div class="updater-step__error">' . $safeError . '</div>'
            . '</div>';
    }

    /**
     * Render captured output as muted inline log content
     *
     * Output is rendered as HTML (not escaped) because it originates from
     * trusted internal updater classes, not from user input.
     *
     * @param string $capturedOutput Raw HTML output captured via ob_get_clean()
     * @return string HTML log content (empty string if no output)
     */
    public function renderLog(string $capturedOutput): string
    {
        $trimmed = trim($capturedOutput);
        if ($trimmed === '') {
            return '';
        }

        return '<div class="updater-log">' . $trimmed . '</div>';
    }

    /**
     * @see UpdaterViewInterface::renderCollapsibleLog()
     */
    public function renderCollapsibleLog(string $capturedOutput, string $summaryLabel = 'Click for details'): string
    {
        $trimmed = trim($capturedOutput);
        if ($trimmed === '') {
            return '';
        }

        $safeSummary = HtmlSanitizer::safeHtmlOutput($summaryLabel);

        return '<div class="updater-log">'
            . '<details class="updater-details">'
            . '<summary class="updater-details__summary">' . $safeSummary . '</summary>'
            . '<div class="updater-details__content">' . $trimmed . '</div>'
            . '</details>'
            . '</div>';
    }

    /**
     * Render trusted HTML inline within the pipeline's visual framework.
     *
     * Used for embedding output from other View classes (BoxscoreView, LeagueConfigView)
     * that generate their own HTML. Content is wrapped in the updater-log div to match
     * the pipeline's styling.
     *
     * @param string $trustedHtml HTML from a trusted internal view class
     * @return string Wrapped HTML (empty string if input is blank)
     */
    public function renderInlineHtml(string $trustedHtml): string
    {
        $trimmed = trim($trustedHtml);
        if ($trimmed === '') {
            return '';
        }

        return '<div class="updater-log">' . $trimmed . '</div>';
    }

    /**
     * Render summary status line showing success/error counts
     *
     * @param int $successCount Number of successful steps
     * @param int $errorCount Number of failed steps
     * @return string HTML summary section
     */
    public function renderSummary(int $successCount, int $errorCount): string
    {
        $html = '<div class="updater-summary">';

        if ($errorCount === 0) {
            $html .= '<span class="updater-summary__status updater-summary__status--success">'
                . $successCount . ' step' . ($successCount !== 1 ? 's' : '') . ' completed</span>';
        } else {
            $html .= '<span class="updater-summary__status updater-summary__status--success">'
                . $successCount . ' succeeded</span>'
                . '<span class="updater-summary__status updater-summary__status--error">'
                . $errorCount . ' failed</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a step's operator messages as a flat list of lines.
     *
     * Messages prefixed "ERROR: " render highlighted; everything else renders plain.
     * No counts and no <details> wrappers: the per-step result boxes (e.g. Boxscore's
     * "Parse Results" card) already carry the real totals, and the old
     * "N files processed" label counted message strings, not files.
     *
     * @param list<string> $messages Messages to display
     * @param int $errorCount Number of errors reported by the result object
     * @return string HTML message list (empty string if nothing to show)
     */
    public function renderMessageLog(array $messages, int $errorCount): string
    {
        if ($messages === [] && $errorCount === 0) {
            return '';
        }

        $hasErrorLines = false;
        $html = '<div class="updater-log">';

        foreach ($messages as $message) {
            if (str_starts_with($message, 'ERROR: ')) {
                $hasErrorLines = true;
                $html .= '<p class="text-error">' . HtmlSanitizer::e($message) . '</p>';
            } else {
                $html .= '<p>' . HtmlSanitizer::e($message) . '</p>';
            }
        }

        // A non-zero count with no ERROR-prefixed lines means the step counted errors
        // it never captured text for (e.g. JsbImportResult's counter). Without this
        // line that signal would vanish entirely.
        if ($errorCount > 0 && !$hasErrorLines) {
            $html .= '<p class="text-error">'
                . $errorCount . ' error' . ($errorCount !== 1 ? 's' : '')
                . ' reported, but details were not captured. '
                . 'Check the PHP error log for more information.</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render the page closing: "Return to IBL" link and close tags
     *
     * @return string HTML page closing
     */
    public function renderPageClose(): string
    {
        return '<a href="/ibl5/index.php" class="ibl-btn updater__return">Return to IBL</a>'
            . '</div>'
            . '</body></html>';
    }
}
