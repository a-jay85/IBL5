<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeExecutionServiceInterface - Policy/authz/validation layer for accepting trades
 *
 * Owns the atomic accept path: derives the set of party teams from an offer's
 * trade_info rows, enforces that the acting GM's team is a party (the authz/IDOR
 * gate), runs N-party cap + roster validation against each party's *resulting*
 * roster, and only then delegates the write to the low-level
 * {@see TradeProcessorInterface::processTrade()} executor.
 *
 * Works identically for 2-party and 3-party (N-party) trades.
 */
interface TradeExecutionServiceInterface
{
    /**
     * Derive the distinct set of party team names for an offer.
     *
     * Union of every trade_info row's trade_from and trade_to, in first-seen
     * order. Reused by both the authz gate and the per-party validation.
     *
     * @param int $offerId Trade offer ID
     * @return list<string> Distinct party team names
     */
    public function deriveParties(int $offerId): array;

    /**
     * Authz / IDOR gate: is the acting GM's team a party to this offer?
     *
     * @param int $offerId Trade offer ID
     * @param string $actingTeam Team name resolved from the authenticated session (never POST)
     * @return bool True if $actingTeam is among the offer's parties
     */
    public function assertActingTeamIsParty(int $offerId, string $actingTeam): bool;

    /**
     * Run authz + N-party validation, then execute the trade atomically.
     *
     * Sequence: authz gate -> build per-party cap/roster deltas from the offer's
     * trade_info rows -> validate every party's resulting cap and roster -> on
     * success call processTrade() (which owns the write transaction). On any
     * failure processTrade() is never entered.
     *
     * @param int $offerId Trade offer ID
     * @param string $actingTeam Team name resolved from the authenticated session
     * @return array{success: bool, error?: string, errors?: list<string>, storytext?: string, storytitle?: string}
     */
    public function validateAndExecute(int $offerId, string $actingTeam): array;
}
