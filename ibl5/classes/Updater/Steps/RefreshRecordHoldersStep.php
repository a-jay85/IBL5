<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Rebuild the Record Holders cache row from box-score data.
 *
 * Writes only to DatabaseCache rows — no transaction needed; a cache rebuild
 * that fails must not roll back the import that preceded it.
 *
 * Skips when the updater run has already used more than the budget of its
 * 300 s time limit; the 5 AM cron remains the backstop for a skipped run.
 *
 * IBL-only — Olympics league does not use this step.
 */
final class RefreshRecordHoldersStep implements PipelineStepInterface
{
    public const DEFAULT_BUDGET_SECONDS = 180.0;

    /**
     * @param \Closure(): \RecordHolders\CachedRecordHoldersService $serviceFactory
     */
    public function __construct(
        private readonly \Closure $serviceFactory,
        private readonly \Clock\ClockInterface $clock,
        private readonly float $startedAt,
        private readonly float $budgetSeconds = self::DEFAULT_BUDGET_SECONDS,
    ) {
    }

    public static function fromDb(\mysqli $db, \Clock\ClockInterface $clock, float $startedAt): self
    {
        return new self(
            static function () use ($db): \RecordHolders\CachedRecordHoldersService {
                $repo = new \RecordHolders\RecordHoldersRepository($db);
                $inner = new \RecordHolders\RecordHoldersService($repo);
                return new \RecordHolders\CachedRecordHoldersService($inner, new \Cache\DatabaseCache($db));
            },
            $clock,
            $startedAt,
        );
    }

    public function getLabel(): string
    {
        return 'record holders cache refreshed';
    }

    public function execute(): StepResult
    {
        $elapsed = $this->clock->microtime() - $this->startedAt;
        if ($elapsed > $this->budgetSeconds) {
            return StepResult::skipped(
                $this->getLabel(),
                sprintf(
                    'updater already ran %.1f s, over the %.0f s budget; the 5 AM cron will refresh it',
                    $elapsed,
                    $this->budgetSeconds,
                ),
            );
        }

        try {
            $before = $this->clock->microtime();
            $service = ($this->serviceFactory)();
            $records = $service->rebuildCache();
        } catch (\Throwable $e) {
            return StepResult::failure($this->getLabel(), $e->getMessage());
        }

        return StepResult::success(
            $this->getLabel(),
            sprintf('%d record groups in %.1f s', count($records), $this->clock->microtime() - $before),
        );
    }
}
