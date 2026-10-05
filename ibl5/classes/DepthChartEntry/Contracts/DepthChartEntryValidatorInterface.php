<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

use Validation\ValidationResultWithContext;

/**
 * DepthChartEntryValidatorInterface - Contract for depth chart submission validation
 *
 * Validates complete depth chart submissions against business rules,
 * including position depth requirements, active player counts, and constraints.
 * All validation errors are collected internally and can be retrieved for display.
 *
 * @phpstan-import-type ProcessedPlayerData from DepthChartEntryProcessorInterface
 *
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
     * Errors are returned on the result; the validator holds no state between calls.
     * 
     * @param ValidatorInput $depthChartData Processed depth chart data
     * @param string $phase Season phase ('Playoffs' or 'Regular Season')
     * @return ValidationResultWithContext<null> Valid when getErrors() is empty; each ValidationError carries type, message, detail
     * 
     * **Important Behaviors:**
     * - Does NOT throw exceptions - errors are returned on the result
     * - Each validation failure adds one ValidationError to the result
     * - Phase comparison is case-sensitive ('Playoffs' vs 'Regular Season')
     */
    public function validate(array $depthChartData, string $phase): ValidationResultWithContext;

    /**
     * Validate that a submission covers the session team's roster exactly.
     *
     * Rejects (one error per category, offending pids listed in the message):
     * - roster_foreign_pid:   a submitted pid that is not on the roster (includes pid 0)
     * - roster_duplicate_pid: a pid submitted more than once
     * - roster_missing_pid:   a roster pid absent from the submission
     *
     * Stateless; the returned result carries one ValidationError per category, in the order foreign, duplicate, missing.
     *
     * @param list<int> $submittedPids pids extracted from POST rows, in form order
     * @param list<int> $rosterPids    pids returned by getPlayersOnTeam() for the session team
     * @return ValidationResultWithContext<null> Valid only when the two sets are equal and the submission has no repeats
     */
    public function validateRoster(array $submittedPids, array $rosterPids): ValidationResultWithContext;
}
