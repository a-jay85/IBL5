<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings;

use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\Contracts\BackupArchiveLocatorInterface;
use PHPUnit\Framework\TestCase;
use Updater\JsbSourceResolver;

class JsbSourceResolverTest extends TestCase
{
    private BackupArchiveLocatorInterface $stubLocator;
    private ArchiveExtractorInterface $stubExtractor;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->stubLocator = $this->createStub(BackupArchiveLocatorInterface::class);
        $this->stubExtractor = $this->createStub(ArchiveExtractorInterface::class);
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

    public function testReturnsContentsFromArchive(): void
    {
        $this->stubLocator->method('findLatestArchive')->willReturn('/backups/25-26/archive.zip');
        $this->stubExtractor->method('extractToString')->willReturn('archive-lge-data');

        $resolver = new JsbSourceResolver(
            $this->stubLocator,
            $this->stubExtractor,
            '/backups/25-26',
            $this->tempDir,
            'IBL5',
        );

        $this->assertSame('archive-lge-data', $resolver->getContents('lge'));
    }

    public function testFallsThroughToDiskWhenArchiveLacksFile(): void
    {
        $this->stubLocator->method('findLatestArchive')->willReturn('/backups/25-26/archive.zip');
        $this->stubExtractor->method('extractToString')->willReturn(false);

        file_put_contents($this->tempDir . '/IBL5.lge', 'disk-lge-data');

        $resolver = new JsbSourceResolver(
            $this->stubLocator,
            $this->stubExtractor,
            '/backups/25-26',
            $this->tempDir,
            'IBL5',
        );

        $this->assertSame('disk-lge-data', $resolver->getContents('lge'));
    }

    public function testFallsThroughToDiskWhenNoArchive(): void
    {
        $this->stubLocator->method('findLatestArchive')->willReturn(null);

        file_put_contents($this->tempDir . '/IBL5.sch', 'disk-sch-data');

        $resolver = new JsbSourceResolver(
            $this->stubLocator,
            $this->stubExtractor,
            '/backups/25-26',
            $this->tempDir,
            'IBL5',
        );

        $this->assertSame('disk-sch-data', $resolver->getContents('sch'));
    }

    public function testReturnsNullWhenNeitherSourceHasFile(): void
    {
        $this->stubLocator->method('findLatestArchive')->willReturn(null);

        $resolver = new JsbSourceResolver(
            $this->stubLocator,
            $this->stubExtractor,
            '/backups/25-26',
            $this->tempDir,
            'IBL5',
        );

        $this->assertNull($resolver->getContents('lge'));
    }

    public function testResolvesArchivePathLazilyPerCall(): void
    {
        $mockLocator = $this->createMock(BackupArchiveLocatorInterface::class);
        $mockLocator->expects($this->exactly(2))
            ->method('findLatestArchive')
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
        $this->stubLocator->method('findLatestArchive')->willReturn('/backups/archive.zip');

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
}
