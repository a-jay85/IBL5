<?php

declare(strict_types=1);

namespace Tests\TransactionHistory;

use PHPUnit\Framework\TestCase;
use TransactionHistory\TransactionHistoryView;

final class TransactionHistoryViewXssTest extends TestCase
{
    public function testRenderEscapesXssInTitleAndCategoryName(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $view = new TransactionHistoryView();
        $output = $view->render([
            'transactions' => [
                ['catid' => '1', 'time' => '2024-01-15 12:00:00', 'title' => $xss, 'sid' => '1'],
            ],
            'categories' => [1 => $xss],
            'availableYears' => [2024],
            'monthNames' => [1 => 'January'],
            'selectedCategory' => 0,
            'selectedYear' => 0,
            'selectedMonth' => 0,
        ]);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }
}
