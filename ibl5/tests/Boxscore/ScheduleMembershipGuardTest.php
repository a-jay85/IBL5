<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\Boxscore;
use Boxscore\RejectedGame;
use Boxscore\ScheduleMembershipGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Boxscore\ScheduleMembershipGuard
 * @covers \Boxscore\RejectedGame
 */
class ScheduleMembershipGuardTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Accept cases
    // -------------------------------------------------------------------------

    public function testAcceptsScheduledGame(): void
    {
        $scheduleIndex = ['2008-01-10' => [2 => [1 => true]]];
        $guard = new ScheduleMembershipGuard(2008, $scheduleIndex, []);
        $game = $this->makeBoxscore('2008-01-10', 2, 1, 1);

        $this->assertNull($guard->evaluate($game));
    }

    public function testAcceptsQuadrupleWhoseGameOfThatDayDiffersFromScheduleOrdinal(): void
    {
        // Adding game_of_that_day to the rule-4 membership predicate MUST break this test.
        //
        // Live evidence: ibl_box_scores_teams stores 2008-06-14 19@21 at game_of_that_day=4,
        // while that matchup is not the second row for that date in ibl_schedule.
        // game_of_that_day is a league-wide ordinal within the date, not a within-schedule rank.
        $scheduleIndex = ['2008-06-14' => [19 => [21 => true]]];
        $guard = new ScheduleMembershipGuard(2008, $scheduleIndex, []);
        $game = $this->makeBoxscore('2008-06-14', 19, 21, 4);

        // Accept when gotd index is empty for this triple
        $this->assertNull($guard->evaluate($game));

        // Accept again when the gotd index already contains gotd=4 (re-import path)
        $guardWithGotd = new ScheduleMembershipGuard(
            2008,
            $scheduleIndex,
            ['2008-06-14' => [19 => [21 => [4]]]],
        );
        $this->assertNull($guardWithGotd->evaluate($game));
    }

    #[DataProvider('allStarAndRisingStarsProvider')]
    public function testAcceptsAllStarAndRisingStarsTeamids(
        int $visitorTeamid,
        int $homeTeamid,
        string $gameDate,
    ): void {
        // Non-optional: without this exemption the All-Star Game breaks every February.
        // An index that contains NEITHER triple: even if we checked, both would fail membership.
        $scheduleIndex = ['2008-01-10' => [2 => [1 => true]]]; // unrelated entry, makes guard enabled
        $guard = new ScheduleMembershipGuard(2008, $scheduleIndex, []);
        $game = $this->makeBoxscore($gameDate, $visitorTeamid, $homeTeamid, 1);

        $this->assertNull($guard->evaluate($game));
    }

    /** @return array<string, array{int, int, string}> */
    public static function allStarAndRisingStarsProvider(): array
    {
        return [
            'Rising Stars 40@41 on 02-02' => [40, 41, '2008-02-02'],
            'Rising Stars 40@41 on 02-03' => [40, 41, '2008-02-03'],
            'All-Star 50@51 on 02-02'     => [50, 51, '2008-02-02'],
            'All-Star 50@51 on 02-03'     => [50, 51, '2008-02-03'],
        ];
    }

    #[DataProvider('offScheduleMonthProvider')]
    public function testAcceptsOffScheduleMonths(
        string $gameDate,
        int $month,
    ): void {
        // Olympics (8), Preseason (9), and HEAT (10) have no ibl_schedule rows by design.
        // The schedule index is empty here, but even with a non-empty index the month
        // exemption fires before the membership check.
        $guard = new ScheduleMembershipGuard(2008, [], []);
        $game = $this->makeBoxscore($gameDate, 3, 7, 1);
        // Override gameMonth to match the off-schedule month (overrideGameContext sets gameDate
        // but not gameMonth, so we set it directly).
        $game->gameMonth = sprintf('%02d', $month);

        $this->assertNull($guard->evaluate($game));
    }

    /** @return array<string, array{string, int}> */
    public static function offScheduleMonthProvider(): array
    {
        return [
            'HEAT month 10 on 2007-10-15'      => ['2007-10-15', 10],
            'Preseason month 9 on 2007-09-20'  => ['2007-09-20', 9],
            'Olympics month 8 on 2008-08-05'   => ['2008-08-05', 8],
        ];
    }

    public function testAcceptsRepeatImportOfSameQuadruple(): void
    {
        // gotd index holds [2] for the triple — incoming gotd is 2 (re-import/update path).
        // processGameUpsert() must still be reached so a re-simmed game updates in place.
        $scheduleIndex = ['2008-03-20' => [5 => [12 => true]]];
        $gotdIndex     = ['2008-03-20' => [5 => [12 => [2]]]];
        $guard = new ScheduleMembershipGuard(2008, $scheduleIndex, $gotdIndex);
        $game = $this->makeBoxscore('2008-03-20', 5, 12, 2);

        $this->assertNull($guard->evaluate($game));
    }

    // -------------------------------------------------------------------------
    // Reject cases
    // -------------------------------------------------------------------------

    public function testRejectsGameAbsentFromSchedule(): void
    {
        // Non-empty index (guard is enabled), but the triple 2008-04-01 7@3 is absent.
        $scheduleIndex = ['2008-04-01' => [2 => [1 => true]]]; // different triple
        $guard = new ScheduleMembershipGuard(2008, $scheduleIndex, []);
        $game = $this->makeBoxscore('2008-04-01', 7, 3, 1);

        $result = $guard->evaluate($game);

        $this->assertInstanceOf(RejectedGame::class, $result);
        $this->assertSame(RejectedGame::REASON_NOT_IN_SCHEDULE, $result->reason);
        $this->assertSame('2008-04-01', $result->gameDate);
        $this->assertSame(7, $result->visitorTeamid);
        $this->assertSame(3, $result->homeTeamid);
        $this->assertSame(1, $result->gameOfThatDay);
    }

    public function testRejectsDuplicateTripleAtDifferentGameOfThatDay(): void
    {
        // Live case: 2008-04-05 21@17 — ibl_schedule records 137-97, matching gotd-1 boxscore;
        // a phantom copy was inserted at gotd=4 (from 07-08_36_playoffs.zip, prior season).
        $scheduleIndex = ['2008-04-05' => [21 => [17 => true]]];
        $gotdIndex     = ['2008-04-05' => [21 => [17 => [1]]]];
        $guard = new ScheduleMembershipGuard(2008, $scheduleIndex, $gotdIndex);
        $game = $this->makeBoxscore('2008-04-05', 21, 17, 4);

        $result = $guard->evaluate($game);

        $this->assertInstanceOf(RejectedGame::class, $result);
        $this->assertSame(RejectedGame::REASON_DUPLICATE_TRIPLE, $result->reason);
        $this->assertSame([1], $result->storedGameOfThatDay);
    }

    public function testRejectsSecondCopyOfSameTripleWithinOneRun(): void
    {
        // In-run registration: the gotd index starts empty; the first evaluate() accepts
        // gotd=1 and registers it; the second evaluate() of the same triple at gotd=4
        // is caught by the duplicate-triple rule.
        $scheduleIndex = ['2008-04-10' => [15 => [8 => true]]];
        $guard = new ScheduleMembershipGuard(2008, $scheduleIndex, []);

        $firstGame  = $this->makeBoxscore('2008-04-10', 15, 8, 1);
        $secondGame = $this->makeBoxscore('2008-04-10', 15, 8, 4);

        // First copy accepted and registered
        $this->assertNull($guard->evaluate($firstGame));

        // Second copy (different gotd) rejected with storedGameOfThatDay=[1]
        $result = $guard->evaluate($secondGame);
        $this->assertInstanceOf(RejectedGame::class, $result);
        $this->assertSame(RejectedGame::REASON_DUPLICATE_TRIPLE, $result->reason);
        $this->assertSame([1], $result->storedGameOfThatDay);
    }

    // -------------------------------------------------------------------------
    // Boundary / negative cases
    // -------------------------------------------------------------------------

    public function testFailsOpenWhenScheduleIndexIsEmpty(): void
    {
        // When no schedule rows exist for the season, every game is accepted.
        // This preserves the pre-guard behavior and avoids 100% rejection on a first import.
        $guard = new ScheduleMembershipGuard(2008, [], []);

        $this->assertFalse($guard->isEnabled());

        // A game that would otherwise fail the membership check is accepted.
        $game = $this->makeBoxscore('2008-04-01', 7, 3, 1);
        $this->assertNull($guard->evaluate($game));
    }

    public function testExemptTeamidsAreNotRegisteredInTheIndex(): void
    {
        // All-Star pseudo-teams must not be registered (rule 2 returns before rule 6),
        // so that evaluating the same All-Star game twice never triggers the duplicate-triple rule.
        $scheduleIndex = ['2008-02-02' => [2 => [1 => true]]]; // unrelated, keeps guard enabled
        $guard = new ScheduleMembershipGuard(2008, $scheduleIndex, []);

        $firstGame  = $this->makeBoxscore('2008-02-02', 50, 51, 1);
        $secondGame = $this->makeBoxscore('2008-02-02', 50, 51, 2);

        $this->assertNull($guard->evaluate($firstGame));
        $this->assertNull($guard->evaluate($secondGame));
    }

    public function testGuardNeverThrows(): void
    {
        // The guard is on the import hot path; an exception would abort the run,
        // violating the never-abort contract. Extreme inputs must return a RejectedGame
        // rather than raising. game_of_that_day=0 and teamids=0/0 are boundary cases.
        $scheduleIndex = ['2008-04-01' => [2 => [1 => true]]]; // non-empty so guard is enabled
        $guard = new ScheduleMembershipGuard(2008, $scheduleIndex, []);
        $game = $this->makeBoxscore('2008-04-01', 0, 0, 0);

        $result = $guard->evaluate($game);

        // teamids 0/0 are not in EXEMPT_TEAMIDS and the triple is not in the schedule,
        // so we expect a REASON_NOT_IN_SCHEDULE rejection, not an exception.
        $this->assertInstanceOf(RejectedGame::class, $result);
        $this->assertSame(RejectedGame::REASON_NOT_IN_SCHEDULE, $result->reason);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Build a minimal Boxscore with the game context needed by ScheduleMembershipGuard.
     *
     * Uses a neutral game-info line (all zeros) to avoid fillGameInfo() side-effects,
     * then overrides the four fields the guard reads: gameDate, visitor_teamid,
     * home_teamid, game_of_that_day. Also overrides gameMonth to be consistent with
     * the gameDate month (overrideGameContext does not update gameMonth).
     */
    private function makeBoxscore(
        string $gameDate,
        int $visitorTeamid,
        int $homeTeamid,
        int $gameOfThatDay,
    ): Boxscore {
        $box = Boxscore::withGameInfoLine(str_repeat('0', 58), 2008, 'Regular Season/Playoffs');
        $box->overrideGameContext($gameDate, $visitorTeamid, $homeTeamid, $gameOfThatDay);
        // Sync gameMonth with the actual date so the month-exemption rule (rule 3) reads correctly.
        $box->gameMonth = substr($gameDate, 5, 2); // e.g. "2008-04-05" → "04"
        return $box;
    }
}
