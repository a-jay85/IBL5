<?php

declare(strict_types=1);

namespace BulkImport;

use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\Contracts\BackupArchiveLocatorInterface;

/**
 * Locates and validates backup archives in season directories.
 *
 * Handles both properly-named archives ({season}_{NN}_{phase}.{ext})
 * and mis-named uploads (e.g. IBL2526Sim15.zip) by falling back to
 * modification time when no standardized name is found.
 */
class BackupArchiveLocator implements BackupArchiveLocatorInterface
{
    /** @var array<string, string> Maps Season::$phase to naming convention slug */
    private const PHASE_SLUG_MAP = [
        'Preseason' => 'preseason',
        'HEAT' => 'heat',
        'Regular Season' => 'reg-sim',
        'Playoffs' => 'playoffs',
        'Draft' => 'offseason-postdraft',
        'Free Agency' => 'offseason-postfa',
    ];

    /** De-duplication memo: prevents re-logging the same archive multiple times per run. */
    private ?string $lastLoggedSelection = null;

    public function __construct(
        private readonly ArchiveExtractorInterface $extractor,
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
    }

    /**
     * Find the most recent archive in a season's backup directory.
     *
     * One-line wrapper over describeSelection() — the ranking logic lives there.
     * Having a single ranking implementation prevents divergence when
     * ExtractFromBackupStep calls this while JsbSourceResolver calls describeSelection().
     *
     * @param string $seasonBackupDir Full path to the season backup directory
     * @return string|null Full path to the archive, or null if none found
     */
    public function findLatestArchive(string $seasonBackupDir): ?string
    {
        return $this->describeSelection($seasonBackupDir)->path;
    }

    /**
     * Rank a season backup directory and return the full selection outcome.
     *
     * Includes mtime-based ranking with deterministic tie-break, provenance
     * metadata, predecessor size lookup (for detector B), directory-season
     * comparison (for detector C), and audit-channel logging.
     */
    public function describeSelection(string $seasonBackupDir): ArchiveSelection
    {
        $empty = new ArchiveSelection(
            path: null,
            mtime: 0,
            candidateCount: 0,
            selectedSeq: null,
            highestSeq: null,
            highestSeqName: null,
            declaredSeason: null,
            directorySeason: null,
            selectedSize: 0,
            predecessorSize: null,
            predecessorName: null,
        );

        if (!is_dir($seasonBackupDir)) {
            return $empty;
        }

        $archives = $this->listArchives($seasonBackupDir);
        if ($archives === []) {
            return $empty;
        }

        // Parse all archive names up front so each file is visited once.
        /** @var array<string, array{season: string, seq: int, phase: string, ending_year: int}|null> $parsed */
        $parsed = [];
        foreach ($archives as $path) {
            $parsed[$path] = $this->extractor->parseArchiveName(basename($path));
        }

        // Find the highest sequence number across all properly-named archives.
        $highestSeq = null;
        $highestSeqName = null;
        foreach ($archives as $path) {
            $p = $parsed[$path];
            if ($p === null) {
                continue;
            }
            if ($highestSeq === null || $p['seq'] > $highestSeq) {
                $highestSeq = $p['seq'];
                $highestSeqName = basename($path);
            }
        }

        // Rank archives: primary key = mtime desc, tie-break = seq desc, then basename desc.
        $latest = null;
        $latestMtime = 0;
        $latestSeq = null;

        foreach ($archives as $path) {
            $mtime = filemtime($path);
            if ($mtime === false) {
                continue;
            }
            $seq = $parsed[$path]['seq'] ?? null;
            if ($latest === null
                || $mtime > $latestMtime
                || ($mtime === $latestMtime && $this->outranksOnTie($seq, $path, $latestSeq, $latest))
            ) {
                $latestMtime = $mtime;
                $latestSeq = $seq;
                $latest = $path;
            }
        }

        // Derive directory season from the directory basename (must match /^\d{2}-\d{2}$/).
        $dirBasename = basename($seasonBackupDir);
        $directorySeason = preg_match('/^\d{2}-\d{2}$/', $dirBasename) === 1 ? $dirBasename : null;

        // Collect metadata for the selected archive.
        $selectedParsed = $latest !== null ? $parsed[$latest] : null;
        $selectedSeq = $selectedParsed['seq'] ?? null;
        $declaredSeason = $selectedParsed['season'] ?? null;
        $selectedPhase = $selectedParsed['phase'] ?? null;
        $selectedSize = $latest !== null ? ((int) filesize($latest)) : 0;

        // Find the predecessor in the same normalized phase for the size-regression detector.
        $predecessorSize = null;
        $predecessorName = null;
        if ($selectedSeq !== null && $selectedPhase !== null) {
            $selectedNorm = $this->normalizePhase($selectedPhase);
            $predSeq = null;
            foreach ($archives as $path) {
                $p = $parsed[$path];
                if ($p === null) {
                    continue;
                }
                if ($p['seq'] >= $selectedSeq) {
                    continue;
                }
                if ($this->normalizePhase($p['phase']) !== $selectedNorm) {
                    continue;
                }
                if ($predSeq === null || $p['seq'] > $predSeq) {
                    $predSeq = $p['seq'];
                    $predecessorSize = (int) filesize($path);
                    $predecessorName = basename($path);
                }
            }
        }

        $selection = new ArchiveSelection(
            path: $latest,
            mtime: $latestMtime,
            candidateCount: count($archives),
            selectedSeq: $selectedSeq,
            highestSeq: $highestSeq,
            highestSeqName: $highestSeqName,
            declaredSeason: $declaredSeason,
            directorySeason: $directorySeason,
            selectedSize: $selectedSize,
            predecessorSize: $predecessorSize,
            predecessorName: $predecessorName,
        );

        // Log once per distinct archive selection; six-plus calls per updater run means
        // an unconditional info() would bury the one line an operator needs to read.
        if ($selection->path !== null && $selection->path !== $this->lastLoggedSelection) {
            $this->lastLoggedSelection = $selection->path;
            $logger = $this->logger ?? \Logging\LoggerFactory::getChannel('audit');
            $logger->info('backup_archive_selected', [
                'archive'          => $selection->name(),
                'dir'              => $seasonBackupDir,
                'mtime'            => $selection->mtime,
                'properly_named'   => $selection->isProperlyNamed(),
                'candidates'       => $selection->candidateCount,
                'selected_seq'     => $selection->selectedSeq,
                'highest_seq'      => $selection->highestSeq,
                'declared_season'  => $selection->declaredSeason,
                'directory_season' => $selection->directorySeason,
                'size'             => $selection->selectedSize,
            ]);
            if ($selection->warnings() !== []) {
                $logger->warning('backup_archive_selection_suspect', [
                    'archive'  => $selection->name(),
                    'warnings' => $selection->warnings(),
                ]);
            }
        }

        return $selection;
    }

