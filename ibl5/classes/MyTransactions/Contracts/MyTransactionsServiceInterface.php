<?php

declare(strict_types=1);

namespace MyTransactions\Contracts;

/**
 * Service interface for the My Team Transactions module.
 *
 * Assembles the logged-in GM's own team ledger and outstanding offers. Team
 * identity is resolved server-side from the username only — the module never
 * honors a client-supplied team parameter.
 *
 * @phpstan-import-type TransactionRow from \TransactionHistory\Contracts\TransactionHistoryViewInterface
 * @phpstan-import-type TeamOfferRow from \FreeAgency\Contracts\FreeAgencyRepositoryInterface
 *
 * @phpstan-type PendingTrade array{tradeofferid: int, oppositeTeam: string, approval: string}
 * @phpstan-type MyTransactionsPageData array{teamName: string, teamId: int, hasTeam: bool, transactions: array<int, TransactionRow>, pendingTrades: list<PendingTrade>, pendingFaBids: list<TeamOfferRow>}
 */
interface MyTransactionsServiceInterface
{
    /**
     * Assemble the page data for the logged-in GM's own team.
     *
     * Resolves the team from the username server-side. If the user has no GM team
     * link (or resolves to Free Agents), returns hasTeam=false with empty sections.
     *
     * @param string|null $username The logged-in username (from the session/auth layer)
     * @return MyTransactionsPageData
     */
    public function getPageData(?string $username): array;
}
