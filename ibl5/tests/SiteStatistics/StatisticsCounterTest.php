<?php

declare(strict_types=1);

namespace Tests\SiteStatistics;

use MockDatabase;
use PHPUnit\Framework\TestCase;
use SiteStatistics\StatisticsRepository;

class StatisticsCounterTest extends TestCase
{
    private MockDatabase $mockDb;
    private StatisticsRepository $repository;
    private string $savedUserAgent;

    protected function setUp(): void
    {
        global $prefix, $user_prefix;
        $prefix = 'nuke';
        $user_prefix = 'nuke';

        $this->savedUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:100.0) Firefox/100.0';

        $this->mockDb = new MockDatabase();
        // Year exists, hour rows exist — default to skip seeding
        $this->mockDb->onQuery('stats_year WHERE year', [['year' => 2026]]);
        $this->mockDb->onQuery('stats_hour WHERE year', [['hour' => 0]]);

        $this->repository = new StatisticsRepository($this->mockDb);
    }

    protected function tearDown(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = $this->savedUserAgent;
    }

    public function testRecordHitIncrementsCounters(): void
    {
        $this->repository->recordHit();

        $this->assertQueryExecuted('_counter');
    }

    public function testRecordHitUpdatesAllTimeSeries(): void
    {
        $this->repository->recordHit();

        $this->assertQueryExecuted('_stats_year');
        $this->assertQueryExecuted('_stats_month');
        $this->assertQueryExecuted('_stats_date');
        $this->assertQueryExecuted('_stats_hour');
    }

    public function testRecordHitSeedsYearWhenMissing(): void
    {
        $this->mockDb->clearQueryPatterns();
        // Year does NOT exist
        $this->mockDb->onQuery('stats_year WHERE year', []);
        // Hour rows exist
        $this->mockDb->onQuery('stats_hour WHERE year', [['hour' => 0]]);

        $this->repository->recordHit();

        $this->assertQueryExecuted('INSERT INTO nuke_stats_year');
        $this->assertQueryExecuted('INSERT INTO nuke_stats_month');
        $this->assertQueryExecuted('INSERT INTO nuke_stats_date');
    }

    public function testRecordHitSkipsYearSeedWhenExists(): void
    {
        $this->repository->recordHit();

        $this->assertQueryNotExecuted('INSERT INTO nuke_stats_year');
    }

    public function testRecordHitSeedsHoursWhenMissing(): void
    {
        $this->mockDb->clearQueryPatterns();
        // Year exists
        $this->mockDb->onQuery('stats_year WHERE year', [['year' => 2026]]);
        // Hour rows do NOT exist
        $this->mockDb->onQuery('stats_hour WHERE year', []);

        $this->repository->recordHit();

        $this->assertQueryExecuted('INSERT INTO nuke_stats_hour');
    }

    public function testDetectBrowserFireFox(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Firefox/120.0';

        $this->repository->recordHit();

        $this->assertQueryContains('_counter', 'FireFox');
    }

    public function testDetectBrowserBot(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';

        $this->repository->recordHit();

        $this->assertQueryContains('_counter', 'Bot');
    }

    public function testDetectOSWindows(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0) Firefox/120.0';

        $this->repository->recordHit();

        $this->assertQueryContains('_counter', 'Windows');
    }

    public function testUnknownUserAgentDefaultsToOther(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'SomeUnknownAgent/1.0';

        $this->repository->recordHit();

        $this->assertQueryContains('_counter', 'Other');
    }

    /**
     * Assert that at least one executed query contains the given substring.
     */
    private function assertQueryExecuted(string $substring): void
    {
        foreach ($this->mockDb->getExecutedQueries() as $query) {
            if (str_contains($query, $substring)) {
                $this->addToAssertionCount(1);
                return;
            }
        }
        $this->fail("No executed query contains '{$substring}'");
    }

    /**
     * Assert that no executed query contains the given substring.
     */
    private function assertQueryNotExecuted(string $substring): void
    {
        foreach ($this->mockDb->getExecutedQueries() as $query) {
            if (str_contains($query, $substring)) {
                $this->fail("Query containing '{$substring}' was executed but should not have been");
            }
        }
        $this->addToAssertionCount(1);
    }

    /**
     * Assert that at least one executed query contains both substrings.
     */
    private function assertQueryContains(string $tableSubstring, string $valueSubstring): void
    {
        foreach ($this->mockDb->getExecutedQueries() as $query) {
            if (str_contains($query, $tableSubstring) && str_contains($query, $valueSubstring)) {
                $this->addToAssertionCount(1);
                return;
            }
        }
        $this->fail("No executed query contains both '{$tableSubstring}' and '{$valueSubstring}'");
    }
}
