<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\Contracts\BackupArchiveLocatorInterface;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ExtractFromBackupStep;

class ExtractFromBackupStepTest extends TestCase
{
    private BackupArchiveLocatorInterface $stubLocator;
    private ArchiveExtractorInterface $stubExtractor;
    private Season $stubSeason;

    protected function setUp(): void
    {
        $this->stubLocator = $this->createStub(BackupArchiveLocatorInterface::class);
        $this->stubExtractor = $this->createStub(ArchiveExtractorInterface::class);
        $this->stubSeason = $this->createStub(Season::class);
        $this->stubSeason->beginningYear = 2025;
        $this->stubSeason->endingYear = 2026;
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubExtractor,
            $this->stubSeason,
            '/tmp',
            'IBL5',
        );

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubExtractor,
            $this->stubSeason,
            '/tmp',
            'IBL5',
        );

        $this->assertSame('Backup extracted', $step->getLabel());
    }

    public function testSkipsWhenNoBackupFound(): void
    {
        $this->stubLocator->method('findLatestArchive')->willReturn(null);

        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubExtractor,
            $this->stubSeason,
            '/tmp',
            'IBL5',
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No backup found', $result->detail);
    }

    public function testExtractsFilesFromBackup(): void
    {
        $this->stubLocator->method('findLatestArchive')
            ->willReturn('/tmp/backups/25-26/25-26_15_reg-sim15.zip');
        $this->stubLocator->method('isProperlyNamed')->willReturn(true);

        // EXTENSIONS = ['sco'] — single extension, extraction succeeds
        $this->stubExtractor->method('extractSingleFile')->willReturn('/tmp/IBL5.sco');

        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubExtractor,
            $this->stubSeason,
            '/tmp',
            'IBL5',
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Extracted 1 files', $result->detail);
    }

    public function testSkipsRenameForProperlyNamedBackup(): void
    {
        $this->stubLocator->method('findLatestArchive')
            ->willReturn('/tmp/backups/25-26/25-26_15_reg-sim15.zip');
        $this->stubLocator->method('isProperlyNamed')->willReturn(true);
        $this->stubExtractor->method('extractSingleFile')->willReturn(false);
        // Note: extractFiles() builds filenames directly as filePrefix + ext,
        // not via extractor->jsbFilename(). No jsbFilename stub needed.

        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubExtractor,
            $this->stubSeason,
            '/tmp',
            'IBL5',
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        // No rename message in messages for properly named files
        foreach ($result->messages as $msg) {
            $this->assertStringNotContainsString('renamed from', $msg);
        }
    }

    public function testHandlesMissingFilesInArchiveGracefully(): void
    {
        $this->stubLocator->method('findLatestArchive')
            ->willReturn('/tmp/backups/25-26/25-26_15_reg-sim15.zip');
        $this->stubLocator->method('isProperlyNamed')->willReturn(true);

        // All extractions fail (no files in archive)
        $this->stubExtractor->method('extractSingleFile')->willReturn(false);
        // Note: extractFiles() builds filenames directly as filePrefix + ext,
        // not via extractor->jsbFilename(). No jsbFilename stub needed.

        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubExtractor,
            $this->stubSeason,
            '/tmp',
            'IBL5',
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Extracted 0 files', $result->detail);
    }
}