    /**
     * Build the season label from beginning and ending years.
     *
     * @return string E.g., "25-26" for season ending 2026
     */
    public static function seasonLabel(int $beginningYear, int $endingYear): string
    {
        return sprintf('%02d-%02d', $beginningYear % 100, $endingYear % 100);
    }

    /**
     * Check if an archive follows the standardized naming convention.
     *
     * @param string $archivePath Full path to the archive file
     */
    public function isProperlyNamed(string $archivePath): bool
    {
        return $this->extractor->parseArchiveName(basename($archivePath)) !== null;
    }

    /**
     * Generate the standardized name for a backup based on current season state.
     *
     * @param string $seasonBackupDir Directory containing existing archives (for sequence numbering)
     * @param string $archiveExtension File extension without dot (e.g. 'zip', 'rar')
     * @param string $seasonLabel Season label (e.g. "25-26")
     * @param string $phase Current season phase (e.g. "Regular Season")
     * @param int $phaseSimNumber Sim number within the current phase
     * @return string New filename (not path), e.g., "25-26_15_reg-sim15.zip"
     */
    public function generateStandardizedName(
        string $seasonBackupDir,
        string $archiveExtension,
        string $seasonLabel,
        string $phase,
        int $phaseSimNumber,
    ): string {
        $nextSeq = $this->countProperlyNamedArchives($seasonBackupDir) + 1;
        $phaseSlug = $this->buildPhaseSlug($phase, $phaseSimNumber);

        return sprintf('%s_%02d_%s.%s', $seasonLabel, $nextSeq, $phaseSlug, $archiveExtension);
    }

    /**
     * Determine whether a candidate archive outranks the incumbent on an mtime tie.
     *
     * Priority: non-null seq beats null; higher seq beats lower; later basename breaks
     * the remaining tie. Preferring an older-by-sequence archive on ties is the incident's
     * failure shape in miniature, so this is a genuine behavior change from the pre-Phase-8
     * strict-greater-than comparison.
     */
    private function outranksOnTie(?int $seq, string $path, ?int $incSeq, string $incumbent): bool
    {
        if ($seq !== null && $incSeq === null) {
            return true;
        }
        if ($seq === null && $incSeq !== null) {
            return false;
        }
        if ($seq !== null && $incSeq !== null) {
            return $seq > $incSeq;
        }

        return strcmp($path, $incumbent) > 0;
    }

    /**
     * Normalize a phase slug for comparison by stripping trailing digits.
     *
     * "reg-sim23" and "reg-sim00" both become "reg-sim", so the size-regression
     * detector only compares archives within the same logical phase family.
     * Phase transitions (e.g. HEAT → Regular Season) are legitimately size-changing
     * and must not fire as false positives.
     */
    private function normalizePhase(string $phase): string
    {
        return rtrim($phase, '0123456789');
    }

    /**
     * Build the phase slug from season phase and sim number.
     *
     * Regular Season gets a precise sim number (e.g., "reg-sim15").
     * Other phases use generic slugs.
     */
    private function buildPhaseSlug(string $phase, int $phaseSimNumber): string
    {
        $baseSlug = self::PHASE_SLUG_MAP[$phase] ?? 'unknown';

        if ($phase === 'Regular Season') {
            return sprintf('reg-sim%02d', $phaseSimNumber);
        }

        return $baseSlug;
    }

    /**
     * Count archives in a directory that follow the standardized naming convention.
     */
    private function countProperlyNamedArchives(string $seasonBackupDir): int
    {
        if (!is_dir($seasonBackupDir)) {
            return 0;
        }

        $count = 0;
        foreach ($this->listArchives($seasonBackupDir) as $path) {
            if ($this->extractor->parseArchiveName(basename($path)) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * List all archive files (zip/rar) in a directory.
     *
     * @return list<string> Full paths, sorted alphabetically
     */
    private function listArchives(string $dir): array
    {
        /** @var list<string>|false $files */
        $files = glob($dir . '/*.{zip,rar,ZIP,RAR}', GLOB_BRACE);
        if ($files === false || $files === []) {
            return [];
        }

        sort($files);

        return $files;
    }
}
