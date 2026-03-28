<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\BackupArchiveLocator;
use BulkImport\Contracts\ArchiveExtractorInterface;
use PHPUnit\Framework\TestCase;

class BackupArchiveLocatorTest extends TestCase
{
    private ArchiveExtractorInterface $stubExtractor;
    private BackupArchiveLocator $locator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->stubExtractor = $this->createStub(ArchiveExtractorInterface::class);
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
}
