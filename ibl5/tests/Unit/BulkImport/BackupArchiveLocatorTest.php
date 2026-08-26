<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\BackupArchiveLocator;
use BulkImport\Contracts\ArchiveExtractorInterface;
use PHPUnit\Framework\TestCase;

class BackupArchiveLocatorTest extends TestCase
{
    /** @var ArchiveExtractorInterface&\PHPUnit\Framework\MockObject\Stub */
    private ArchiveExtractorInterface $stubExtractor;
    private BackupArchiveLocator $locator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->stubExtractor = self::createStub(ArchiveExtractorInterface::class);
        $this->locator = new BackupArchiveLocator($this->stubExtractor);

        $this->tmpDir = sys_get_temp_dir() . '/backup-locator-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob($this->tmpDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testSeasonLabelFormat(): void
    {
        $this->assertSame('25-26', BackupArchiveLocator::seasonLabel(2025, 2026));
        $this->assertSame('99-00', BackupArchiveLocator::seasonLabel(1999, 2000));
        $this->assertSame('88-89', BackupArchiveLocator::seasonLabel(1988, 1989));
    }

    public function testReturnsNullForNonexistentDirectory(): void
    {
        $this->assertNull($this->locator->findLatestArchive('/nonexistent/path'));
    }

    public function testReturnsNullForEmptyDirectory(): void
    {
        $this->assertNull($this->locator->findLatestArchive($this->tmpDir));
    }

    public function testFindsLatestArchiveByMtime(): void
    {
        $older = $this->tmpDir . '/older.zip';
        $newer = $this->tmpDir . '/newer.zip';

        file_put_contents($older, 'old');
        touch($older, time() - 100);

        file_put_contents($newer, 'new');
        touch($newer, time());

        $result = $this->locator->findLatestArchive($this->tmpDir);

        $this->assertSame($newer, $result);
    }

    public function testIsProperlyNamedTrue(): void
    {
        $this->stubExtractor->method('parseArchiveName')
            ->willReturn(['season' => '25-26', 'seq' => 15, 'phase' => 'reg-sim15', 'ending_year' => 2026]);

        $this->assertTrue($this->locator->isProperlyNamed('/path/to/25-26_15_reg-sim15.zip'));
    }

    public function testIsProperlyNamedFalse(): void
    {
        $this->stubExtractor->method('parseArchiveName')->willReturn(null);

        $this->assertFalse($this->locator->isProperlyNamed('/path/to/IBL2526Sim15.zip'));
    }

    public function testGenerateStandardizedNameForRegularSeason(): void
    {
        // No properly-named archives exist yet → seq = 1
        $this->stubExtractor->method('parseArchiveName')->willReturn(null);

        $name = $this->locator->generateStandardizedName(
            $this->tmpDir,
            'zip',
            '25-26',
            'Regular Season',
            15,
        );

        $this->assertSame('25-26_01_reg-sim15.zip', $name);
    }

    public function testGenerateStandardizedNameForPlayoffs(): void
    {
        $this->stubExtractor->method('parseArchiveName')->willReturn(null);

        $name = $this->locator->generateStandardizedName(
            $this->tmpDir,
            'zip',
            '25-26',
            'Playoffs',
            1,
        );

        $this->assertSame('25-26_01_playoffs.zip', $name);
    }

    public function testSequenceNumberCountsExistingProperlyNamedArchives(): void
    {
        // Create 3 archives, 2 properly named
        file_put_contents($this->tmpDir . '/25-26_01_reg-sim01.zip', 'a');
        file_put_contents($this->tmpDir . '/25-26_02_reg-sim02.zip', 'b');
        file_put_contents($this->tmpDir . '/IBL2526Sim3.zip', 'c');

        $this->stubExtractor->method('parseArchiveName')
            ->willReturnCallback(static function (string $filename): ?array {
                if (str_starts_with($filename, '25-26_')) {
                    return ['season' => '25-26', 'seq' => 1, 'phase' => 'test', 'ending_year' => 2026];
                }
                return null;
            });

        $name = $this->locator->generateStandardizedName(
            $this->tmpDir,
            'zip',
            '25-26',
            'Regular Season',
            3,
        );

        // 2 properly named + 1 = seq 03
        $this->assertSame('25-26_03_reg-sim03.zip', $name);
    }

    // -------------------------------------------------------------------------
    // Phase 8 — describeSelection() and provenance-hardening tests
    // -------------------------------------------------------------------------

    /**
     * findLatestArchive() must still return the right path after the Phase 8
     * refactor that makes it delegate to describeSelection()->path.
     */
    public function testFindLatestArchiveStillReturnsPathAfterRefactor(): void
    {
        $older = $this->tmpDir . '/25-26_05_reg-sim05.zip';
        $newer = $this->tmpDir . '/25-26_10_reg-sim10.zip';

        file_put_contents($older, str_repeat('a', 500));
        file_put_contents($newer, str_repeat('b', 600));

        touch($older, time() - 200);
        touch($newer, time() - 10);

        $this->stubExtractor->method('parseArchiveName')
            ->willReturnCallback(static function (string $filename): ?array {
                $map = [
                    '25-26_05_reg-sim05.zip' => ['season' => '25-26', 'seq' => 5,  'phase' => 'reg-sim05', 'ending_year' => 2026],
                    '25-26_10_reg-sim10.zip' => ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026],
                ];
                return $map[$filename] ?? null;
            });

