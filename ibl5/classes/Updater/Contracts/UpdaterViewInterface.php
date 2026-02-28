<?php

declare(strict_types=1);

namespace Updater\Contracts;

/**
 * Contract for the updater page HTML rendering.
 */
interface UpdaterViewInterface
{
    /**
     * Render the page opening: doctype, head, body open, title.
     */
    public function renderPageOpen(string $stylesheetPath): string;

    /**
     * Open a labelled section group.
     */
    public function renderSectionOpen(string $label): string;

    /**
     * Close a section group.
     */
    public function renderSectionClose(): string;

    /**
     * Render an initialization confirmation line.
     */
    public function renderInitStatus(string $label): string;

    /**
     * Render a step-in-progress indicator with spinner.
     */
    public function renderStepStart(string $label): string;

    /**
     * Render a completed step with green checkmark.
     */
    public function renderStepComplete(string $label, string $detail = ''): string;

    /**
     * Render a failed step with red X and error message.
     */
    public function renderStepError(string $label, string $error): string;

    /**
     * Render captured output as muted inline log content.
     */
    public function renderLog(string $capturedOutput): string;

    /**
     * Render trusted HTML inline within the pipeline's visual framework.
     */
    public function renderInlineHtml(string $trustedHtml): string;

    /**
     * Render summary status line showing success/error counts.
     */
    public function renderSummary(int $successCount, int $errorCount): string;

    /**
     * Render a list of messages as an expandable log.
     *
     * @param list<string> $messages
     */
    public function renderMessageLog(array $messages, int $errorCount): string;

    /**
     * Render the page closing: return link and close tags.
     */
    public function renderPageClose(): string;
}
