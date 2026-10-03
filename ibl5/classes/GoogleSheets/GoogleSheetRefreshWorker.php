<?php

declare(strict_types=1);

namespace GoogleSheets;

use Clock\ClockInterface;
use GoogleSheets\Contracts\GoogleSheetConnectionRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * GoogleSheetRefreshWorker - drains the pending-refresh queue for the prod cron tick.
 *
 * Each row runs behind GoogleSheetExportService::refreshConnection(), which never
 * throws, so one GM's failure cannot stop the next. A time budget stops the loop
 * before it overruns the cron period; unstarted rows stay pending for the next tick.
 */
class GoogleSheetRefreshWorker
{
    public const EXIT_OK = 0;
    public const EXIT_ROW_FAILURES = 1;

    public function __construct(
        private readonly GoogleSheetConnectionRepositoryInterface $connections,
        private readonly GoogleSheetExportService $export,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param callable(string): void $out Receives one output line per call (no trailing newline)
     * @return int Exit code: 0 all ok or nothing pending, 1 when any row ended error or broken
     */
    public function run(int $limit, int $budgetSeconds, bool $dryRun, bool $all, callable $out): int
    {
        // --dry-run stays zero-write, so --all is ignored under it.
        if ($all && !$dryRun) {
            $this->connections->markAllActivePending();
        }

        $rows = $this->connections->findPending($limit);

        if ($dryRun) {
            foreach ($rows as $row) {
                $out(sprintf(
                    'user_id=%d spreadsheet_id=%s last_refresh_at=%s last_refresh_status=%s',
                    $row['user_id'],
                    $row['spreadsheet_id'],
                    $row['last_refresh_at'] ?? 'never',
                    $row['last_refresh_status'] ?? 'never'
                ));
            }
            $out('rows_to_write: ' . count($this->export->buildRows()));

            return self::EXIT_OK;
        }

        $start = $this->clock->now();
        $processed = 0;
        $failures = 0;
        foreach ($rows as $row) {
            if ($this->clock->now() - $start >= $budgetSeconds) {
                break;
            }

            $status = $this->export->refreshConnection($row);
            $out('user_id=' . $row['user_id'] . ' status=' . $status);
            $processed++;
            if ($status !== GoogleSheetExportService::RESULT_OK) {
                $failures++;
            }
        }

        $deferred = count($rows) - $processed;
        $out("processed={$processed} failed={$failures} deferred={$deferred}");
        $this->logger?->info('google sheet tick finished', [
            'processed' => $processed,
            'failed' => $failures,
            'deferred' => $deferred,
        ]);

        return $failures > 0 ? self::EXIT_ROW_FAILURES : self::EXIT_OK;
    }
}
