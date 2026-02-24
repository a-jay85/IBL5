<?php

declare(strict_types=1);

namespace Updater;

use Utilities\HtmlSanitizer;

/**
 * UpdaterView - Renders the admin update page as a clean operations log
 *
 * Provides structured HTML rendering for the updateAllTheThings script,
 * with grouped sections and minimal visual accents.
 */
class UpdaterView
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
            . '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=Barlow:wght@400;500;600;700&display=block" rel="stylesheet">'
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
