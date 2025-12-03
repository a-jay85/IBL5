<?php

declare(strict_types=1);

namespace DepthChart\Contracts;

/**
 * DepthChartSubmissionHandlerInterface - Contract for depth chart submission orchestration
 * 
 * Orchestrates the complete depth chart submission workflow:
 * processing user input, validating against business rules,
 * persisting to database, and generating confirmations.
 */
interface DepthChartSubmissionHandlerInterface
{
    /**
     * Handle complete depth chart form submission
     * 
     * Orchestrates the full submission workflow:
     * 1. Extract and sanitize team name from POST data
     * 2. Process raw form data into structured depth chart
     * 3. Validate against current season phase requirements
     * 4. If valid: Save to database, generate CSV file, send email confirmation
     * 5. If invalid: Display error messages with submitted data for correction
     * 6. Render result page (success or error) with confirmation table
     * 
     * **Workflow Steps:**
     * - Team name validation (required, sanitized)
     * - Data processing via DepthChartProcessor
     * - Business rule validation via DepthChartValidator
     * - Database updates via DepthChartRepository (if valid)
     * - CSV file generation and email (if valid)
     * - HTML result page rendering
     * 
     * **Error Handling:**
     * - Team name missing: Early exit with error message
     * - Validation failures: Display errors with submitted data table
     * - Database errors: Logged implicitly via repository (caller handles display)
     * - File write errors: Error message echoed to output
     * 
     * @param array<string, mixed> $postData Raw POST data from form submission ($_POST)
     * @return void Renders HTML response (success or error page)
     * 
     * **Important Behaviors:**
     * - Does NOT return value (renders directly to output)
     * - All error messages output directly to stdout
     * - Uses Season object to determine phase requirements
     * - Sends email only if not on localhost
     * - File operations validate path to prevent directory traversal
     */
    public function handleSubmission(array $postData): void;
}
