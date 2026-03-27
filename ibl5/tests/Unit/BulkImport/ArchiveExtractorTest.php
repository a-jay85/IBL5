<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\ArchiveExtractor;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * @covers \BulkImport\ArchiveExtractor
 */
final class ArchiveExtractorTest extends TestCase
{
    private ArchiveExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ArchiveExtractor();
    }

    // ── parseArchiveName ────────────────────────────────────────────────────

    public function testParseArchiveNameStandardRegSim(): void
    {
        $result = $this->extractor->parseArchiveName('00-01_06_reg-sim01.zip');

        self::assertNotNull($result);
        self::assertSame('00-01', $result['season']);
        self::assertSame(6, $result['seq']);
        self::assertSame('reg-sim01', $result['phase']);
        self::assertSame(2001, $result['ending_year']);
    }

    public function testParseArchiveNameFinals(): void
    {
        $result = $this->extractor->parseArchiveName('06-07_36_finals.zip');

        self::assertNotNull($result);
        self::assertSame('06-07', $result['season']);
        self::assertSame(36, $result['seq']);
        self::assertSame('finals', $result['phase']);
        self::assertSame(2007, $result['ending_year']);
    }

    public function testParseArchiveNameHeatEnd(): void
    {
        $result = $this->extractor->parseArchiveName('88-89_05_heat-end.zip');

        self::assertNotNull($result);
        self::assertSame('88-89', $result['season']);
        self::assertSame(5, $result['seq']);
        self::assertSame('heat-end', $result['phase']);
        self::assertSame(1989, $result['ending_year']);
    }

    public function testParseArchiveNamePlayoffs(): void
    {
        $result = $this->extractor->parseArchiveName('99-00_30_playoffs-rd2-gm1-3.zip');

        self::assertNotNull($result);
        self::assertSame('99-00', $result['season']);
        self::assertSame(30, $result['seq']);
        self::assertSame('playoffs-rd2-gm1-3', $result['phase']);
        self::assertSame(2000, $result['ending_year']);
    }

    public function testParseArchiveNameRarExtension(): void
    {
        $result = $this->extractor->parseArchiveName('00-01_13_reg-sim08.rar');

        self::assertNotNull($result);
        self::assertSame('00-01', $result['season']);
        self::assertSame(13, $result['seq']);
        self::assertSame('reg-sim08', $result['phase']);
    }

    public function testParseArchiveNameReturnsNullForInvalidFormat(): void
    {
        self::assertNull($this->extractor->parseArchiveName('IBL5HEAT00RR1.zip'));
        self::assertNull($this->extractor->parseArchiveName('random.zip'));
        self::assertNull($this->extractor->parseArchiveName(''));
    }

    public function testParseArchiveNameEndOfSeason(): void
    {
        $result = $this->extractor->parseArchiveName('93-94_41_end-of-season.zip');

        self::assertNotNull($result);
        self::assertSame('93-94', $result['season']);
        self::assertSame(41, $result['seq']);
        self::assertSame('end-of-season', $result['phase']);
        self::assertSame(1994, $result['ending_year']);
    }

    // ── seasonLabelToEndingYear ─────────────────────────────────────────────

    public function testSeasonLabelToEndingYear1900s(): void
    {
        self::assertSame(1989, $this->extractor->seasonLabelToEndingYear('88-89'));
        self::assertSame(1999, $this->extractor->seasonLabelToEndingYear('98-99'));
        self::assertSame(2000, $this->extractor->seasonLabelToEndingYear('99-00'));
    }

    public function testSeasonLabelToEndingYear2000s(): void
    {
        self::assertSame(2001, $this->extractor->seasonLabelToEndingYear('00-01'));
        self::assertSame(2007, $this->extractor->seasonLabelToEndingYear('06-07'));
    }

    public function testSeasonLabelToEndingYearInvalidReturnsZero(): void
    {
        self::assertSame(0, $this->extractor->seasonLabelToEndingYear('invalid'));
        self::assertSame(0, $this->extractor->seasonLabelToEndingYear(''));
    }

    // ── detectFormat ────────────────────────────────────────────────────────

    public function testDetectFormatZip(): void
    {
        self::assertSame('zip', $this->extractor->detectFormat('/path/to/file.zip'));
        self::assertSame('zip', $this->extractor->detectFormat('/path/to/file.ZIP'));
    }

    public function testDetectFormatRar(): void
    {
        self::assertSame('rar', $this->extractor->detectFormat('/path/to/file.rar'));
        self::assertSame('rar', $this->extractor->detectFormat('/path/to/file.RAR'));
    }

    public function testDetectFormatDefaultsToZip(): void
    {
        self::assertSame('zip', $this->extractor->detectFormat('/path/to/file.unknown'));
    }

    // ── extractSingleFile + cleanupTemp (integration with real zip) ────────

    public function testExtractSingleFileFromZip(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ibl5_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        $zipPath = $tmpDir . '/test.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('IBL5.plr', 'test player data content');
        $zip->addFromString('IBL5.sco', 'test score data content');
        $zip->close();

        try {
            // Extract existing file
            $extracted = $this->extractor->extractSingleFile($zipPath, 'IBL5.plr', $tmpDir);
            self::assertIsString($extracted);
            self::assertFileExists($extracted);
            self::assertSame('test player data content', file_get_contents($extracted));

            // Cleanup
            $this->extractor->cleanupTemp($extracted);
            self::assertFileDoesNotExist($extracted);

            // Extract non-existent file returns false
            $missing = $this->extractor->extractSingleFile($zipPath, 'IBL5.nonexistent', $tmpDir);
            self::assertFalse($missing);
        } finally {
            // Cleanup temp directory
            array_map('unlink', glob($tmpDir . '/*') ?: []);
            rmdir($tmpDir);
        }
    }

    public function testCleanupTempNonExistentFileDoesNotError(): void
    {
        $this->extractor->cleanupTemp('/tmp/does_not_exist_' . uniqid());

        // No exception = pass
        self::assertTrue(true);
    }

    // ── findLastArchive + findHeatEndArchive (filesystem integration) ──────

    public function testFindLastArchiveSelectsHighestSequence(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ibl5_find_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            // Create dummy zip files with different sequence numbers
            foreach (['00-01_06_reg-sim01.zip', '00-01_15_reg-sim10.zip', '00-01_33_finals.zip'] as $name) {
                $zip = new ZipArchive();
                $zip->open($tmpDir . '/' . $name, ZipArchive::CREATE);
                $zip->addFromString('dummy', '');
                $zip->close();
            }

            $result = $this->extractor->findLastArchive($tmpDir);
            self::assertNotNull($result);
            self::assertStringEndsWith('00-01_33_finals.zip', $result);
        } finally {
            array_map('unlink', glob($tmpDir . '/*') ?: []);
            rmdir($tmpDir);
        }
    }

    public function testFindLastArchiveReturnsNullForEmptyDir(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ibl5_empty_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            self::assertNull($this->extractor->findLastArchive($tmpDir));
        } finally {
            rmdir($tmpDir);
        }
    }

    public function testFindHeatEndArchivePrioritizesHeatEnd(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ibl5_heat_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            foreach ([
                '00-01_02_heat-rr1.zip',
                '00-01_03_heat-rr2.zip',
                '00-01_04_heat-wb.zip',
                '00-01_05_heat-end.zip',
            ] as $name) {
                $zip = new ZipArchive();
                $zip->open($tmpDir . '/' . $name, ZipArchive::CREATE);
                $zip->addFromString('dummy', '');
                $zip->close();
            }

            $result = $this->extractor->findHeatEndArchive($tmpDir);
            self::assertNotNull($result);
            self::assertStringEndsWith('00-01_05_heat-end.zip', $result);
        } finally {
            array_map('unlink', glob($tmpDir . '/*') ?: []);
            rmdir($tmpDir);
        }
    }

    public function testFindHeatEndArchiveFallsBackToLastHeat(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ibl5_heat_fb_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            // Only non-priority heat slugs
            foreach ([
                '93-94_05_heat-pool1.zip',
                '93-94_06_heat-pool2.zip',
            ] as $name) {
                $zip = new ZipArchive();
                $zip->open($tmpDir . '/' . $name, ZipArchive::CREATE);
                $zip->addFromString('dummy', '');
                $zip->close();
            }

            $result = $this->extractor->findHeatEndArchive($tmpDir);
            self::assertNotNull($result);
            // Should pick the highest-sequenced heat archive
            self::assertStringEndsWith('93-94_06_heat-pool2.zip', $result);
        } finally {
            array_map('unlink', glob($tmpDir . '/*') ?: []);
            rmdir($tmpDir);
        }
    }

    public function testFindHeatEndArchiveReturnsNullWhenNoHeat(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ibl5_noheat_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            $zip = new ZipArchive();
            $zip->open($tmpDir . '/00-01_06_reg-sim01.zip', ZipArchive::CREATE);
            $zip->addFromString('dummy', '');
            $zip->close();

            self::assertNull($this->extractor->findHeatEndArchive($tmpDir));
        } finally {
            array_map('unlink', glob($tmpDir . '/*') ?: []);
            rmdir($tmpDir);
        }
    }

    // ── jsbFilename ─────────────────────────────────────────────────────────

    public function testJsbFilename(): void
    {
        self::assertSame('IBL5.plr', $this->extractor->jsbFilename('plr'));
        self::assertSame('IBL5.sco', $this->extractor->jsbFilename('sco'));
        self::assertSame('IBL5.car', $this->extractor->jsbFilename('car'));
    }
}
