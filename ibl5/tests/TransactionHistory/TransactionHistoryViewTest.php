<?php

declare(strict_types=1);

namespace Tests\TransactionHistory;

use PHPUnit\Framework\TestCase;
use TransactionHistory\Contracts\TransactionHistoryViewInterface;
use TransactionHistory\TransactionHistoryView;

/**
 * @covers \TransactionHistory\TransactionHistoryView
 */
class TransactionHistoryViewTest extends TestCase
{
    private TransactionHistoryView $view;

    protected function setUp(): void
    {
        $this->view = new TransactionHistoryView();
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(TransactionHistoryViewInterface::class, $this->view);
    }

    public function testRenderOutputsTable(): void
    {
        $data = self::createRenderData([
            'transactions' => [self::createTransactionRow()],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    public function testRenderShowsTitle(): void
    {
        $html = $this->view->render(self::createRenderData());

        $this->assertStringContainsString('Transaction History', $html);
    }

    public function testRenderShowsFilterForm(): void
    {
        $html = $this->view->render(self::createRenderData());

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('<select', $html);
    }

    public function testRenderShowsCategoryOptions(): void
    {
        $html = $this->view->render(self::createRenderData());

        $this->assertStringContainsString('Trades', $html);
    }

    public function testRenderShowsEmptyStateWhenNoTransactions(): void
    {
        $html = $this->view->render(self::createRenderData([
            'transactions' => [],
        ]));

        $this->assertStringContainsString('No transactions found', $html);
    }

    public function testRenderShowsTransactionTitle(): void
    {
        $data = self::createRenderData([
            'transactions' => [self::createTransactionRow(['title' => 'Test Trade'])],
        ]);

        $html = $this->view->render($data);

        $this->assertStringContainsString('Test Trade', $html);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{transactions: array<int, array{sid: string, catid: string, title: string, time: string}>, categories: array<int, string>, availableYears: array<int, int>, monthNames: array<int, string>, selectedCategory: int, selectedYear: int, selectedMonth: int}
     */
    private static function createRenderData(array $overrides = []): array
    {
        $defaults = [
            'transactions' => [],
            'categories' => [1 => 'Waiver Pool Moves', 2 => 'Trades'],
            'availableYears' => [2024, 2023],
            'monthNames' => [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'],
            'selectedCategory' => 0,
            'selectedYear' => 0,
            'selectedMonth' => 0,
        ];

        /** @var array{transactions: array<int, array{sid: string, catid: string, title: string, time: string}>, categories: array<int, string>, availableYears: array<int, int>, monthNames: array<int, string>, selectedCategory: int, selectedYear: int, selectedMonth: int} */
        return array_merge($defaults, $overrides);
    }

    /**
     * @param array<string, string> $overrides
     * @return array{sid: string, catid: string, title: string, time: string}
     */
    private static function createTransactionRow(array $overrides = []): array
    {
        /** @var array{sid: string, catid: string, title: string, time: string} */
        return array_merge([
            'sid' => '1',
            'catid' => '2',
            'title' => 'Test Trade',
            'time' => '2024-01-15 12:00:00',
        ], $overrides);
    }
}
