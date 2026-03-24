<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Integration tests for modules/TransactionHistory/index.php entry point.
 *
 * This module passes raw $_GET directly to $service->getPageData($_GET),
 * which handles type-casting internally via extractFilters().
 */
class TransactionHistoryEntryPointTest extends ModuleEntryPointTestCase
{
    public function testNoFiltersShowsAllTransactions(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('TransactionHistory');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testValidCategoryFilter(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('TransactionHistory', ['cat' => '2']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testNonNumericCategoryIgnored(): void
    {
        // (int)'trades' === 0, which the service treats as "no category filter"
        $this->mockDb->setMockData([]);
        $output = $this->runModule('TransactionHistory', ['cat' => 'trades']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testValidYearAndMonthCombo(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('TransactionHistory', [
            'cat' => '1',
            'year' => '2025',
            'month' => '3',
        ]);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testNonNumericYearIgnored(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('TransactionHistory', ['year' => 'abc']);

        $this->assertNotEmpty($output);
        // (int)'abc' === 0, treated as "no year filter"
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testNegativeYearIgnored(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('TransactionHistory', ['year' => '-1']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_stories');
    }
}
