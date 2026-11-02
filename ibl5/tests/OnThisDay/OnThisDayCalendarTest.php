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

    public function testSingleDaySpanAtRadiusZeroReturnsThatDay(): void
    {
        $this->assertSame(['03-15'], $this->calendar->spanWindow('2026-03-15', '2026-03-15', 0));
    }

    public function testSingleDaySpanWrapsAcrossYearEnd(): void
    {
        $this->assertSame(
            ['12-29', '12-30', '12-31', '01-01', '01-02'],
            $this->calendar->spanWindow('2026-12-31', '2026-12-31', 2)
        );
        $this->assertSame(
            ['12-31', '01-01', '01-02'],
            $this->calendar->spanWindow('2027-01-01', '2027-01-01', 1)
        );
    }

    public function testFebruary29SingleDaySpanMatchesOnlyItself(): void
    {
        $this->assertSame(['02-29'], $this->calendar->spanWindow('2028-02-29', '2028-02-29', 0));
    }

    public function testMarchFirstSingleDaySpanIncludesFebruary29(): void
    {
        $this->assertSame(
            ['02-29', '03-01', '03-02'],
            $this->calendar->spanWindow('2026-03-01', '2026-03-01', 1)
        );
    }

    public function testSpanCoversEveryDayFromStartThroughEnd(): void
    {
        $this->assertSame(
            ['03-09', '03-10', '03-11', '03-12', '03-13', '03-14', '03-15'],
            $this->calendar->spanWindow('2026-03-09', '2026-03-15', 0)
        );
    }

    public function testSpanAcrossYearEndRunsInOrder(): void
    {
        $this->assertSame(
            ['12-29', '12-30', '12-31', '01-01', '01-02'],
            $this->calendar->spanWindow('2026-12-29', '2027-01-02', 0)
        );
    }

    /**
     * The real year 2027 is not a leap year, so this proves the layout year decides.
     */
    public function testSpanCrossingFebruaryIncludesFebruary29(): void
    {
        $this->assertSame(
            ['02-27', '02-28', '02-29', '03-01'],
            $this->calendar->spanWindow('2027-02-27', '2027-03-01', 0)
        );
    }

    public function testRadiusWidensFromBothSpanEdges(): void
    {
        $window = $this->calendar->spanWindow('2026-03-09', '2026-03-15', 3);

        $this->assertSame('03-06', $window[0]);
        $this->assertSame('03-18', $window[array_key_last($window)]);
        $this->assertCount(13, $window);
    }

    public function testStartAfterEndCollapsesToEndDay(): void
    {
        $this->assertSame(['03-15'], $this->calendar->spanWindow('2026-03-20', '2026-03-15', 0));
    }

    public function testOverlongSpanKeepsItsLastDays(): void
    {
        $window = $this->calendar->spanWindow('2026-01-01', '2026-03-15', 0);

        $this->assertCount(OnThisDayCalendar::MAX_SPAN_DAYS, $window);
        $this->assertSame('03-15', $window[array_key_last($window)]);
    }

    /**
     * The widest window (an overlong span at the last widening radius) fills the
     * repository's month-day list exactly; a non-leap February crossing fits it.
     */
    public function testWidestWindowFitsTheReaderList(): void
    {
        $radii = OnThisDayCalendar::WIDENING_RADII;
        $widest = $radii[array_key_last($radii)];

        $overlong = $this->calendar->spanWindow('2026-01-01', '2026-03-15', $widest);
        $this->assertCount(OnThisDayRepository::MONTH_DAY_LIST_WIDTH, $overlong);

        $februaryCrossing = $this->calendar->spanWindow('2027-02-01', '2027-03-10', $widest);
        $this->assertLessThanOrEqual(OnThisDayRepository::MONTH_DAY_LIST_WIDTH, count($februaryCrossing));
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
