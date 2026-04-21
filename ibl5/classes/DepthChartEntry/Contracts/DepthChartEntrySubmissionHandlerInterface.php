<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

/**
 * DepthChartEntrySubmissionHandlerInterface - Contract for depth chart submission orchestration
 *
 * Orchestrates the complete depth chart submission workflow:
 * processing user input, validating against business rules,
 * and persisting to database. Emits no direct output — the caller
 * uses the return value + `$_SESSION['_ibl_depth_chart_flash']` to
 * drive a Post-Redirect-Get response.
 */
interface DepthChartEntrySubmissionHandlerInterface
{
    /**
     * Handle complete depth chart form submission.
     *
     * Flow:
     * 1. Validate `Team_Name` is present.
     * 2. Process raw form data via DepthChartEntryProcessor.
     * 3. Validate against current season phase via DepthChartEntryValidator.
     * 4. On success: save to DB, write CSV file, email confirmation, snapshot.
     * 5. On failure: stash `$_SESSION['_ibl_depth_chart_flash']` with `errors_html`
     *    and the raw `post_data` so the next GET can re-render the form
     *    pre-populated with the user's in-flight edits.
     *
     * @param array<string, mixed> $postData Raw POST data from form submission ($_POST).
     * @return bool True on success (caller should flash_success + redirect).
     *              False on any failure — flash is stashed for the redirected GET.
     */
    public function handleSubmission(array $postData): bool;
}
