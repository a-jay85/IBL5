<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\AuditFinding;
use Boxscore\BoxscoreRepository;
use Boxscore\ScheduleReconciliationAudit;
use PHPUnit\Framework\Attributes\Group;

/**
 * DB-group integration tests for ScheduleReconciliationAudit.
 *
 * Every test rolls back via DatabaseTestCase::tearDown().
 *
 * Season-year / date rules (from section 9.1):
 *   ibl_box_scores_teams.season_year is GENERATED:
 *     CASE WHEN month(game_date) >= 10 THEN year(game_date) + 1 ELSE year(game_date) END
 *
 *   So season 2008 requires dates where:
 *     - month < 10 AND year = 2008  (e.g. 2008-01-15)
 *     - OR month >= 10 AND year = 2007 (e.g. 2007-11-15 — NOT month 8/9/10 which are off-schedule)
 *
 *   NEVER use 2008-10-XX or 2008-11-XX for season 2008 — those land in season 2009.
 *   NEVER use 2007-10-XX — month 10 is in OFF_SCHEDULE_MONTHS, exempt from orphan query.
 *
 * ibl_schedule.season_year is a regular stored INT (not generated) — insert it directly.
 */
#[Group('database')]
class ScheduleReconciliationAuditTest extends DatabaseTestCase
{
    private BoxscoreRepository $repo;
    private ScheduleReconciliationAudit $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo  = new BoxscoreRepository($this->db);
        $this->audit = new ScheduleReconciliationAudit($this->repo);
    }

    // ── Matrix row 8: audit fails on a seeded orphan ────────────────────────

    /**
     * A boxscore triple with no matching schedule row for the season must appear
     * as exactly one orphan error finding.
     */
    public function testOrphanBoxscoreIsReportedAsError(): void
    {
        // Seed a schedule row for a DIFFERENT triple so isEnabled=true.
        // Without at least one schedule row for the season the fail-open path
        // skips the orphan direction entirely (mirrors isEnabled() in Phase 3).
        $this->insertScheduleRow(2008, '2008-01-10', 5, 90, 6, 88);

        // Insert a boxscore for season 2008 (date in Jan, not off-schedule).
        // No schedule row for this triple → orphan.
        $this->insertTeamBoxscoreRow('2008-01-15', 'Metros', 1, 3, 7);

        $report = $this->audit->run(2008);

        $orphans = array_filter(
            $report->findings,
            static fn (AuditFinding $f): bool => $f->kind === AuditFinding::KIND_ORPHAN
        );

        self::assertCount(1, $orphans, 'Expected exactly one orphan finding');
        $finding = array_values($orphans)[0];
        self::assertSame(AuditFinding::SEVERITY_ERROR, $finding->severity);
        self::assertSame('2008-01-15', $finding->gameDate);
        self::assertSame(3, $finding->visitorTeamId);
        self::assertSame(7, $finding->homeTeamId);
        self::assertSame(1, $report->exitCode());
    }

    // ── Matrix row 10: scheduled-but-unplayed (0-0) produces no finding ─────

    /**
     * A scheduled game with visitor_score = 0 AND home_score = 0 must produce
     * no finding of any kind — those are series-ended-early games, not missing boxscores.
     */
    public function testScheduledButUnplayedGameProducesNoFinding(): void
    {
        // Insert 0-0 schedule row (unplayed game) — no boxscore row.
        $this->insertScheduleRow(2008, '2008-03-01', 3, 0, 7, 0);

        $report = $this->audit->run(2008);

        self::assertSame([], $report->findings, 'A 0-0 schedule row must produce no finding');
        self::assertSame(0, $report->exitCode());
    }

    // ── Matrix row 9: played-but-missing boxscore is warning only ────────────

    /**
     * A schedule row with real scores but no boxscore rows must appear as exactly
     * one warning — never as an error — and must not affect the exit code.
     *
     * This mirrors the live 2008-06-25 3@19 (134-147) case.
     */
    public function testScheduledAndPlayedWithoutBoxscoreIsWarningOnly(): void
    {
        $this->insertScheduleRow(2008, '2008-06-25', 3, 134, 19, 147);

        $report = $this->audit->run(2008);

        self::assertSame([], $report->errors(), 'No errors expected for a missing boxscore');
        self::assertCount(1, $report->warnings(), 'Expected exactly one warning');
        $warning = $report->warnings()[0];
        self::assertSame(AuditFinding::KIND_MISSING_BOXSCORE, $warning->kind);
        self::assertStringContainsString('134', $warning->detail);
        self::assertStringContainsString('147', $warning->detail);
        self::assertSame(0, $report->exitCode());
    }

    // ── All-Star exemption ───────────────────────────────────────────────────

    /**
     * Boxscore rows for the Rising Stars Game (40@41) on an early-February date
     * must never be reported as orphans — the All-Star exemption applies.
     */
    public function testAllStarGameIsNotReportedAsOrphan(): void
    {
        // Seed a schedule row so isEnabled=true — otherwise the fail-open path
        // skips orphan checks entirely and the exemption is never exercised.
        $this->insertScheduleRow(2008, '2008-01-10', 5, 90, 6, 88);

        // Rising Stars Game — team IDs 40/41 are in EXEMPT_TEAMIDS.
        $this->insertTeamBoxscoreRow('2008-02-02', 'Rookies', 1, 40, 41);

        $report = $this->audit->run(2008);

        $orphans = array_filter(
            $report->findings,
            static fn (AuditFinding $f): bool => $f->kind === AuditFinding::KIND_ORPHAN
        );
        self::assertCount(0, $orphans, 'Rising Stars game must not be reported as an orphan');
    }

    // ── Off-schedule month exemption ─────────────────────────────────────────

    /**
     * A boxscore row in an off-schedule month (month 10 = HEAT) with no schedule
     * row must not be reported as an orphan.
     *
     * date 2007-10-15 → season_year = 2008 (month 10 >= 10, year+1), month = 10.
     * Month 10 is in OFF_SCHEDULE_MONTHS → exempt from orphan query.
     */
    public function testOffScheduleMonthGameIsNotReportedAsOrphan(): void
    {
        // Seed a schedule row so isEnabled=true — otherwise the fail-open path
        // skips orphan checks entirely and the month-exemption is never exercised.
        $this->insertScheduleRow(2008, '2008-01-10', 5, 90, 6, 88);

        // ibl_box_scores_teams GENERATED season_year:
        // 2007-10-15 → month(10) >= 10, year(2007) + 1 = 2008. Month 10 = HEAT → exempt.
        $this->insertTeamBoxscoreRow('2007-10-15', 'Heat Team', 1, 3, 7);

        $report = $this->audit->run(2008);

        $orphans = array_filter(
            $report->findings,
            static fn (AuditFinding $f): bool => $f->kind === AuditFinding::KIND_ORPHAN
        );
        self::assertCount(0, $orphans, 'HEAT month boxscore must not be reported as orphan');
    }

    // ── Duplicate triple ─────────────────────────────────────────────────────

    /**
     * When the same triple exists at two different game_of_that_day values, the
     * audit must report it as a duplicate-triple error — even though one half of
     * the pair DOES match the schedule row (this case is invisible to the orphan
     * query, which only checks triple membership).
     */
    public function testDuplicateTripleIsReportedEvenWhenScheduleRowExists(): void
    {
        $this->insertScheduleRow(2008, '2008-02-05', 3, 105, 7, 98);

        // Two boxscore rows for the same triple at gotd=1 and gotd=4.
        $this->insertTeamBoxscoreRow('2008-02-05', 'Metros', 1, 3, 7);
        $this->insertTeamBoxscoreRow('2008-02-05', 'Metros', 4, 3, 7);

        $report = $this->audit->run(2008);

        $dupes = array_filter(
            $report->findings,
            static fn (AuditFinding $f): bool => $f->kind === AuditFinding::KIND_DUPLICATE_TRIPLE
        );
        self::assertCount(1, $dupes, 'Expected exactly one duplicate-triple finding');
        $finding = array_values($dupes)[0];
        self::assertSame(AuditFinding::SEVERITY_ERROR, $finding->severity);
        // Detail must name both gotd values without asserting which is the phantom
        self::assertStringContainsString('1', $finding->detail);
        self::assertStringContainsString('4', $finding->detail);
    }

    // ── Anti-regression: quadruple-header game_of_that_day ──────────────────

    /**
     * Anti-regression for matrix row 4: a boxscore whose game_of_that_day value
     * does NOT match any "rank" within that date's schedule listing must still
     * be accepted (no orphan) when the triple matches a schedule row.
     *
     * Reproduces the 2008-06-14 19@21 shape:
     *   - 4 games scheduled on the same date (hence gotd values up to 4)
     *   - Boxscore for the triple at gotd=4 while the triple is also the 4th entry
     *
     * If an implementer adds game_of_that_day to the NOT EXISTS predicate, this
     * test fails — which is the whole point.
     */
    public function testQuadrupleHeaderGameIsNotReportedAsOrphan(): void
    {
        $date = '2008-01-14';

        // Three "filler" schedule rows on the same date (different triples)
        $this->insertScheduleRow(2008, $date, 1, 90, 2, 88);
        $this->insertScheduleRow(2008, $date, 5, 95, 6, 92);
        $this->insertScheduleRow(2008, $date, 7, 102, 8, 98);

        // The triple under test — visitor=3, home=4, it's the 4th game in the day
        $this->insertScheduleRow(2008, $date, 3, 110, 4, 107);

        // Boxscore at gotd=4 (league-wide ordinal for the date, not a within-schedule rank)
        $this->insertTeamBoxscoreRow($date, 'Metros', 4, 3, 4);

        $report = $this->audit->run(2008);

        $orphans = array_filter(
            $report->findings,
            static fn (AuditFinding $f): bool => $f->kind === AuditFinding::KIND_ORPHAN
        );
        self::assertCount(0, $orphans, 'Quadruple-header boxscore at gotd=4 must not be orphan');
    }

    // ── Fail-open: empty schedule season ────────────────────────────────────

    /**
     * When a season has boxscores but zero schedule rows, the orphan direction
     * must be skipped entirely — reporting every game as corruption would be
     * catastrophically wrong for seasons whose schedule was never imported.
     *
     * Mirrors ScheduleMembershipGuard::isEnabled() fail-open behaviour from Phase 3.
     */
    public function testEmptyScheduleSeasonReportsNoOrphans(): void
    {
        // Insert a boxscore with no schedule rows at all for season 9999.
        // date 9999-01-15 → month 1 < 10, year 9999 → season_year = 9999.
        // No schedule rows exist for season 9999 in the test DB.
        $this->insertTeamBoxscoreRow('9999-01-15', 'Metros', 1, 3, 7);

        $report = $this->audit->run(9999);

        $orphans = array_filter(
            $report->findings,
            static fn (AuditFinding $f): bool => $f->kind === AuditFinding::KIND_ORPHAN
        );
        self::assertCount(0, $orphans, 'Empty-schedule season must produce no orphan findings');
    }
}
