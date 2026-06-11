<?php

declare(strict_types=1);

namespace Negotiation\Contracts;

/**
 * NegotiationServiceInterface — Contract for the contract-negotiation workflow.
 *
 * Renders the negotiation offer flow for a player: header, free-agency and
 * eligibility validation, demand calculation, and the negotiation form (or a
 * renegotiation demands breakdown when ownership is bypassed).
 *
 * @package Negotiation\Contracts
 */
interface NegotiationServiceInterface
{
    /**
     * Builds the full negotiation offer output for a player as rendered HTML.
     *
     * All failure paths (player not found, free agency active, ineligible) are
     * caught internally and returned as rendered error views — this method does
     * not throw.
     *
     * @param int    $playerID        Player ID to negotiate with (positive integer)
     * @param string $userTeamName    Name of the negotiating team
     * @param string $prefix          Form action/route prefix used by the rendered form
     * @param bool   $bypassOwnership When true, runs the renegotiation path
     *                                (skips ownership check, renders demands breakdown)
     * @return string Rendered HTML: header plus either an error view, the
     *                negotiation form, or the renegotiation demands breakdown
     */
    public function processNegotiation(int $playerID, string $userTeamName, string $prefix, bool $bypassOwnership = false): string;
}
