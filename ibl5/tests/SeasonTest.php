<?php

declare(strict_types=1);

use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * SeasonTest - Tests for Season class
 */
class SeasonTest extends \PHPUnit\Framework\TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    // ============================================
    // CONSTANT TESTS
    // ============================================

    public function testIblOlympicsMonthConstant(): void
    {
        $this->assertGreaterThanOrEqual(1, Season::IBL_OLYMPICS_MONTH);
        $this->assertLessThanOrEqual(12, Season::IBL_OLYMPICS_MONTH);
    }

    public function testIblHeatMonthConstant(): void
    {
        $this->assertGreaterThanOrEqual(1, Season::IBL_HEAT_MONTH);
        $this->assertLessThanOrEqual(12, Season::IBL_HEAT_MONTH);
    }

    public function testIblRegularSeasonStartingMonthConstant(): void
    {
        $this->assertGreaterThanOrEqual(1, Season::IBL_REGULAR_SEASON_STARTING_MONTH);
        $this->assertLessThanOrEqual(12, Season::IBL_REGULAR_SEASON_STARTING_MONTH);
    }

    public function testIblAllStarMonthConstant(): void
    {
        $this->assertGreaterThanOrEqual(1, Season::IBL_ALL_STAR_MONTH);
        $this->assertLessThanOrEqual(12, Season::IBL_ALL_STAR_MONTH);
    }

    public function testIblRegularSeasonEndingMonthConstant(): void
    {
        $this->assertGreaterThanOrEqual(1, Season::IBL_REGULAR_SEASON_ENDING_MONTH);
        $this->assertLessThanOrEqual(12, Season::IBL_REGULAR_SEASON_ENDING_MONTH);
    }

    public function testIblPlayoffMonthConstant(): void
    {
        $this->assertGreaterThanOrEqual(1, Season::IBL_PLAYOFF_MONTH);
        $this->assertLessThanOrEqual(12, Season::IBL_PLAYOFF_MONTH);
    }

    public function testIblAllStarBreakStartDayConstant(): void
    {
        $this->assertGreaterThanOrEqual(1, Season::IBL_ALL_STAR_BREAK_START_DAY);
        $this->assertLessThanOrEqual(31, Season::IBL_ALL_STAR_BREAK_START_DAY);
    }

    public function testIblRisingStarsGameDayConstant(): void
    {
        $this->assertGreaterThan(Season::IBL_ALL_STAR_BREAK_START_DAY, Season::IBL_RISING_STARS_GAME_DAY);
    }

    public function testIblAllStarGameDayConstant(): void
    {
        $this->assertGreaterThan(Season::IBL_RISING_STARS_GAME_DAY, Season::IBL_ALL_STAR_GAME_DAY);
    }

    public function testIblAllStarBreakEndDayConstant(): void
    {
        $this->assertGreaterThan(Season::IBL_ALL_STAR_GAME_DAY, Season::IBL_ALL_STAR_BREAK_END_DAY);
    }

    public function testIblPostAllStarFirstDayConstant(): void
    {
        $this->assertGreaterThan(Season::IBL_ALL_STAR_BREAK_END_DAY, Season::IBL_POST_ALL_STAR_FIRST_DAY);
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testHasShowDraftLinkProperty(): void
    {
        // Verify the showDraftLink property is accessible (not private/protected)
        $reflection = new \ReflectionProperty(Season::class, 'showDraftLink');
        $this->assertTrue($reflection->isPublic());
    }

    // ============================================
    // RS-TO-PLAYOFFS GAP SKIP TESTS
    // ============================================

    /**
     * Create a Season mock configured for gap-skip testing
     *
     * Uses the mock Season (via class_alias) with properties set directly
     * instead of database queries.
     *
     * @param int $simLengthInDays Sim length in days
     * @param string|null $lastRSGameDate Last regular season game date (YYYY-MM-DD), or null
     * @return Season Configured mock Season
     */
    private function createGapSkipSeason(
        int $simLengthInDays,
        ?string $lastRSGameDate,
    ): Season {
        $season = new Season($this->mockDb);
        $season->endingYear = 2025;
        $season->playoffsStartDate = new \DateTime('2025-06-01');
        $season->simLengthInDays = $simLengthInDays;
        $season->lastRegularSeasonGameDate = $lastRSGameDate;
        return $season;
    }

    public function testGapSkipWhenSimCrossesGap(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-15');

        $result = $season->getProjectedNextSimEndDate('2025-05-10');

        // May 10 + 7 = May 17, crosses gap (May 16 to June 1 = 16 days), May 17 + 16 = June 2
        $this->assertSame('2025-06-02', $result->format('Y-m-d'));
    }

    public function testGapSkipWhenLastRsSimDone(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-15');

        $result = $season->getProjectedNextSimEndDate('2025-05-15');

        // May 15 + 7 = May 22, crosses gap (May 16 to June 1 = 16 days), May 22 + 16 = June 7
        $this->assertSame('2025-06-07', $result->format('Y-m-d'));
    }

    public function testNoGapSkipWhenSimEndsBeforeGap(): void
    {
        $season = $this->createGapSkipSeason(3, '2025-05-15');

        $result = $season->getProjectedNextSimEndDate('2025-05-08');

        // May 8 + 3 = May 11, does not reach gap (May 16), no adjustment
        $this->assertSame('2025-05-11', $result->format('Y-m-d'));
    }

    public function testNoGapSkipWhenAlreadyInPlayoffs(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-15');

        $result = $season->getProjectedNextSimEndDate('2025-06-01');

        // June 1 + 7 = June 8, lastSimEnd (June 1) is NOT < gapStart (May 16), no adjustment
        $this->assertSame('2025-06-08', $result->format('Y-m-d'));
    }

    public function testNoGapSkipWhenNoScheduleData(): void
    {
        $season = $this->createGapSkipSeason(7, null);

        $result = $season->getProjectedNextSimEndDate('2025-05-10');

        // No lastRSGameDate, no adjustment: May 10 + 7 = May 17
        $this->assertSame('2025-05-17', $result->format('Y-m-d'));
    }

    public function testNoGapSkipWhenRsEndsMay31(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-31');

        $result = $season->getProjectedNextSimEndDate('2025-05-28');

        // gapStart = June 1, playoffsStart = June 1, gapStart < playoffsStart is false, no adjustment
        // May 28 + 7 = June 4
        $this->assertSame('2025-06-04', $result->format('Y-m-d'));
    }

    public function testGapSkipWhenLastSimInsideGap(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-15');

        // lastSimEndDate = May 20, which is inside the gap (May 16 to June 1)
        $result = $season->getProjectedNextSimEndDate('2025-05-20');

        // May 20 + 7 = May 27, then skip remaining gap days (May 20 to June 1 = 12 days)
        // May 27 + 12 = June 8
        $this->assertSame('2025-06-08', $result->format('Y-m-d'));
    }

    public function testGapSkipWhenLastSimAtGapEnd(): void
    {
        $season = $this->createGapSkipSeason(7, '2025-05-15');

        // lastSimEndDate = May 31, which is at the end of the gap
        $result = $season->getProjectedNextSimEndDate('2025-05-31');

        // May 31 + 7 = June 7, then skip remaining gap days (May 31 to June 1 = 1 day)
        // June 7 + 1 = June 8
        $this->assertSame('2025-06-08', $result->format('Y-m-d'));
    }

    // --- Merged from SeasonAreTradesAllowedTest ---

    #[\PHPUnit\Framework\Attributes\DataProvider('tradesAllowedProvider')]
    public function testAreTradesAllowed(string $phase, string $allowTrades, bool $expected): void
    {
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->phase = $phase;
        $season->allowTrades = $allowTrades;

        $this->assertSame($expected, $season->areTradesAllowed());
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function tradesAllowedProvider(): array
    {
        return [
            'draft phase overrides No setting' => ['Draft', 'No', true],
            'draft phase with Yes setting' => ['Draft', 'Yes', true],
            'free agency phase overrides No setting' => ['Free Agency', 'No', true],
            'free agency phase with Yes setting' => ['Free Agency', 'Yes', true],
            'regular season with Yes setting' => ['Regular Season', 'Yes', true],
            'regular season with No setting' => ['Regular Season', 'No', false],
            'preseason with Yes setting' => ['Preseason', 'Yes', true],
            'preseason with No setting' => ['Preseason', 'No', true],
            'heat phase overrides No setting' => ['HEAT', 'No', true],
            'heat phase with Yes setting' => ['HEAT', 'Yes', true],
            'playoffs always blocked with Yes setting' => ['Playoffs', 'Yes', false],
            'playoffs always blocked with No setting' => ['Playoffs', 'No', false],
        ];
    }
}
