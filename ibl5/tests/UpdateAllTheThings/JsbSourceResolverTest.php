<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings;

use BulkImport\ArchiveSelection;
use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\Contracts\BackupArchiveLocatorInterface;
use PHPUnit\Framework\TestCase;
use Updater\JsbSourceResolver;
use Updater\SourceProvenance;

class JsbSourceResolverTest extends TestCase
{
    /** @var BackupArchiveLocatorInterface&\PHPUnit\Framework\MockObject\Stub */
    private BackupArchiveLocatorInterface $stubLocator;
    /** @var ArchiveExtractorInterface&\PHPUnit\Framework\MockObject\Stub */
    private ArchiveExtractorInterface $stubExtractor;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->stubLocator = self::createStub(BackupArchiveLocatorInterface::class);
        $this->stubExtractor = self::createStub(ArchiveExtractorInterface::class);
        $this->tempDir = sys_get_temp_dir() . '/jsb_resolver_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                array_map('unlink', $files);
            }
            rmdir($this->tempDir);
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeSelection(string $path, array $overrides = []): ArchiveSelection
    {
        return new ArchiveSelection(
            path: $path,
            mtime: $overrides['mtime'] ?? 1700000000,
            candidateCount: $overrides['candidateCount'] ?? 1,
            selectedSeq: $overrides['selectedSeq'] ?? null,
            highestSeq: $overrides['highestSeq'] ?? null,
            highestSeqName: $overrides['highestSeqName'] ?? null,
            declaredSeason: $overrides['declaredSeason'] ?? null,
            directorySeason: $overrides['directorySeason'] ?? null,
            selectedSize: $overrides['selectedSize'] ?? 0,
            predecessorSize: $overrides['predecessorSize'] ?? null,
            predecessorName: $overrides['predecessorName'] ?? null,
        );
    }

    private function makeResolver(): JsbSourceResolver
    {
        return new JsbSourceResolver(
            $this->stubLocator,
            $this->stubExtractor,
            '/backups/25-26',
            $this->tempDir,
            'IBL5',
        );
    }

    // ---------------------------------------------------------------------------
    // Existing behaviour tests (updated to use describeSelection)
    // ---------------------------------------------------------------------------

    public function testReturnsContentsFromArchive(): void
    {
        $this->stubLocator->method('describeSelection')
            ->willReturn($this->makeSelection('/backups/25-26/archive.zip'));
        $this->stubExtractor->method('extractToString')->willReturn('archive-lge-data');

        $this->assertSame('archive-lge-data', $this->makeResolver()->getContents('lge'));
    }

    public function testFallsThroughToDiskWhenArchiveLacksFile(): void
    {
        $this->stubLocator->method('describeSelection')
            ->willReturn($this->makeSelection('/backups/25-26/archive.zip'));
        $this->stubExtractor->method('extractToString')->willReturn(false);

        file_put_contents($this->tempDir . '/IBL5.lge', 'disk-lge-data');

        $this->assertSame('disk-lge-data', $this->makeResolver()->getContents('lge'));
    }

    public function testFallsThroughToDiskWhenNoArchive(): void
    {
        // describeSelection returns null by default (stub default for nullable return type).
        file_put_contents($this->tempDir . '/IBL5.sch', 'disk-sch-data');

        $this->assertSame('disk-sch-data', $this->makeResolver()->getContents('sch'));
    }

    public function testReturnsNullWhenNeitherSourceHasFile(): void
    {
        // describeSelection returns null; no disk file → null result.
        $this->assertNull($this->makeResolver()->getContents('lge'));
    }

    public function testResolvesArchivePathLazilyPerCall(): void
    {
        /** @var BackupArchiveLocatorInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockLocator = $this->createMock(BackupArchiveLocatorInterface::class);
        $mockLocator->expects($this->exactly(2))
            ->method('describeSelection')
            ->willReturn(null);

        $resolver = new JsbSourceResolver(
            $mockLocator,
            $this->stubExtractor,
            '/backups/25-26',
            $this->tempDir,
            'IBL5',
        );

        $resolver->getContents('lge');
        $resolver->getContents('sch');
    }

    public function testUsesCorrectFilePrefixForArchive(): void
    {
        $this->stubLocator->method('describeSelection')
            ->willReturn($this->makeSelection('/backups/archive.zip'));

        /** @var ArchiveExtractorInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockExtractor = $this->createMock(ArchiveExtractorInterface::class);
        $mockExtractor->expects($this->once())
            ->method('extractToString')
            ->with('/backups/archive.zip', 'Olympics.lge')
            ->willReturn('olympics-data');

        $resolver = new JsbSourceResolver(
            $this->stubLocator,
            $mockExtractor,
            '/backups',
            $this->tempDir,
            'Olympics',
        );

        $this->assertSame('olympics-data', $resolver->getContents('lge'));
    }

    // ---------------------------------------------------------------------------
    // Phase 8 — describeLastSource() provenance tests
    // ---------------------------------------------------------------------------

    public function testDescribeLastSourceIsNullBeforeAnyRead(): void
    {
        $this->assertNull($this->makeResolver()->describeLastSource());
    }

    public function testDescribeLastSourceReportsArchiveWithParsedSeason(): void
    {
        $this->stubLocator->method('describeSelection')
            ->willReturn($this->makeSelection('/backups/25-26/25-26_10_reg-sim10.zip'));
        $this->stubExtractor->method('extractToString')->willReturn('contents');
        $this->stubExtractor->method('parseArchiveName')
            ->willReturn(['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026]);

        $resolver = $this->makeResolver();
        $resolver->getContents('sco');
        $provenance = $resolver->describeLastSource();

        $this->assertNotNull($provenance);
        $this->assertSame(SourceProvenance::KIND_ARCHIVE, $provenance->kind);
        $this->assertSame('25-26_10_reg-sim10.zip', $provenance->name);
        $this->assertSame('25-26', $provenance->declaredSeason);
        $this->assertSame(2026, $provenance->declaredSeasonEndingYear);
        $this->assertSame('reg-sim10', $provenance->declaredPhase);
        $this->assertTrue($provenance->isProperlyNamed());
    }

    public function testDescribeLastSourceHandlesMisnamedArchive(): void
    {
        $this->stubLocator->method('describeSelection')
            ->willReturn($this->makeSelection('/backups/25-26/IBL2526Sim10.zip'));
        $this->stubExtractor->method('extractToString')->willReturn('contents');
        $this->stubExtractor->method('parseArchiveName')->willReturn(null);

        $resolver = $this->makeResolver();
        $resolver->getContents('sco');
        $provenance = $resolver->describeLastSource();

        $this->assertNotNull($provenance);
        $this->assertSame(SourceProvenance::KIND_ARCHIVE, $provenance->kind);
        $this->assertSame('IBL2526Sim10.zip', $provenance->name);
        $this->assertNull($provenance->declaredSeasonEndingYear);
        $this->assertFalse($provenance->isProperlyNamed());
    }

    public function testDescribeLastSourceReportsDiskFallback(): void
    {
        // describeSelection returns null → no archive → disk fallback.
        file_put_contents($this->tempDir . '/IBL5.sco', 'disk-sco');

        $resolver = $this->makeResolver();
        $resolver->getContents('sco');
        $provenance = $resolver->describeLastSource();

        $this->assertNotNull($provenance);
        $this->assertSame(SourceProvenance::KIND_DISK, $provenance->kind);
        $this->assertSame('IBL5.sco', $provenance->name);
        $this->assertSame([], $provenance->selectionWarnings);
    }

    public function testDescribeLastSourceIsResetWhenReadFails(): void
    {
        // First read succeeds.
        $this->stubLocator->method('describeSelection')
            ->willReturn($this->makeSelection('/backups/25-26/25-26_10_reg-sim10.zip'));
        $this->stubExtractor->method('extractToString')->willReturn('ok');
        $this->stubExtractor->method('parseArchiveName')->willReturn(null);

        $resolver = $this->makeResolver();
        $resolver->getContents('sco');
        $this->assertNotNull($resolver->describeLastSource());

        // Second read finds nothing — provenance must reset to null.
        $stubLocator2 = self::createStub(BackupArchiveLocatorInterface::class);
        // describeSelection returns null by default
        $resolver2 = new JsbSourceResolver(
            $stubLocator2,
            $this->stubExtractor,
            '/backups/25-26',
            $this->tempDir,
            'NOPE',  // no NOPE.sco on disk
        );
        $resolver2->getContents('sco');

        $this->assertNull($resolver2->describeLastSource());
    }

    public function testSelectionWarningsReachProvenance(): void
    {
        // Build a selection with a sequence regression warning.
        $selection = new ArchiveSelection(
            path: '/backups/07-08/07-08_35_playoffs.zip',
            mtime: 1700000000,
            candidateCount: 3,
            selectedSeq: 35,
            highestSeq: 36,
            highestSeqName: '07-08_36_playoffs.zip',
            declaredSeason: '07-08',
            directorySeason: '07-08',
            selectedSize: 10_000_000,
            predecessorSize: null,
            predecessorName: null,
        );

        $this->stubLocator->method('describeSelection')->willReturn($selection);
        $this->stubExtractor->method('extractToString')->willReturn('sco-bytes');
        $this->stubExtractor->method('parseArchiveName')
            ->willReturn(['season' => '07-08', 'seq' => 35, 'phase' => 'playoffs', 'ending_year' => 2008]);

        $resolver = $this->makeResolver();
        $resolver->getContents('sco');
        $provenance = $resolver->describeLastSource();

        $this->assertNotNull($provenance);
        $this->assertNotEmpty($provenance->selectionWarnings);
        $this->assertStringContainsString('ARCHIVE SELECTION LOOKS STALE', $provenance->selectionWarnings[0]);
    }

    public function testHandlesDefaultStubSelection(): void
    {
        // describeSelection() default stub return is null.
        // The resolver must not fatal — it falls through to disk (no disk file → null).
        $this->assertNull($this->makeResolver()->getContents('sco'));
        $this->assertNull($this->makeResolver()->describeLastSource());
    }

    public function testDiskFallbackProvenanceHasEmptySelectionWarnings(): void
    {
        file_put_contents($this->tempDir . '/IBL5.lge', 'disk');

        $resolver = $this->makeResolver();
        $resolver->getContents('lge');
        $provenance = $resolver->describeLastSource();

        $this->assertNotNull($provenance);
        $this->assertSame(SourceProvenance::KIND_DISK, $provenance->kind);
        $this->assertSame([], $provenance->selectionWarnings);
    }
}
