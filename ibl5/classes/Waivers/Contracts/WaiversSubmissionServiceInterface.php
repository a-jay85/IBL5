<?php

declare(strict_types=1);

namespace Waivers\Contracts;

/**
 * WaiversSubmissionServiceInterface - Contract for the waiver submission verdict.
 *
 * Hosts the authz decision that previously lived inline in
 * WaiversController::processWaiverSubmission(). The gate returns a verdict rather
 * than calling a redirect, so the security-critical "non-party refused + no
 * mutation" property is unit-testable exit-free (backlog 6.22). The controller
 * becomes a thin verdict -> redirect shim.
 *
 * @package Waivers\Contracts
 */
interface WaiversSubmissionServiceInterface
{
    /**
     * Process a waiver add/waive submission on behalf of the verified acting team.
     *
     * @param array<string, mixed> $postData
     * @param ?string $verifiedTeamName Acting team, derived from the authenticated session — never from POST.
     *                                  Null or empty means the caller could not establish an acting team;
     *                                  the method refuses with a verdict and performs no mutation (IDOR fix D-08).
     * @return array{success: bool, result?: string, error?: string}
     */
    public function submit(array $postData, ?string $verifiedTeamName): array;
}
