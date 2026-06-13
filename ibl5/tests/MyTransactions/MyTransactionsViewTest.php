<?php

declare(strict_types=1);

namespace Tests\MyTransactions;

use MyTransactions\MyTransactionsView;
use PHPUnit\Framework\TestCase;

final class MyTransactionsViewTest extends TestCase
{
    public function testNoTeamRendersOnlyEmptyState(): void
    {
        $output = (new MyTransactionsView())->render([
            'teamName' => '',
            'teamId' => 0,
            'hasTeam' => false,
            'transactions' => [],
            'pendingTrades' => [],
            'pendingFaBids' => [],
        ]);

        self::assertStringContainsString('My Team Transactions', $output);
        self::assertStringContainsString('not assigned a team', $output);
        // No sections should be rendered for a teamless user.
        self::assertStringNotContainsString('Outstanding Trade Offers', $output);
        self::assertStringNotContainsString('Transaction History', $output);
    }

    public function testPopulatedRenderShowsAllSections(): void
    {
        $output = (new MyTransactionsView())->render([
            'teamName' => 'Metros',
            'teamId' => 1,
            'hasTeam' => true,
            'transactions' => [
                ['sid' => '5', 'catid' => '1', 'title' => 'Metros sign Player One', 'time' => '2025-01-15 12:00:00'],
            ],
            'pendingTrades' => [
                ['tradeofferid' => 7, 'oppositeTeam' => 'Stars', 'approval' => 'pending'],
            ],
            'pendingFaBids' => [
                ['name' => 'Bid Player', 'pid' => 10, 'offer1' => 1500, 'offer2' => 1600, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0],
            ],
        ]);

        self::assertStringContainsString('Metros sign Player One', $output);
        self::assertStringContainsString('modules.php?name=News&amp;file=article&amp;sid=5', $output);
        self::assertStringContainsString('Stars', $output);
        self::assertStringContainsString('Bid Player', $output);
        self::assertStringContainsString('1500 / 1600', $output);
        self::assertStringContainsString('txn-badge--1', $output);
    }

    public function testEmptySectionsRenderEmptyStates(): void
    {
        $output = (new MyTransactionsView())->render([
            'teamName' => 'Metros',
            'teamId' => 1,
            'hasTeam' => true,
            'transactions' => [],
            'pendingTrades' => [],
            'pendingFaBids' => [],
        ]);

        self::assertStringContainsString('No outstanding trade offers.', $output);
        self::assertStringContainsString('No outstanding free-agent bids.', $output);
        self::assertStringContainsString('No transactions found for your team.', $output);
    }
}
