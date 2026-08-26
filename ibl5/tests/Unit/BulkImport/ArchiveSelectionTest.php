<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\ArchiveSelection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ArchiveSelection value object.
 *
 * These tests validate the three warning detectors (A/B/C), the name() helper,
 * and the null-safe behaviour when archive names cannot be parsed.
 */
class ArchiveSelectionTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Build a clean selection that produces no warnings.
     * Callers override only the fields relevant to the detector under test.
     *
     * @param array<string, mixed> $overrides
     */
    private function makeSelection(array $overrides = []): ArchiveSelection
    {
        $defaults = [
            'path'            => '/backups/25-26/25-26_10_reg-sim10.zip',
            'mtime'           => 1700000000,
            'candidateCount'  => 5,
            'selectedSeq'     => 10,
            'highestSeq'      => 10,
            'highestSeqName'  => '25-26_10_reg-sim10.zip',
            'declaredSeason'  => '25-26',
            'directorySeason' => '25-26',
            'selectedSize'    => 1_000_000,
            'predecessorSize' => null,
            'predecessorName' => null,
        ];

        $args = array_merge($defaults, $overrides);

        return new ArchiveSelection(
            path: $args['path'],
            mtime: $args['mtime'],
            candidateCount: $args['candidateCount'],
            selectedSeq: $args['selectedSeq'],
            highestSeq: $args['highestSeq'],
            highestSeqName: $args['highestSeqName'],
            declaredSeason: $args['declaredSeason'],
            directorySeason: $args['directorySeason'],
            selectedSize: $args['selectedSize'],
            predecessorSize: $args['predecessorSize'],
            predecessorName: $args['predecessorName'],
        );
    }

    // ---------------------------------------------------------------------------
    // Clean selection
    // ---------------------------------------------------------------------------

    public function testCleanSelectionProducesNoWarnings(): void
    {
        $selection = $this->makeSelection();

        $this->assertSame([], $selection->warnings());
        $this->assertFalse($selection->isSequenceRegression());
        $this->assertFalse($selection->isSizeRegression());
        $this->assertFalse($selection->isSeasonMisfiled());
    }

    // ---------------------------------------------------------------------------
    // Detector A — sequence regression
    // ---------------------------------------------------------------------------

    public function testSequenceRegressionWarnsNamingBothArchives(): void
    {
        // Selected seq=5 but seq=10 exists in the directory → stale by mtime.
        $selection = $this->makeSelection([
            'path'           => '/backups/25-26/25-26_05_reg-sim05.zip',
            'selectedSeq'    => 5,
            'highestSeq'     => 10,
            'highestSeqName' => '25-26_10_reg-sim10.zip',
        ]);

        $this->assertTrue($selection->isSequenceRegression());
        $warnings = $selection->warnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('25-26_05_reg-sim05.zip', $warnings[0]);
        $this->assertStringContainsString('25-26_10_reg-sim10.zip', $warnings[0]);
        $this->assertStringContainsString('ARCHIVE SELECTION LOOKS STALE', $warnings[0]);
    }

    public function testNoSequenceRegressionWhenSelectedIsHighest(): void
    {
        $selection = $this->makeSelection([
            'selectedSeq' => 10,
            'highestSeq'  => 10,
        ]);

        $this->assertFalse($selection->isSequenceRegression());
        $this->assertSame([], $selection->warnings());
    }

    // ---------------------------------------------------------------------------
    // Detector B — phase-scoped size regression
    // ---------------------------------------------------------------------------

    public function testSizeRegressionFiresAtTheIncidentMargin(): void
    {
        // Mirrors the 07-08_36_playoffs.zip incident: ~3.78% regression.
        $predecessorSize = 10_000_000;
        $selectedSize    = 9_622_000; // -3.78%
        $selection = $this->makeSelection([
            'path'            => '/backups/07-08/07-08_36_playoffs.zip',
            'selectedSize'    => $selectedSize,
            'predecessorSize' => $predecessorSize,
            'predecessorName' => '07-08_35_playoffs.zip',
        ]);

        $this->assertTrue($selection->isSizeRegression());
        $warnings = $selection->warnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('ARCHIVE SIZE REGRESSION', $warnings[0]);
        $this->assertStringContainsString('07-08_36_playoffs.zip', $warnings[0]);
        $this->assertStringContainsString('07-08_35_playoffs.zip', $warnings[0]);
    }

    public function testSizeRegressionDoesNotFireAtHalfPercent(): void
    {
        // 0.5% is below the 1% tolerance — no warning expected.
        $selection = $this->makeSelection([
            'selectedSize'    => 995_000,
            'predecessorSize' => 1_000_000,
            'predecessorName' => '25-26_09_reg-sim09.zip',
        ]);

        $this->assertFalse($selection->isSizeRegression());
        $this->assertSame([], $selection->warnings());
    }

    public function testSizeRegressionDoesNotFireOnGrowth(): void
    {
        $selection = $this->makeSelection([
            'selectedSize'    => 1_100_000,
            'predecessorSize' => 1_000_000,
            'predecessorName' => '25-26_09_reg-sim09.zip',
        ]);

        $this->assertFalse($selection->isSizeRegression());
        $this->assertSame([], $selection->warnings());
    }

    public function testSizeRegressionSilentWhenPredecessorIsNull(): void
    {
        $selection = $this->makeSelection([
            'selectedSize'    => 100,
            'predecessorSize' => null,
            'predecessorName' => null,
        ]);

        $this->assertFalse($selection->isSizeRegression());
        $this->assertSame([], $selection->warnings());
    }

    // ---------------------------------------------------------------------------
    // Detector C — season misfiled
    // ---------------------------------------------------------------------------

    public function testSeasonMisfiledWarnsWhenDeclaredSeasonDiffersFromDirectory(): void
    {
        // An 07-08 archive sits in the 08-09 directory.
        $selection = $this->makeSelection([
            'path'            => '/backups/08-09/07-08_36_playoffs.zip',
            'declaredSeason'  => '07-08',
            'directorySeason' => '08-09',
        ]);

        $this->assertTrue($selection->isSeasonMisfiled());
        $warnings = $selection->warnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('ARCHIVE MISFILED', $warnings[0]);
        $this->assertStringContainsString('07-08', $warnings[0]);
        $this->assertStringContainsString('08-09', $warnings[0]);
    }

    public function testSeasonMisfiledSilentWhenDirectorySeasonIsNull(): void
    {
        // Directory name is not in NN-NN format → directorySeason null → no warning.
        $selection = $this->makeSelection([
            'declaredSeason'  => '25-26',
            'directorySeason' => null,
        ]);

        $this->assertFalse($selection->isSeasonMisfiled());
        $this->assertSame([], $selection->warnings());
    }

    // ---------------------------------------------------------------------------
    // Mis-named archive — predicates that require parsed seq must be silent
    // ---------------------------------------------------------------------------

    public function testMisnamedArchiveProducesNoSequenceOrSizeWarning(): void
    {
        // IBL2526Sim23.zip cannot be parsed → selectedSeq null.
        $selection = $this->makeSelection([
            'path'            => '/backups/25-26/IBL2526Sim23.zip',
            'selectedSeq'     => null,
            'highestSeq'      => null,
            'highestSeqName'  => null,
            'declaredSeason'  => null,
            'directorySeason' => '25-26',
            'predecessorSize' => null,
            'predecessorName' => null,
        ]);

        $this->assertFalse($selection->isProperlyNamed());
        $this->assertFalse($selection->isSequenceRegression());
        $this->assertFalse($selection->isSizeRegression());
        $this->assertSame([], $selection->warnings());
    }

    // ---------------------------------------------------------------------------
    // name() helper
    // ---------------------------------------------------------------------------

    public function testNameReturnsBasenameNotFullPath(): void
    {
        $selection = $this->makeSelection([
            'path' => '/long/absolute/path/to/25-26/25-26_10_reg-sim10.zip',
        ]);

        $this->assertSame('25-26_10_reg-sim10.zip', $selection->name());
    }

    // ---------------------------------------------------------------------------
    // Empty directory / null selection
    // ---------------------------------------------------------------------------

    public function testEmptyDirectorySelectionIsNullPathWithNoWarnings(): void
    {
        // When describeSelection() finds no archives, it returns an ArchiveSelection
        // with path=null and all nullable fields null.
        $selection = new ArchiveSelection(
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

        $this->assertNull($selection->path);
        $this->assertSame('', $selection->name());
        $this->assertSame([], $selection->warnings());
        $this->assertFalse($selection->isProperlyNamed());
        $this->assertFalse($selection->isSequenceRegression());
        $this->assertFalse($selection->isSizeRegression());
        $this->assertFalse($selection->isSeasonMisfiled());
    }
}
