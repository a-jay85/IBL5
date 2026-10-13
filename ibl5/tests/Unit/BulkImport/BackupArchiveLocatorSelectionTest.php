<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\BackupArchiveLocator;
use BulkImport\Contracts\ArchiveExtractorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Characterization tests for BackupArchiveLocator::describeSelection().
 *
 * Pins the tie-break, predecessor, season, and log-memo paths that
 * BackupArchiveLocatorTest does not reach, so the method can be split safely.
 */
final class BackupArchiveLocatorSelectionTest extends TestCase
{
    /** @var ArchiveExtractorInterface&\PHPUnit\Framework\MockObject\Stub */
    private ArchiveExtractorInterface $stubExtractor;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->stubExtractor = self::createStub(ArchiveExtractorInterface::class);

        $this->tmpDir = sys_get_temp_dir() . '/backup-locator-selection-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tmpDir)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->tmpDir);
    }

    public function testTieKeepsProperlyNamedArchiveOverLaterSortedLegacyArchive(): void
    {
        $t = time() - 1000;
        $proper = $this->writeArchive($this->tmpDir, '25-26_10_reg-sim10.zip', 100, $t);
        $this->writeArchive($this->tmpDir, 'IBL2526Sim15.zip', 100, $t);
        $this->stubParseMap([
            '25-26_10_reg-sim10.zip' => ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026],
        ]);

        $selection = $this->locator()->describeSelection($this->tmpDir);

        $this->assertSame($proper, $selection->path);
        $this->assertSame(10, $selection->selectedSeq);
    }

    public function testTiePrefersProperlyNamedArchiveOverEarlierSortedLegacyArchive(): void
    {
        $t = time() - 1000;
        $this->writeArchive($this->tmpDir, '00-upload.zip', 100, $t);
        $proper = $this->writeArchive($this->tmpDir, '25-26_10_reg-sim10.zip', 100, $t);
        $this->stubParseMap([
            '25-26_10_reg-sim10.zip' => ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026],
        ]);

        $selection = $this->locator()->describeSelection($this->tmpDir);

        $this->assertSame($proper, $selection->path);
        $this->assertSame(10, $selection->selectedSeq);
    }

    public function testTieBetweenLegacyArchivesSelectsLaterSortedNameWithNoSequenceMetadata(): void
    {
        $t = time() - 1000;
        $this->writeArchive($this->tmpDir, 'IBL2526Sim14.zip', 100, $t);
        $later = $this->writeArchive($this->tmpDir, 'IBL2526Sim15.zip', 100, $t);
        $this->stubParseMap([]);

        $selection = $this->locator()->describeSelection($this->tmpDir);

        $this->assertSame($later, $selection->path);
        $this->assertSame(2, $selection->candidateCount);
        $this->assertNull($selection->selectedSeq);
        $this->assertNull($selection->highestSeq);
        $this->assertNull($selection->highestSeqName);
        $this->assertNull($selection->declaredSeason);
        $this->assertNull($selection->predecessorSize);
        $this->assertNull($selection->predecessorName);
    }

    public function testTieWithEqualSequenceKeepsEarlierSortedArchive(): void
    {
        $t = time() - 1000;
        $first = $this->writeArchive($this->tmpDir, '25-26_10_reg-sim10.zip', 100, $t);
        $this->writeArchive($this->tmpDir, '25-26_10_reg-sim10b.zip', 100, $t);
        $this->stubParseMap([
            '25-26_10_reg-sim10.zip' => ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026],
            '25-26_10_reg-sim10b.zip' => ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10b', 'ending_year' => 2026],
        ]);

        $selection = $this->locator()->describeSelection($this->tmpDir);

        $this->assertSame($first, $selection->path);
    }

    public function testNewerMtimeOutranksHigherSequence(): void
    {
        $t = time() - 1000;
        $lowerSeqNewer = $this->writeArchive($this->tmpDir, '25-26_05_reg-sim05.zip', 100, $t + 100);
        $this->writeArchive($this->tmpDir, '25-26_10_reg-sim10.zip', 100, $t);
        $this->stubParseMap([
            '25-26_05_reg-sim05.zip' => ['season' => '25-26', 'seq' => 5, 'phase' => 'reg-sim05', 'ending_year' => 2026],
            '25-26_10_reg-sim10.zip' => ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026],
        ]);

        $selection = $this->locator()->describeSelection($this->tmpDir);

        $this->assertSame($lowerSeqNewer, $selection->path);
        $this->assertSame(5, $selection->selectedSeq);
        $this->assertSame(10, $selection->highestSeq);
        $this->assertSame('25-26_10_reg-sim10.zip', $selection->highestSeqName);
        $this->assertSame($t + 100, $selection->mtime);
    }

    public function testPredecessorIsHighestLowerSequenceInSamePhaseFamily(): void
    {
        $t = time() - 1000;
        $this->writeArchive($this->tmpDir, '25-26_07_reg-sim07.zip', 200, $t);
        $this->writeArchive($this->tmpDir, '25-26_08_reg-sim08.zip', 300, $t + 10);
        $this->writeArchive($this->tmpDir, '25-26_09_heat.zip', 900, $t + 20);
        $selected = $this->writeArchive($this->tmpDir, '25-26_10_reg-sim10.zip', 500, $t + 30);
        $this->stubParseMap([
            '25-26_07_reg-sim07.zip' => ['season' => '25-26', 'seq' => 7, 'phase' => 'reg-sim07', 'ending_year' => 2026],
            '25-26_08_reg-sim08.zip' => ['season' => '25-26', 'seq' => 8, 'phase' => 'reg-sim08', 'ending_year' => 2026],
            '25-26_09_heat.zip' => ['season' => '25-26', 'seq' => 9, 'phase' => 'heat', 'ending_year' => 2026],
            '25-26_10_reg-sim10.zip' => ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026],
        ]);

        $selection = $this->locator()->describeSelection($this->tmpDir);

        $this->assertSame($selected, $selection->path);
        $this->assertSame('25-26_08_reg-sim08.zip', $selection->predecessorName);
        $this->assertSame(300, $selection->predecessorSize);
        $this->assertSame(500, $selection->selectedSize);
    }

    public function testSelectionReportsDeclaredAndDirectorySeasonFromSeparateSources(): void
    {
        $t = time() - 1000;
        $dir = $this->tmpDir . '/25-26';
        mkdir($dir);
        $this->writeArchive($dir, '24-25_03_playoffs.zip', 400, $t);
        $this->stubParseMap([
            '24-25_03_playoffs.zip' => ['season' => '24-25', 'seq' => 3, 'phase' => 'playoffs', 'ending_year' => 2025],
        ]);

        $selection = $this->locator()->describeSelection($dir);

        $this->assertSame('24-25', $selection->declaredSeason);
        $this->assertSame('25-26', $selection->directorySeason);
        $this->assertSame(400, $selection->selectedSize);
        $this->assertNull($selection->predecessorName);
    }

    public function testDirectorySeasonIsNullWhenDirectoryNameIsNotASeasonLabel(): void
    {
        $t = time() - 1000;
        $this->writeArchive($this->tmpDir, '25-26_03_playoffs.zip', 100, $t);
        $this->stubParseMap([
            '25-26_03_playoffs.zip' => ['season' => '25-26', 'seq' => 3, 'phase' => 'playoffs', 'ending_year' => 2026],
        ]);

        $selection = $this->locator()->describeSelection($this->tmpDir);

        $this->assertNull($selection->directorySeason);
        $this->assertSame('25-26', $selection->declaredSeason);
    }

    public function testLogsSelectionOncePerDistinctPathAcrossCalls(): void
    {
        $t = time() - 1000;
        $this->writeArchive($this->tmpDir, '25-26_05_reg-sim05.zip', 100, $t);
        $this->stubParseMap([
            '25-26_05_reg-sim05.zip' => ['season' => '25-26', 'seq' => 5, 'phase' => 'reg-sim05', 'ending_year' => 2026],
            '25-26_06_reg-sim06.zip' => ['season' => '25-26', 'seq' => 6, 'phase' => 'reg-sim06', 'ending_year' => 2026],
        ]);
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))
            ->method('info')
            ->with('backup_archive_selected', self::anything());
        $locator = $this->locator($logger);

        $locator->describeSelection($this->tmpDir);
        $locator->describeSelection($this->tmpDir);

        $this->writeArchive($this->tmpDir, '25-26_06_reg-sim06.zip', 200, $t + 50);
        $locator->describeSelection($this->tmpDir);
    }

    public function testEqualSequenceArchivesKeepFirstSortedHighestAndNeverSelfPredecessor(): void
    {
        $t = time() - 1000;
        $rar = $this->writeArchive($this->tmpDir, '25-26_10_reg-sim10.rar', 100, $t);
        $this->writeArchive($this->tmpDir, '25-26_10_reg-sim10.zip', 100, $t);
        $parsed = ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026];
        $this->stubParseMap([
            '25-26_10_reg-sim10.rar' => $parsed,
            '25-26_10_reg-sim10.zip' => $parsed,
        ]);

        $selection = $this->locator()->describeSelection($this->tmpDir);

        $this->assertSame($rar, $selection->path);
        $this->assertSame('25-26_10_reg-sim10.rar', $selection->highestSeqName);
        $this->assertNull($selection->predecessorName);
        $this->assertNull($selection->predecessorSize);
    }

    private function writeArchive(string $dir, string $name, int $bytes, int $mtime): string
    {
        $path = $dir . '/' . $name;
        file_put_contents($path, str_repeat('x', $bytes));
        touch($path, $mtime);

        return $path;
    }

    /**
     * @param array<string, array{season: string, seq: int, phase: string, ending_year: int}> $map
     */
    private function stubParseMap(array $map): void
    {
        $this->stubExtractor->method('parseArchiveName')
            ->willReturnCallback(static fn (string $filename): ?array => $map[$filename] ?? null);
    }

    private function locator(?LoggerInterface $logger = null): BackupArchiveLocator
    {
        return new BackupArchiveLocator($this->stubExtractor, $logger);
    }
}
