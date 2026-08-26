<?php

declare(strict_types=1);

namespace Boxscore;

/**
 * An immutable record of one discrepancy found by ScheduleReconciliationAudit.
 *
 * describe() returns plain text with no HTML and no ANSI colour.
 * Phase 10's CLI adds colour at the print site so the same strings are usable
 * from a web context later.
 */
final class AuditFinding
{
    public const SEVERITY_ERROR   = 'error';
    public const SEVERITY_WARNING = 'warning';

    public const KIND_ORPHAN           = 'orphan';
    public const KIND_DUPLICATE_TRIPLE = 'duplicate_triple';
    public const KIND_MISSING_BOXSCORE = 'missing_boxscore';

    public function __construct(
        public readonly string $kind,
        public readonly string $severity,
        public readonly string $gameDate,
        public readonly int $visitorTeamId,
        public readonly int $homeTeamId,
        public readonly string $detail,
    ) {
    }

    /**
     * Returns a plain-text description of the finding.
     *
     * Format: "YYYY-MM-DD visitor@home — detail"
     * Matches the describe() convention established by RejectedGame (Phase 3).
     */
    public function describe(): string
    {
        return sprintf(
            '%s %d@%d — %s',
            $this->gameDate,
            $this->visitorTeamId,
            $this->homeTeamId,
            $this->detail
        );
    }
}
