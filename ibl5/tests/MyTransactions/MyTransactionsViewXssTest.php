<?php

declare(strict_types=1);

namespace Tests\MyTransactions;

use MyTransactions\MyTransactionsView;
use PHPUnit\Framework\TestCase;

final class MyTransactionsViewXssTest extends TestCase
{
    public function testRenderEscapesXssInDynamicValues(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $output = (new MyTransactionsView())->render([
            'teamName' => 'Metros',
            'teamId' => 1,
            'hasTeam' => true,
            'transactions' => [
                ['sid' => '1', 'catid' => '1', 'title' => $xss, 'time' => '2025-01-15 12:00:00'],
            ],
            'pendingTrades' => [
                ['tradeofferid' => 1, 'oppositeTeam' => $xss, 'approval' => $xss],
            ],
            'pendingFaBids' => [
                ['name' => $xss, 'pid' => 10, 'offer1' => 1500, 'offer2' => 0, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0],
            ],
        ]);

        self::assertStringContainsString($escaped, $output);
        self::assertStringNotContainsString($xss, $output);
    }
}
