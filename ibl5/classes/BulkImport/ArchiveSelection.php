<?php

declare(strict_types=1);

namespace BulkImport;

use BasketballStats\StatsFormatter;

/**
 * The outcome of ranking a season backup directory: which archive won,
 * and every fact needed to judge whether that choice looks wrong.
 *
 * Pure value object — no filesystem access, no logger, no extractor.
 * The locator does the I/O and hands finished numbers to the constructor.
 * Every predicate returns false when its inputs are null so that a directory
 * of entirely mis-named uploads produces zero warnings rather than a crash.
 */
final class ArchiveSelection
{
    /** Fractional size drop tolerated within a phase before detector B warns. */
    public const SIZE_REGRESSION_TOLERANCE = 0.01;

    /**
     * @param ?string $path           Full path to the selected archive, or null when none found.
     * @param int     $mtime          Unix timestamp of the selected archive (0 when path is null).
     * @param int     $candidateCount Number of zip/rar files found in the directory.
     * @param ?int    $selectedSeq    Sequence number parsed from the selected archive name, or null.
     * @param ?int    $highestSeq     Highest sequence number found in the directory, or null.
     * @param ?string $highestSeqName Basename of the archive with the highest sequence number.
     * @param ?string $declaredSeason Season label declared in the selected archive name (e.g. "07-08").
     * @param ?string $directorySeason Season label derived from basename($seasonBackupDir),
     *                                 or null when the directory name does not match /^\d{2}-\d{2}$/.
     * @param int     $selectedSize   Byte size of the selected archive (0 when path is null).
     * @param ?int    $predecessorSize Byte size of the preceding archive in the same phase, or null.
     * @param ?string $predecessorName Basename of the preceding archive in the same phase, or null.
     */
    public function __construct(
        public readonly ?string $path,
        public readonly int $mtime,
        public readonly int $candidateCount,
        public readonly ?int $selectedSeq,
        public readonly ?int $highestSeq,
        public readonly ?string $highestSeqName,
        public readonly ?string $declaredSeason,
        public readonly ?string $directorySeason,
        public readonly int $selectedSize,
        public readonly ?int $predecessorSize,
        public readonly ?string $predecessorName,
    ) {
    }

    /**
     * Basename of the selected archive, or '' when no archive was selected.
     */
    public function name(): string
    {
        return $this->path !== null ? basename($this->path) : '';
    }

    /**
     * Whether the selected archive follows the standardized naming convention.
     */
    public function isProperlyNamed(): bool
    {
        return $this->selectedSeq !== null;
    }

    /**
     * Detector A — fires when a higher-sequenced archive exists in the directory
     * but a lower-sequenced one won by modification time.
     *
     * Returns false when either seq is null (un-parseable names).
     */
    public function isSequenceRegression(): bool
    {
        if ($this->selectedSeq === null || $this->highestSeq === null) {
            return false;
        }

        return $this->selectedSeq < $this->highestSeq;
    }

    /**
     * Detector B — fires when the selected archive is smaller than its immediate
     * predecessor in the same phase by more than SIZE_REGRESSION_TOLERANCE.
     *
     * Returns false when no predecessor data is available or the predecessor size is zero.
     */
    public function isSizeRegression(): bool
    {
        if ($this->predecessorSize === null || $this->predecessorSize === 0) {
            return false;
        }

        $delta = ($this->selectedSize - $this->predecessorSize) / $this->predecessorSize;

        return $delta < -self::SIZE_REGRESSION_TOLERANCE;
    }

    /**
     * Detector C — fires when the selected archive's declared season differs from
     * the season label in the directory name.
     *
     * Returns false when either season label is null (arbitrary directory names are safe).
     */
    public function isSeasonMisfiled(): bool
    {
        if ($this->declaredSeason === null || $this->directorySeason === null) {
            return false;
        }

        return $this->declaredSeason !== $this->directorySeason;
    }

    /**
     * Operator-readable warnings describing why the selection looks suspicious.
     *
     * Returns an empty array on a clean selection. Ordering: C (most specific),
     * then A (most common), then B (most subtle).
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];

        // Detector C — season misfiled (most specific diagnosis)
        if ($this->isSeasonMisfiled()) {
            $warnings[] = sprintf(
                'ARCHIVE MISFILED: %s declares season %s but sits in the %s backup directory. Importing it will write last season\'s games under this season\'s dates.',
                $this->name(),
                (string) $this->declaredSeason,
                (string) $this->directorySeason,
            );
        }

        // Detector A — sequence regression (most common staleness signal)
        if ($this->isSequenceRegression()) {
            $warnings[] = sprintf(
                'ARCHIVE SELECTION LOOKS STALE: picked %s (seq %d) by modification time, but %s (seq %d) is the highest-numbered archive in %s. A re-uploaded or touched older archive will be imported as if it were the newest sim.',
                $this->name(),
                (int) $this->selectedSeq,
                (string) $this->highestSeqName,
                (int) $this->highestSeq,
                $this->directorySeason ?? 'this directory',
            );
        }

        // Detector B — phase-scoped size regression (most subtle staleness signal)
        if ($this->isSizeRegression() && $this->predecessorSize !== null && $this->predecessorSize > 0) {
            $pct = abs(($this->selectedSize - $this->predecessorSize) / $this->predecessorSize) * 100.0;
            $warnings[] = sprintf(
                'ARCHIVE SIZE REGRESSION: %s is %s bytes, %.1f%% smaller than %s (%s bytes) in the same phase. Season archives grow monotonically; a shrinking one is usually a stale snapshot renamed forward. Verify before trusting this import.',
                $this->name(),
                StatsFormatter::formatTotal($this->selectedSize),
                $pct,
                (string) $this->predecessorName,
                StatsFormatter::formatTotal($this->predecessorSize),
            );
        }

        return $warnings;
    }
}
