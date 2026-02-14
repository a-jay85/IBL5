<?php

declare(strict_types=1);

namespace Updater;

use Utilities\HtmlSanitizer;

/**
 * UpdaterView - Renders the admin update page with progressive output
 *
 * Provides structured HTML rendering for the updateAllTheThings script,
 * replacing raw inline HTML with styled, XSS-safe output.
 */
class UpdaterView
{
    /**
     * Render the page opening: doctype, head with fonts/stylesheet, body open, card header
     *
     * @param string $stylesheetPath Path to the compiled stylesheet
     * @return string HTML page opening
     */
    public function renderPageOpen(string $stylesheetPath): string
    {
        /** @var string $safeStylesheet */
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
            . '<div class="ibl-card">'
            . '<div class="ibl-card__header">'
            . '<h1 class="ibl-card__title">Update All The Things</h1>'
            . '</div>'
            . '<div class="ibl-card__body">';
    }

    /**
     * Render an initialization confirmation line
     *
     * @param string $label Description of what was initialized
     * @return string HTML init status line
     */
    public function renderInitStatus(string $label): string
    {
        /** @var string $safeLabel */
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
        /** @var string $safeLabel */
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
        /** @var string $safeLabel */
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);

        $html = '<div class="updater-step updater-step--success">'
            . '<span class="updater-step__icon">&#10003;</span>'
            . '<span class="updater-step__label">' . $safeLabel . '</span>';

        if ($detail !== '') {
            /** @var string $safeDetail */
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
        /** @var string $safeLabel */
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
        /** @var string $safeError */
        $safeError = HtmlSanitizer::safeHtmlOutput($error);

        return '<div class="updater-step updater-step--error">'
            . '<span class="updater-step__icon">&#10007;</span>'
            . '<span class="updater-step__label">' . $safeLabel . '</span>'
            . '<div class="ibl-alert ibl-alert--error">' . $safeError . '</div>'
            . '</div>';
    }

    /**
     * Render captured output in a collapsible terminal-style log area
     *
     * @param string $capturedOutput Raw output captured via ob_get_clean()
     * @return string HTML log area (empty string if no output)
     */
    public function renderLog(string $capturedOutput): string
    {
        $trimmed = trim($capturedOutput);
        if ($trimmed === '') {
            return '';
        }

        /** @var string $safeOutput */
        $safeOutput = HtmlSanitizer::safeHtmlOutput($trimmed);

        return '<details class="updater-log">'
            . '<summary class="updater-log__toggle">View log output</summary>'
            . '<div class="updater-log__content">'
            . '<pre class="updater-log__pre">' . $safeOutput . '</pre>'
            . '</div>'
            . '</details>';
    }

    /**
     * Render summary badges showing success/error counts
     *
     * @param int $successCount Number of successful steps
     * @param int $errorCount Number of failed steps
     * @return string HTML summary section
     */
    public function renderSummary(int $successCount, int $errorCount): string
    {
        $html = '<div class="updater-summary">';

        if ($errorCount === 0) {
            $html .= '<span class="ibl-badge ibl-badge--success">'
                . $successCount . ' step' . ($successCount !== 1 ? 's' : '') . ' completed</span>';
        } else {
            $html .= '<span class="ibl-badge ibl-badge--success">'
                . $successCount . ' succeeded</span>'
                . '<span class="ibl-badge ibl-badge--error">'
                . $errorCount . ' failed</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render the page closing: "Return to IBL" button and close tags
     *
     * @return string HTML page closing
     */
    public function renderPageClose(): string
    {
        return '</div></div>'
            . '<a href="/ibl5/index.php" class="ibl-btn updater__return">Return to IBL</a>'
            . '</div>'
            . '</body></html>';
    }
}
