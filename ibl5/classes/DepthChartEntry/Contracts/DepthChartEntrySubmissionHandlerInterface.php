<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

/**
 * DepthChartEntrySubmissionHandlerInterface - Contract for depth chart submission orchestration
 *
 * Orchestrates the complete depth chart submission workflow:
 * processing user input, validating against business rules,
 * and persisting to database. Emits no direct output — the caller
 * uses the returned result array to drive session flash and
 * Post-Redirect-Get response.
 *
 * @phpstan-type SubmissionResult array{
 *     success: bool,
 *     fileOk: bool,
 *     errorsHtml: string,
 *     postData: array<string, mixed>
 * }
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
     * 5. On failure: return errorsHtml and postData for the caller to stash as flash.
     *
     * @param array<string, mixed> $postData Raw POST data from form submission ($_POST).
     * @return array{success: bool, fileOk: bool, errorsHtml: string, postData: array<string, mixed>}
     */
    public function handleSubmission(array $postData): array;
}
