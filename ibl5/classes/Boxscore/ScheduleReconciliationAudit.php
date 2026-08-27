<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreRepositoryInterface;

/**
 * Two-sided reconciliation audit between ibl_box_scores_teams and ibl_schedule.
 *
 * Severity policy (non-negotiable):
 *   - KIND_ORPHAN          → SEVERITY_ERROR   (boxscore with no schedule row should not exist)
 *   - KIND_DUPLICATE_TRIPLE → SEVERITY_ERROR  (same matchup at two game_of_that_day values)
 *   - KIND_MISSING_BOXSCORE → SEVERITY_WARNING (played game pending import — never hard-fail)
 *
 * The audit has NO write path. It reads and reports only.
 *
 * Fail-open: when a season has zero schedule rows (was never imported), the orphan
 * direction is skipped entirely rather than flagging every boxscore as corruption.
 * This mirrors ScheduleMembershipGuard::isEnabled() from Phase 3.
 */
final class ScheduleReconciliationAudit
{
    public function __construct(
        private readonly BoxscoreRepositoryInterface $repository,
    ) {
    }

    /**
     * Run the full reconciliation for one season.
     *
     * Findings are emitted in order: orphans, duplicates, missing — so strict
     * (error-severity) findings always precede advisory ones.
     */
    public function run(int $seasonYear): ScheduleAuditReport
    {
        // Load the schedule index to count scheduled games and determine isEnabled.
        $scheduleIndex = $this->repository->fetchScheduledGameIndex($seasonYear);

        $scheduledGames = 0;
        foreach ($scheduleIndex as $visitors) {
            foreach ($visitors as $homes) {
                $scheduledGames += count($homes);
            }
        }

        // Fail-open: skip orphan checks for seasons with no schedule data.
        $isEnabled = $scheduleIndex !== [];

        $findings = [];

        if ($isEnabled) {
            // Orphan direction: boxscore games with no matching schedule row (error).
            foreach ($this->repository->findOrphanBoxscoreGames($seasonYear) as $row) {
                $findings[] = new AuditFinding(
                    AuditFinding::KIND_ORPHAN,
                    AuditFinding::SEVERITY_ERROR,
                    (string) $row['game_date'],
                    (int) $row['visitor_teamid'],
                    (int) $row['home_teamid'],
                    sprintf(
                        'not in ibl_schedule for season %d (gotd %d, "%s")',
                        $seasonYear,
                        (int) $row['game_of_that_day'],
                        (string) $row['name']
                    )
                );
            }

            // Duplicate-triple direction: same (date, visitor, home) at >1 gotd (error).
            foreach ($this->repository->findDuplicateTripleGames($seasonYear) as $row) {
                $findings[] = new AuditFinding(
                    AuditFinding::KIND_DUPLICATE_TRIPLE,
                    AuditFinding::SEVERITY_ERROR,
                    (string) $row['game_date'],
                    (int) $row['visitor_teamid'],
                    (int) $row['home_teamid'],
                    sprintf(
                        'recorded at %d different game_of_that_day values (%s) — one is likely a phantom import',
                        (int) $row['occurrences'],
                        (string) $row['gotds']
                    )
                );
            }
        }

        // Missing direction: scheduled and played games with no boxscore (warning).
        // This runs even when the schedule is empty (returns 0 rows — safe).
        $missingRows = $this->repository->findScheduledGamesWithoutBoxscores($seasonYear);
        foreach ($missingRows as $row) {
            $findings[] = new AuditFinding(
                AuditFinding::KIND_MISSING_BOXSCORE,
                AuditFinding::SEVERITY_WARNING,
                (string) $row['game_date'],
                (int) $row['visitor_teamid'],
                (int) $row['home_teamid'],
                sprintf(
                    'scheduled and played (%d-%d) but no boxscore rows found',
                    (int) $row['visitor_score'],
                    (int) $row['home_score']
                )
            );
        }

        // boxscoreGames: scheduled games that have matching boxscores.
        // The missing-direction count is the definitive measure of games without boxscores;
        // 0-0 (unplayed) schedule rows are excluded by the missing query and thus counted
        // as "having" boxscores for summary purposes.
        $boxscoreGames = $scheduledGames - count($missingRows);

        return new ScheduleAuditReport(
            $seasonYear,
            $findings,
            $scheduledGames,
            $boxscoreGames,
        );
    }
}
