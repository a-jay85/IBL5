<?php

declare(strict_types=1);

namespace Tests\OnThisDay;

use OnThisDay\OnThisDayCalendar;
use OnThisDay\OnThisDayRepository;
use PHPUnit\Framework\TestCase;

class OnThisDayCalendarTest extends TestCase
{
    private OnThisDayCalendar $calendar;

    protected function setUp(): void
    {
        $this->calendar = new OnThisDayCalendar();
    }

    public function testRadiusZeroReturnsCenterOnly(): void
    {
        $this->assertSame(['03-15'], $this->calendar->monthDayWindow('2026-03-15', 0));
    }

    public function testWindowWrapsAcrossYearEnd(): void
    {
        $this->assertSame(
            ['12-29', '12-30', '12-31', '01-01', '01-02'],
            $this->calendar->monthDayWindow('2026-12-31', 2)
        );
        $this->assertSame(
            ['12-31', '01-01', '01-02'],
            $this->calendar->monthDayWindow('2027-01-01', 1)
        );
    }

    public function testFebruary29CenterAtRadiusZeroMatchesOnlyItself(): void
    {
        $this->assertSame(['02-29'], $this->calendar->monthDayWindow('2028-02-29', 0));
    }

    public function testMarchFirstWindowIncludesFebruary29(): void
    {
        $this->assertSame(
            ['02-29', '03-01', '03-02'],
            $this->calendar->monthDayWindow('2026-03-01', 1)
        );
    }

    /**
     * The widest radius is one week each side, the #3351 (8c6049c75e) cap, and
     * that window fills the repository's month-day list exactly.
     */
    public function testWidestRadiusSpansOneWeekEachSideAndFitsTheReaderList(): void
    {
        $radii = OnThisDayCalendar::WIDENING_RADII;
        $window = $this->calendar->monthDayWindow('2026-03-15', $radii[array_key_last($radii)]);

        $this->assertSame('03-08', $window[0]);
        $this->assertSame('03-22', $window[array_key_last($window)]);
        $this->assertCount(OnThisDayRepository::MONTH_DAY_LIST_WIDTH, $window);
    }

    public function testOctoberDateBelongsToNextSeasonYear(): void
    {
        $this->assertSame(2027, $this->calendar->seasonYearOf('2026-10-19'));
        $this->assertSame(2027, $this->calendar->seasonYearOf('2026-11-01'));
        $this->assertSame(2026, $this->calendar->seasonYearOf('2026-06-15'));
    }

    public function testSeasonsAgoExcludesCurrentAndFutureSeasons(): void
    {
        $this->assertNull($this->calendar->seasonsAgo(2027, 2027));
        $this->assertNull($this->calendar->seasonsAgo(2027, 2030));
        $this->assertSame(1, $this->calendar->seasonsAgo(2027, 2026));
        $this->assertSame(10, $this->calendar->seasonsAgo(2027, 2017));
    }
}
