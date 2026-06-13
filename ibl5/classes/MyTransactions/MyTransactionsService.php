<?php

declare(strict_types=1);

namespace MyTransactions;

use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use League\League;
use MyTransactions\Contracts\MyTransactionsServiceInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use TransactionHistory\Contracts\TransactionHistoryRepositoryInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;

/**
 * Orchestrates the My Team Transactions page.
 *
 * Resolves the GM's team server-side from the username, then assembles the
 * team's transaction ledger and outstanding offers by reusing existing
 * repositories. No team input is ever read from the client.
 *
 * @phpstan-import-type MyTransactionsPageData from MyTransactionsServiceInterface
 * @phpstan-import-type PendingTrade from MyTransactionsServiceInterface
 *
 * @see MyTransactionsServiceInterface
 */
class MyTransactionsService implements MyTransactionsServiceInterface
{
    public function __construct(
        private readonly TransactionHistoryRepositoryInterface $transactionRepo,
        private readonly TradeOfferRepositoryInterface $tradeOfferRepo,
        private readonly FreeAgencyRepositoryInterface $faRepo,
        private readonly TeamIdentityRepositoryInterface $teamIdentityRepo,
    ) {
    }

    /**
     * @see MyTransactionsServiceInterface::getPageData()
     *
     * @return MyTransactionsPageData
     */
    public function getPageData(?string $username): array
    {
        $teamName = $this->teamIdentityRepo->getTeamnameFromUsername($username);

        // A logged-in user with no GM link resolves to null; an empty/absent
        // username resolves to the Free Agents sentinel. Neither owns a team, so
        // return empty sections rather than leaking the Free Agents pseudo-team.
        if ($teamName === null || $teamName === League::FREE_AGENTS_TEAM_NAME) {
            return $this->emptyPageData();
        }

        $teamId = $this->teamIdentityRepo->getTidFromTeamname($teamName);
        if ($teamId === null) {
            return $this->emptyPageData();
        }

        return [
            'teamName' => $teamName,
            'teamId' => $teamId,
            'hasTeam' => true,
            'transactions' => $this->transactionRepo->getTransactionsForTeam($teamName),
            'pendingTrades' => $this->collectPendingTrades($teamName),
            'pendingFaBids' => $this->faRepo->getOffersByTeam($teamId),
        ];
    }

    /**
     * Group the team's pending trade offers by offer ID.
     *
     * Reuses the exact in/out predicate from TradingService::groupTradeOffers():
     * a row is relevant only when the team is the sender or receiver. The opposite
     * team is taken from the first relevant row of each offer (a single offer is
     * always between two teams), mirroring the existing trade-review display rather
     * than inventing a per-offer incoming/outgoing direction (a single offer carries
     * items in both directions, so a binary direction would be arbitrary).
     *
     * @return list<PendingTrade>
     */
    private function collectPendingTrades(string $teamName): array
    {
        $offers = [];
        foreach ($this->tradeOfferRepo->getAllTradeOffers() as $row) {
            $from = $row['trade_from'];
            $to = $row['trade_to'];
            if ($from !== $teamName && $to !== $teamName) {
                continue;
            }

            $offerId = $row['tradeofferid'];
            if (isset($offers[$offerId])) {
                continue;
            }

            $offers[$offerId] = [
                'tradeofferid' => $offerId,
                'oppositeTeam' => ($from === $teamName) ? $to : $from,
                'approval' => $row['approval'],
            ];
        }

        return array_values($offers);
    }

    /**
     * @return MyTransactionsPageData
     */
    private function emptyPageData(): array
    {
        return [
            'teamName' => '',
            'teamId' => 0,
            'hasTeam' => false,
            'transactions' => [],
            'pendingTrades' => [],
            'pendingFaBids' => [],
        ];
    }
}
