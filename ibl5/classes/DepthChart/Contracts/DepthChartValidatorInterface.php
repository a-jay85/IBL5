<?php

declare(strict_types=1);

namespace DepthChart\Contracts;

/**
 * DepthChartValidatorInterface - Contract for depth chart submission validation
 * 
 * Validates complete depth chart submissions against business rules,
 * including position depth requirements, active player counts, and constraints.
 * All validation errors are collected internally and can be retrieved for display.
 */
interface DepthChartValidatorInterface
{
    /**
     * Validate a complete depth chart submission against all business rules
     * 
     * Performs comprehensive validation based on season phase:
     * 
     * **Regular Season Requirements:**
     * - Exactly 12 active players in lineup
     * - At least 3 players per position slot
     * 
     * **Playoff Requirements:**
     * - 10-12 active players in lineup (flexible)
     * - At least 2 players per position slot
     * 
     * **All Phases:**
     * - No player can be starting (depth = 1) at multiple positions
     * 
     * All errors are collected internally and can be retrieved via getErrors() or getErrorMessagesHtml().
     * 
     * @param array $depthChartData Processed depth chart data with these keys:
     *                              - activePlayers: int (total count)
     *                              - pos_1: int (PG slot count)
     *                              - pos_2: int (SG slot count)
     *                              - pos_3: int (SF slot count)
     *                              - pos_4: int (PF slot count)
     *                              - pos_5: int (C slot count)
     *                              - hasStarterAtMultiplePositions: bool
     *                              - nameOfProblemStarter: string (player name if above is true)
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
     * Get all validation errors from the last validate() call
     * 
     * Returns array of error arrays, each containing:
     * - type: Error category (e.g., 'active_players_min', 'position_depth', 'multiple_starting_positions')
     * - message: User-facing error summary (HTML may be present, not yet escaped)
     * - detail: Actionable guidance for user (HTML may be present, not yet escaped)
     * 
     * @return array<int, array{type: string, message: string, detail: string}> Array of error arrays (empty if no errors)
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
     * - Uses legacy font tags: `<font color=red><b>{message}</b></font>`
     * - Detail text follows each error with paragraph breaks
     * - Centered text with `<center>` tags
     * - Ready to echo directly without additional escaping
     * 
     * **Important Behaviors:**
     * - Returns empty string if getErrors() is empty
     * - HTML is pre-formatted and ready for display
     * - Uses legacy HTML elements (font, center) for backward compatibility
     */
    public function getErrorMessagesHtml(): string;
}
