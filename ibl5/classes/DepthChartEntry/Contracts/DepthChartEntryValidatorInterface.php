<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

/**
 * DepthChartEntryValidatorInterface - Contract for depth chart submission validation
 *
 * Validates complete depth chart submissions against business rules,
 * including position depth requirements, active player counts, and constraints.
 * All validation errors are collected internally and can be retrieved for display.
 *
 * @phpstan-import-type ProcessedPlayerData from DepthChartEntryProcessorInterface
 *
 * @phpstan-type ValidationError array{type: string, message: string, detail: string}
 * @phpstan-type ValidatorInput array{playerData?: list<ProcessedPlayerData>, activePlayers: int, pos_1: int, pos_2: int, pos_3: int, pos_4: int, pos_5: int, hasStarterAtMultiplePositions: bool, nameOfProblemStarter: string}
 */
interface DepthChartEntryValidatorInterface
{
    /**
     * Validate a complete depth chart submission against all business rules
     * 
     * Performs validation based on season phase:
     *
     * **Regular Season Requirements:**
     * - Exactly 12 active players in lineup
     *
     * **Playoff Requirements:**
     * - 10-12 active players in lineup (flexible)
     * 
     * All errors are collected internally and can be retrieved via getErrors() or getErrorMessagesHtml().
     * 
     * @param ValidatorInput $depthChartData Processed depth chart data
     * @param string $phase Season phase ('Playoffs' or 'Regular Season')
     * @return bool True if all validations pass, false if any violation detected
     * 
     * **Important Behaviors:**
     * - Does NOT throw exceptions - errors are collected internally
     * - Errors can be retrieved via getErrors() or getErrorMessagesHtml()
     * - Each validation failure adds one error array to internal errors list
     * - Phase comparison is case-sensitive ('Playoffs' vs 'Regular Season')
     */
    public function validate(array $depthChartData, string $phase): bool;

    /**
     * Validate that a submission covers the session team's roster exactly.
     *
     * Rejects (one error per category, offending pids listed in the message):
     * - roster_foreign_pid:   a submitted pid that is not on the roster (includes pid 0)
     * - roster_duplicate_pid: a pid submitted more than once
     * - roster_missing_pid:   a roster pid absent from the submission
     *
     * Resets the error list first (same contract as validate()); read errors via
     * getErrors() / getErrorMessagesHtml() before calling validate(), which resets again.
     *
     * @param list<int> $submittedPids pids extracted from POST rows, in form order
     * @param list<int> $rosterPids    pids returned by getPlayersOnTeam() for the session team
     * @return bool True only when the two sets are equal and the submission has no repeats
     */
    public function validateRoster(array $submittedPids, array $rosterPids): bool;

    /**
     * Get all validation errors from the last validate() call
     * 
     * Returns array of error arrays, each containing:
     * - type: Error category (e.g., 'active_players_min', 'position_depth', 'multiple_starting_positions')
     * - message: User-facing error summary (HTML may be present, not yet escaped)
     * - detail: Actionable guidance for user (HTML may be present, not yet escaped)
     * 
     * @return list<ValidationError> Array of error arrays (empty if no errors)
     * 
     * **Important Behaviors:**
     * - Returns empty array if validate() returned true
     * - Each error is an associative array with 'type', 'message', 'detail' keys
     * - Error messages may contain HTML entities (e.g., "&mdash;")
     * - Caller is responsible for HTML escaping if displaying to users
     */
    public function getErrors(): array;

    /**
     * Get validation errors formatted as HTML for display
     * 
     * Renders all collected errors as a formatted HTML string suitable for display.
     * Each error is presented with red text and includes both the message and detail.
     * 
     * @return string HTML-formatted error display (empty string if no errors)
     * 
     * **HTML Format:**
     * - Uses `<strong>` and `<span>` for error formatting
     * - Detail text follows each error with `<div>` wrappers
     * - Centered text via CSS class
     * - Ready to echo directly without additional escaping
     * 
     * **Important Behaviors:**
     * - Returns empty string if getErrors() is empty
     * - HTML is pre-formatted and ready for display
     * - Uses legacy HTML elements (font, center) for backward compatibility
     */
    public function getErrorMessagesHtml(): string;
}
