<?php

declare(strict_types=1);

namespace Boxscore;

/**
 * Immutable result object produced by ScheduleReconciliationAudit::run().
 *
 * exitCode() living here instead of in the CLI is what makes the strict/warning
 * split unit-testable without running a process.
 */
final class ScheduleAuditReport
{
    /** @param list<AuditFinding> $findings */
    public function __construct(
        public readonly int $seasonYear,
        public readonly array $findings,
        public readonly int $scheduledGames,
        public readonly int $boxscoreGames,
    ) {
    }

    /** @return list<AuditFinding> */
    public function errors(): array
    {
        return array_values(
            array_filter($this->findings, static fn (AuditFinding $f): bool => $f->severity === AuditFinding::SEVERITY_ERROR)
        );
    }

    /** @return list<AuditFinding> */
    public function warnings(): array
    {
        return array_values(
            array_filter($this->findings, static fn (AuditFinding $f): bool => $f->severity === AuditFinding::SEVERITY_WARNING)
        );
    }

    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    /**
     * Returns 1 when there are any error-severity findings, 0 otherwise.
     *
     * Missing-boxscore findings are warnings and do NOT raise the exit code,
     * so the audit stays green on every mid-sim run when a game import is still
     * pending.
     */
    public function exitCode(): int
    {
        return $this->hasErrors() ? 1 : 0;
    }

    /**
     * Findings of kind KIND_DUPLICATE_TRIPLE only.
     *
     * The duplicate invariant has its own CI hosts, which must fail on a
     * duplicate and stay green on orphan or missing-boxscore findings.
     *
     * @return list<AuditFinding>
     */
    public function duplicateFindings(): array
    {
        return array_values(
            array_filter($this->findings, static fn (AuditFinding $f): bool => $f->kind === AuditFinding::KIND_DUPLICATE_TRIPLE)
        );
    }

    /**
     * Returns 1 when there is at least one duplicate-triple finding, 0 otherwise.
     *
     * Deliberately narrower than exitCode(): orphan findings are errors there but
     * are not this invariant's concern.
     */
    public function duplicateExitCode(): int
    {
        return $this->duplicateFindings() === [] ? 0 : 1;
    }

    /**
     * Returns a one-line summary for terminal output.
     *
     * Example: "Season 2008: 1250 scheduled, 1249 with boxscores — 621 error(s), 1 warning(s)"
     */
    public function summaryLine(): string
    {
        return sprintf(
            'Season %d: %d scheduled, %d with boxscores — %d error(s), %d warning(s)',
            $this->seasonYear,
            $this->scheduledGames,
            $this->boxscoreGames,
            count($this->errors()),
            count($this->warnings())
        );
    }
}
