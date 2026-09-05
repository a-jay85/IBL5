<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeDecisionServiceInterface — exit-free orchestrator for the Trading
 * accept/reject POST decisions.
 *
 * Each method owns the full post-CSRF decision for one endpoint: offer lookup,
 * the authz/IDOR gate, the mutation, audit logging, and notification. Instead of
 * redirecting (which exits the process), each RETURNS a verdict:
 *
 *   array{success: bool, redirect: string}
 *
 * The controller's only remaining job is to hand `redirect` to
 * {@see \Utilities\HtmxHelper::redirect()}. This is what makes "a non-party is
 * refused AND no mutation occurs" a plain PHPUnit assertion rather than an
 * E2E-only property.
 *
 * SECURITY — $actingTeam is ALWAYS resolved from the authenticated session via
 * TradingController::resolveActingTeam(). It must never be taken from POST.
 * $teamRejecting / $teamReceiving ARE POST-supplied display values and are used
 * ONLY to address the Discord DM — never as an authorization input.
 *
 * Accept is added by the stacked follow-up (unit 1d) as
 * `accept(int $offerId, string $actingTeam): array`.
 */
interface TradeDecisionServiceInterface
{
    /**
     * Reject (decline) a trade offer on behalf of the acting GM's team.
     *
     * @param int    $offerId       Trade offer ID (already parsed by the caller)
     * @param string $actingTeam    Session-derived team name; '' when unresolved
     * @param string $teamRejecting POST display value — Discord DM addressing only
     * @param string $teamReceiving POST display value — Discord DM addressing only
     * @return array{success: bool, redirect: string}
     */
    public function reject(
        int $offerId,
        string $actingTeam,
        string $teamRejecting,
        string $teamReceiving
    ): array;
}
