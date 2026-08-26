<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\AuditFinding;
use Boxscore\Contracts\BoxscoreRepositoryInterface;
use Boxscore\ScheduleAuditReport;
use Boxscore\ScheduleReconciliationAudit;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Boxscore\ScheduleReconciliationAudit
 * @covers \Boxscore\ScheduleAuditReport
 * @covers \Boxscore\AuditFinding
 */
final class ScheduleReconciliationAuditTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, array<int, array<int, true>>>                                                                  $scheduleIndex
     * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, name: string}>    $orphans
     * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, occurrences: int, gotds: string}>        $duplicates
     * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, visitor_score: int, home_score: int}>    $missing
     */
    private function makeAudit(
        array $scheduleIndex = [],
        array $orphans = [],
        array $duplicates = [],
        array $missing = [],
    ): ScheduleAuditReport {
        $repo = new class($scheduleIndex, $orphans, $duplicates, $missing) implements BoxscoreRepositoryInterface {
            /**
             * @param array<string, array<int, array<int, true>>>                                                               $scheduleIndex
             * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, name: string}> $orphans
             * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, occurrences: int, gotds: string}>     $duplicates
             * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, visitor_score: int, home_score: int}> $missing
             */
            public function __construct(
                private array $scheduleIndex,
                private array $orphans,
                private array $duplicates,
                private array $missing,
            ) {}

            public function fetchScheduledGameIndex(int $seasonYear): array { return $this->scheduleIndex; }
            public function findOrphanBoxscoreGames(int $seasonYear): array { return $this->orphans; }
            public function findDuplicateTripleGames(int $seasonYear): array { return $this->duplicates; }
            public function findScheduledGamesWithoutBoxscores(int $seasonYear): array { return $this->missing; }

            // Unused methods — throw to catch accidental calls
            public function fetchBoxscoreGameOfThatDayIndex(int $seasonYear): array { throw new \RuntimeException('not implemented'); }
            public function deletePreseasonBoxScores(int $seasonBeginningYear): bool { throw new \RuntimeException('not implemented'); }
            public function deleteHeatBoxScores(int $seasonStartingYear): bool { throw new \RuntimeException('not implemented'); }
            public function deleteRegularSeasonAndPlayoffsBoxScores(int $seasonStartingYear): bool { throw new \RuntimeException('not implemented'); }
            public function findTeamBoxscore(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): ?array { throw new \RuntimeException('not implemented'); }
            public function deleteTeamBoxscoresByGame(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): int { throw new \RuntimeException('not implemented'); }
            public function deletePlayerBoxscoresByGame(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): int { throw new \RuntimeException('not implemented'); }
            public function insertTeamBoxscore(array $row): int { throw new \RuntimeException('not implemented'); }
            public function hasNullTeamIdPlayerBoxscores(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): bool { throw new \RuntimeException('not implemented'); }
            public function findAllStarTeamNames(string $date): ?array { throw new \RuntimeException('not implemented'); }
            public function findAllStarGamesWithDefaultNames(): array { throw new \RuntimeException('not implemented'); }
            public function getPlayersForAllStarTeam(string $date, int $teamid): array { throw new \RuntimeException('not implemented'); }
            public function renameAllStarTeam(int $recordId, string $newName): int { throw new \RuntimeException('not implemented'); }
            public function insertPlayerBoxscore(
                string $date, string $uuid, string $name, string $position,
                int $playerID, int $visitor_teamid, int $home_teamid, int $game_of_that_day,
                int $attendance, int $capacity, int $visitor_wins, int $visitor_losses,
                int $home_wins, int $home_losses, int $teamid, int $minutesPlayed,
                int $fieldGoalsMade, int $fieldGoalsAttempted, int $freeThrowsMade,
                int $freeThrowsAttempted, int $threePointersMade, int $threePointersAttempted,
                int $offensiveRebounds, int $defensiveRebounds, int $assists, int $steals,
                int $turnovers, int $blocks, int $personalFouls,
            ): int { throw new \RuntimeException('not implemented'); }
        };

        return (new ScheduleReconciliationAudit($repo))->run(2008);
    }

    /**
     * @return array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, name: string}
     */
    private function makeOrphanRow(
        string $date = '2008-02-05',
        int $visitor = 3,
        int $home = 7,
        int $gotd = 1,
        string $name = 'Chicago Fire',
    ): array {
        return [
            'game_date'       => $date,
            'visitor_teamid'  => $visitor,
            'home_teamid'     => $home,
            'game_of_that_day' => $gotd,
            'name'            => $name,
        ];
    }

    /**
     * @return array{game_date: string, visitor_teamid: int, home_teamid: int, occurrences: int, gotds: string}
     */
    private function makeDuplicateRow(
        string $date = '2008-04-15',
        int $visitor = 23,
        int $home = 19,
        int $occurrences = 2,
        string $gotds = '1,4',
    ): array {
        return [
            'game_date'      => $date,
            'visitor_teamid' => $visitor,
            'home_teamid'    => $home,
            'occurrences'    => $occurrences,
            'gotds'          => $gotds,
        ];
    }

    /**
     * @return array{game_date: string, visitor_teamid: int, home_teamid: int, visitor_score: int, home_score: int}
     */
    private function makeMissingRow(
        string $date = '2008-06-25',
        int $visitor = 3,
        int $home = 19,
        int $visitorScore = 134,
        int $homeScore = 147,
    ): array {
        return [
            'game_date'      => $date,
            'visitor_teamid' => $visitor,
            'home_teamid'    => $home,
            'visitor_score'  => $visitorScore,
            'home_score'     => $homeScore,
        ];
    }

    /**
     * Minimal schedule index with one entry so isEnabled() returns true.
     *
     * @return array<string, array<int, array<int, true>>>
     */
    private function oneGameSchedule(): array
    {
        return ['2008-01-10' => [2 => [1 => true]]];
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function testCleanSeasonProducesNoFindingsAndExitCodeZero(): void
    {
        $report = $this->makeAudit(scheduleIndex: $this->oneGameSchedule());

        self::assertSame([], $report->findings);
        self::assertFalse($report->hasErrors());
        self::assertSame(0, $report->exitCode());
    }

    public function testOrphanRowsBecomeErrors(): void
    {
        $report = $this->makeAudit(
            scheduleIndex: $this->oneGameSchedule(),
            orphans: [$this->makeOrphanRow()],
        );

        self::assertCount(1, $report->findings);
        self::assertSame(AuditFinding::SEVERITY_ERROR, $report->findings[0]->severity);
        self::assertSame(AuditFinding::KIND_ORPHAN, $report->findings[0]->kind);
        self::assertSame(1, $report->exitCode());
    }

    public function testDuplicateTriplesBecomeErrors(): void
    {
        $dup = $this->makeDuplicateRow(gotds: '1,4');
        $report = $this->makeAudit(
            scheduleIndex: $this->oneGameSchedule(),
            duplicates: [$dup],
        );

        self::assertCount(1, $report->errors());
        self::assertSame(AuditFinding::KIND_DUPLICATE_TRIPLE, $report->errors()[0]->kind);
        self::assertSame(1, $report->exitCode());
        // Detail must name both gotd values
        self::assertStringContainsString('1', $report->errors()[0]->detail);
        self::assertStringContainsString('4', $report->errors()[0]->detail);
    }

    /**
     * Matrix row 9 at unit level: the single most important assertion in the phase.
     * A missing boxscore is a warning, never an error; exitCode must stay 0.
     */
    public function testMissingBoxscoresBecomeWarningsAndDoNotFail(): void
    {
        $report = $this->makeAudit(
            scheduleIndex: $this->oneGameSchedule(),
            missing: [$this->makeMissingRow()],
        );

        self::assertCount(1, $report->warnings());
        self::assertSame(AuditFinding::KIND_MISSING_BOXSCORE, $report->warnings()[0]->kind);
        self::assertSame(AuditFinding::SEVERITY_WARNING, $report->warnings()[0]->severity);
        self::assertSame([], $report->errors());
        self::assertSame(0, $report->exitCode());
    }

    /** Findings must be emitted in order: orphan, duplicate, missing. */
    public function testMixedFindingsOrderErrorsBeforeWarnings(): void
    {
        $report = $this->makeAudit(
            scheduleIndex: $this->oneGameSchedule(),
            orphans: [$this->makeOrphanRow()],
            duplicates: [$this->makeDuplicateRow()],
            missing: [$this->makeMissingRow()],
        );

        self::assertCount(3, $report->findings);
        self::assertSame(AuditFinding::KIND_ORPHAN, $report->findings[0]->kind);
        self::assertSame(AuditFinding::KIND_DUPLICATE_TRIPLE, $report->findings[1]->kind);
        self::assertSame(AuditFinding::KIND_MISSING_BOXSCORE, $report->findings[2]->kind);
    }

    /** summaryLine() must report both severities with correct counts. */
    public function testSummaryLineReportsCountsForBothSeverities(): void
    {
        // 2 errors (1 orphan + 1 duplicate), 1 warning
        $report = $this->makeAudit(
            scheduleIndex: $this->oneGameSchedule(),
            orphans: [$this->makeOrphanRow()],
            duplicates: [$this->makeDuplicateRow()],
            missing: [$this->makeMissingRow()],
        );

        $line = $report->summaryLine();
        self::assertStringContainsString('2 error(s)', $line);
        self::assertStringContainsString('1 warning(s)', $line);
        self::assertStringContainsString('Season 2008', $line);
    }

    /**
     * Negative-path guard: the duplicate detail must NOT claim which row is the
     * phantom (that would be confidently wrong advice). It may contain "likely a
     * phantom" but must not contain "delete" or the phrase "phantom is".
     */
    public function testDuplicateDetailDoesNotClaimWhichRowIsPhantom(): void
    {
        $report = $this->makeAudit(
            scheduleIndex: $this->oneGameSchedule(),
            duplicates: [$this->makeDuplicateRow()],
        );

        $detail = $report->findings[0]->detail;
        self::assertStringNotContainsString('delete', $detail);
        self::assertStringNotContainsString('phantom is', $detail);
        // Must still communicate the situation without asserting which side is phantom
        self::assertStringContainsString('likely a phantom', $detail);
    }
}
