<?php

declare(strict_types=1);

namespace TradeBlock\Contracts;

/**
 * TradeBlockProcessorInterface - Write-path orchestration for the edit form.
 *
 * Owns the IDOR defense: the submitted checkbox set is reconciled against the
 * GM's OWN current roster (resolved server-side), so a forged pid belonging to
 * another team never reaches a write.
 */
interface TradeBlockProcessorInterface
{
    /**
     * Reconcile the submitted on-block set against the resolved team's roster
     * and persist the seeking note.
     *
     * @param list<int> $postedPids       checkbox pids submitted by the client
     * @param array<int, string> $postedNotes pid => per-player note
     * @param string $seekingNote         team free-text seeking note
     * @return array{success: bool, result?: string, error?: string}
     */
    public function processEdit(int $resolvedTeamId, array $postedPids, array $postedNotes, string $seekingNote): array;
}
