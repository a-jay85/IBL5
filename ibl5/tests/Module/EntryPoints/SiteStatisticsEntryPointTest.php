<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * SiteStatistics is a legacy PHP-Nuke module that uses global language
 * constants from language/lang-english.php (loaded by mainfile.php, not
 * get_lang()). We pre-define the ones the view needs.
 */
class SiteStatisticsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $langFile = dirname(__DIR__, 3) . '/language/lang-english.php';
        if (file_exists($langFile)) {
            include_once $langFile;
        }
    }

    /** @return array<string, mixed> */
    private function siteStatsGlobals(): array
    {
        return array_merge($this->dbGlobals(), [
            'startdate' => '01-01-2020',
        ]);
    }

    public function testDefaultOpRendersOverview(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('SiteStatistics', [], [], $this->siteStatsGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('_counter');
    }

    public function testOpStatsRendersDetailedStats(): void
    {
        $this->mockDb->onQuery('_stats_month', [
            ['year' => 2024, 'month' => 6, 'hits' => 1000],
        ]);
        $this->mockDb->onQuery('_stats_date', [
            ['year' => 2024, 'month' => 6, 'date' => 15, 'hits' => 100],
        ]);
        $this->mockDb->onQuery('_stats_hour', [
            ['year' => 2024, 'month' => 6, 'date' => 15, 'hour' => 12, 'hits' => 50],
        ]);
        $this->mockDb->onQuery('_stats_year', [
            ['year' => 2024, 'hits' => 5000],
        ]);
        $this->mockDb->setMockData([['count' => 10000, 'type' => 'total', 'var' => 'hits']]);

        $output = $this->runModule('SiteStatistics', ['op' => 'Stats'], [], $this->siteStatsGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('_counter');
    }

    public function testOpYearlyStatsRendersYearlyView(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('SiteStatistics', ['op' => 'YearlyStats', 'year' => '2024'], [], $this->siteStatsGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('_stats_month');
    }

    public function testInvalidYearCastsToZero(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('SiteStatistics', ['op' => 'YearlyStats', 'year' => 'garbage'], [], $this->siteStatsGlobals());

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('_stats_month');
    }
}