        $this->assertSame($newer, $this->locator->findLatestArchive($this->tmpDir));
    }

    /**
     * When two archives share an identical mtime, the tie-break must prefer the
     * one with the higher sequence number. Prior to §8.9.5 the strict-greater-than
     * comparison would have left the incumbent (first by alphabet) in place.
     */
    public function testTieBreaksOnSequenceNumberWhenMtimesAreEqual(): void
    {
        $lowSeq  = $this->tmpDir . '/25-26_05_reg-sim05.zip';
        $highSeq = $this->tmpDir . '/25-26_10_reg-sim10.zip';

        file_put_contents($lowSeq,  str_repeat('a', 100));
        file_put_contents($highSeq, str_repeat('b', 200));

        // Force identical modification times.
        $equalMtime = time() - 50;
        touch($lowSeq,  $equalMtime);
        touch($highSeq, $equalMtime);

        $this->stubExtractor->method('parseArchiveName')
            ->willReturnCallback(static function (string $filename): ?array {
                $map = [
                    '25-26_05_reg-sim05.zip' => ['season' => '25-26', 'seq' => 5,  'phase' => 'reg-sim05', 'ending_year' => 2026],
                    '25-26_10_reg-sim10.zip' => ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026],
                ];
                return $map[$filename] ?? null;
            });

        // The higher-sequence archive must win even though mtimes are identical.
        $this->assertSame($highSeq, $this->locator->findLatestArchive($this->tmpDir));
    }

    public function testDescribeSelectionReportsCandidateCountAndHighestSeq(): void
    {
        file_put_contents($this->tmpDir . '/25-26_05_reg-sim05.zip', str_repeat('a', 500));
        file_put_contents($this->tmpDir . '/25-26_10_reg-sim10.zip', str_repeat('b', 600));
        file_put_contents($this->tmpDir . '/IBL2526Sim3.zip',         str_repeat('c', 100));

        $this->stubExtractor->method('parseArchiveName')
            ->willReturnCallback(static function (string $filename): ?array {
                $map = [
                    '25-26_05_reg-sim05.zip' => ['season' => '25-26', 'seq' => 5,  'phase' => 'reg-sim05', 'ending_year' => 2026],
                    '25-26_10_reg-sim10.zip' => ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026],
                ];
                return $map[$filename] ?? null;
            });

        // Touch so seq=10 has the newest mtime.
        touch($this->tmpDir . '/25-26_10_reg-sim10.zip', time());

        $selection = $this->locator->describeSelection($this->tmpDir);

        $this->assertSame(3, $selection->candidateCount);
        $this->assertSame(10, $selection->highestSeq);
        $this->assertSame('25-26_10_reg-sim10.zip', $selection->highestSeqName);
    }

    public function testDescribeSelectionOnMissingDirectoryIsEmptyNotFatal(): void
    {
        $selection = $this->locator->describeSelection('/this/path/does/not/exist');

        $this->assertNull($selection->path);
        $this->assertSame(0, $selection->candidateCount);
        $this->assertSame([], $selection->warnings());
    }

    public function testLogsWarningLevelWhenSelectionIsSuspect(): void
    {
        // Create seq=10 (larger) and seq=11 (smaller) in the same phase — triggers size regression.
        $pred    = $this->tmpDir . '/07-08_35_playoffs.zip';
        $suspect = $this->tmpDir . '/07-08_36_playoffs.zip';

        file_put_contents($pred,    str_repeat('x', 10_000));
        file_put_contents($suspect, str_repeat('y', 9_000));   // ~10% smaller → fires detector B

        touch($pred,    time() - 100);
        touch($suspect, time());       // suspect is newest by mtime

        $this->stubExtractor->method('parseArchiveName')
            ->willReturnCallback(static function (string $filename): ?array {
                $map = [
                    '07-08_35_playoffs.zip' => ['season' => '07-08', 'seq' => 35, 'phase' => 'playoffs', 'ending_year' => 2008],
                    '07-08_36_playoffs.zip' => ['season' => '07-08', 'seq' => 36, 'phase' => 'playoffs', 'ending_year' => 2008],
                ];
                return $map[$filename] ?? null;
            });

        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('warning')
            ->with('backup_archive_selection_suspect');

        $locatorWithLogger = new BackupArchiveLocator($this->stubExtractor, $mockLogger);
        $locatorWithLogger->describeSelection($this->tmpDir);
    }

    public function testDoesNotLogWarningLevelOnCleanSelection(): void
    {
        file_put_contents($this->tmpDir . '/25-26_10_reg-sim10.zip', str_repeat('a', 500));

        $this->stubExtractor->method('parseArchiveName')
            ->willReturn(['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026]);

        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockLogger->expects($this->once())->method('info');
        $mockLogger->expects($this->never())->method('warning');

        $locatorWithLogger = new BackupArchiveLocator($this->stubExtractor, $mockLogger);
        $locatorWithLogger->describeSelection($this->tmpDir);
    }
}
