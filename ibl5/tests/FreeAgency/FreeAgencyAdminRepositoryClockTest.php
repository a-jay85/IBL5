<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Admin\FreeAgencyAdminRepository;
use PHPUnit\Framework\TestCase;
use Tests\Clock\FixedClock;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Pins the injected clock so news-story timestamps are deterministic.
 */
class FreeAgencyAdminRepositoryClockTest extends TestCase
{
    public function testInsertNewsStoryStampsTimeFromInjectedClockAtDayBoundary(): void
    {
        // 23:59:59 UTC on the last day of a year: the day before the rollover.
        $pinnedNow = (int) gmmktime(23, 59, 59, 12, 31, 2025);
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            $db = new MockDatabase();
            $db->setAffectedRows(0);
            $repo = new FreeAgencyAdminRepository($db, null, new FixedClock($pinnedNow));

            $repo->insertNewsStory('Title', 'Home', 'Body');
        } finally {
            date_default_timezone_set($tz);
        }

        self::assertContains('2025-12-31 23:59:59', $db->getLastBoundParams());
    }
}
