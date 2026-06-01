<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use BulkImport\Contracts\BackupArchiveLocatorInterface;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ExtractFromBackupStep;

class ExtractFromBackupStepTest extends TestCase
{
    /** @var BackupArchiveLocatorInterface&\PHPUnit\Framework\MockObject\Stub */
    private BackupArchiveLocatorInterface $stubLocator;
    /** @var Season&\PHPUnit\Framework\MockObject\Stub */
    private Season $stubSeason;

    protected function setUp(): void
    {
        $this->stubLocator = self::createStub(BackupArchiveLocatorInterface::class);
        $this->stubSeason = self::createStub(Season::class);
        $this->stubSeason->beginningYear = 2025;
        $this->stubSeason->endingYear = 2026;
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubSeason,
            '/tmp',
        );

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubSeason,
            '/tmp',
        );

        $this->assertSame('Backup extracted', $step->getLabel());
    }

    public function testSkipsWhenNoBackupFound(): void
    {
        $this->stubLocator->method('findLatestArchive')->willReturn(null);

        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubSeason,
            '/tmp',
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No backup found', $result->detail);
    }

    public function testExtractsZeroFilesFromBackup(): void
    {
        $this->stubLocator->method('findLatestArchive')
            ->willReturn('/tmp/backups/25-26/25-26_15_reg-sim15.zip');
        $this->stubLocator->method('isProperlyNamed')->willReturn(true);

        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubSeason,
            '/tmp',
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Extracted 0 files', $result->detail);
    }

    public function testCustomBackupDirIsUsedWhenProvided(): void
    {
        $customDir = '/custom/olympics/backups';
        /** @var BackupArchiveLocatorInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockLocator = $this->createMock(BackupArchiveLocatorInterface::class);
        $mockLocator->expects($this->once())
            ->method('findLatestArchive')
            ->with($customDir)
            ->willReturn($customDir . '/Olympics2003.zip');
        $mockLocator->method('isProperlyNamed')->willReturn(true);

        $step = new ExtractFromBackupStep(
            $mockLocator,
            $this->stubSeason,
            '/tmp',
            $customDir,
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Olympics2003.zip', $result->detail);
    }

    public function testBackwardCompatibleWithThreeArgConstructor(): void
    {
        $this->stubLocator->method('findLatestArchive')->willReturn(null);

        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubSeason,
            '/tmp',
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No backup found', $result->detail);
    }

    public function testSkipsRenameForProperlyNamedBackup(): void
    {
        $this->stubLocator->method('findLatestArchive')
            ->willReturn('/tmp/backups/25-26/25-26_15_reg-sim15.zip');
        $this->stubLocator->method('isProperlyNamed')->willReturn(true);

        $step = new ExtractFromBackupStep(
            $this->stubLocator,
            $this->stubSeason,
            '/tmp',
        );
        $result = $step->execute();

        $this->assertTrue($result->success);
        foreach ($result->messages as $msg) {
            $this->assertStringNotContainsString('renamed from', $msg);
        }
    }
}
