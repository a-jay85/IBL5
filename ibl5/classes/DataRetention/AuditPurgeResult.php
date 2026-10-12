<?php

declare(strict_types=1);

namespace DataRetention;

/**
 * Outcome of one audit-log retention purge.
 */
final readonly class AuditPurgeResult
{
    public function __construct(
        public int $cutoffEpoch,
        public int $eligible,
        public int $deleted,
        public bool $dryRun,
    ) {
    }
}
