<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use Boxscore\BoxscoreProcessor;
use BulkImport\BulkImportRunner;
use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\FileTypeHandler;
use BulkImport\JsbFileType;
use JsbParser\Contracts\JsbImportServiceInterface;
use JsbParser\JsbImportResult;
use JsbParser\PlayerIdResolver;
use LeagueConfig\LeagueConfigService;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserServiceInterface;

/**
 * @covers \BulkImport\BulkImportRunner
 */
class BulkImportRunnerTest extends TestCase
{
    private string $backupsDir;

    /** @var ArchiveExtractorInterface&\PHPUnit\Framework\MockObject\Stub */
    private ArchiveExtractorInterface $stubExtractor;

    /** @var JsbImportServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private JsbImportServiceInterface $stubJsb;

    /** @var PlayerIdResolver&\PHPUnit\Framework\MockObject\Stub */
    private PlayerIdResolver $stubResolver;

    private FileTypeHandler $handler;
    private BulkImportRunner $runner;

    protected function setUp(): void
    {
        $this->backupsDir = sys_get_temp_dir() . '/ibl5_runner_test_' . bin2hex(random_bytes(8));
        mkdir($this->backupsDir, 0700, true);

        $this->stubExtractor = self::createStub(ArchiveExtractorInterface::class);
        $this->stubJsb = self::createStub(JsbImportServiceInterface::class);
        $stubBoxscore = self::createStub(BoxscoreProcessor::class);
        $stubPlr = self::createStub(PlrParserServiceInterface::class);
        $stubLge = self::createStub(LeagueConfigService::class);

        $this->handler = new FileTypeHandler(
            $this->stubJsb,
            $stubBoxscore,
            $stubPlr,
            $stubLge,
        );

        $this->stubResolver = self::createStub(PlayerIdResolver::class);
        $stubDb = self::createStub(\mysqli::class);

        $this->runner = new BulkImportRunner(
            $this->stubExtractor,
            $this->handler,
            $this->stubResolver,
            $stubDb,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->backupsDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        /** @var list<string> $items */
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testRunWithNoFileTypesProducesEmptySummary(): void
    {
        ob_start();
        $summary = $this->runner->run($this->backupsDir, [], false, false, null);
        ob_end_clean();

        self::assertSame(0, $summary->filesProcessed);
        self::assertSame(0, $summary->totalInserted);
        self::assertSame(0, $summary->totalErrors);
    }

    public function testRunWithNoSeasonDirsProducesEmptySummary(): void
    {
        $this->stubExtractor->method('seasonLabelToEndingYear')->willReturn(2007);

        ob_start();
        $summary = $this->runner->run(
            $this->backupsDir,
            [JsbFileType::Car],
            false,
            false,
            null,
        );
        ob_end_clean();

        self::assertSame(0, $summary->filesProcessed);
    }

    public function testRunSkipsEntriesWhenArchiveNotFound(): void
    {
        $seasonDir = $this->backupsDir . '/06-07';
        mkdir($seasonDir, 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')->willReturn(2007);
        $this->stubExtractor->method('findLastArchive')->willReturn('/tmp/fake-archive.zip');
        $this->stubExtractor->method('jsbFilename')->willReturn('IBL5.car');
        // extractSingleFile returns false → resolveFilePath returns null → "File not found"
        $this->stubExtractor->method('extractSingleFile')->willReturn(false);

        ob_start();
        $summary = $this->runner->run(
            $this->backupsDir,
            [JsbFileType::Car],
            false,
            false,
            null,
        );
        ob_end_clean();

        self::assertSame(0, $summary->filesProcessed);
        self::assertSame(0, $summary->totalErrors);
    }

    public function testRunDispatchesToHandlerAndAggregatesResults(): void
    {
        $seasonDir = $this->backupsDir . '/06-07';
        mkdir($seasonDir, 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')->willReturn(2007);
        $this->stubExtractor->method('findLastArchive')->willReturn('/tmp/fake-archive.zip');
        $this->stubExtractor->method('jsbFilename')->willReturn('IBL5.car');

        // Create a real temp file for extraction to succeed
        $tmpDir = sys_get_temp_dir() . '/ibl5_import_' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0700, true);
        $extractedFile = $tmpDir . '/IBL5.car';
        touch($extractedFile);

        $this->stubExtractor->method('extractSingleFile')->willReturn($extractedFile);

        $result = new JsbImportResult();
        $result->addInserted(5);
        $result->addUpdated(3);
        $this->stubJsb->method('processCarFile')->willReturn($result);

        ob_start();
        $summary = $this->runner->run(
            $this->backupsDir,
            [JsbFileType::Car],
            false,
            false,
            null,
        );
        ob_end_clean();

        self::assertSame(1, $summary->filesProcessed);
        self::assertSame(5, $summary->totalInserted);
        self::assertSame(3, $summary->totalUpdated);

        // Cleanup temp dir in case runner didn't remove it
        if (is_dir($tmpDir)) {
            $this->removeDir($tmpDir);
        }
    }

    public function testRunCleansUpExtractedFilesAfterImport(): void
    {
        $seasonDir = $this->backupsDir . '/06-07';
        mkdir($seasonDir, 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')->willReturn(2007);
        $this->stubExtractor->method('findLastArchive')->willReturn('/tmp/fake-archive.zip');
        $this->stubExtractor->method('jsbFilename')->willReturn('IBL5.car');

        $tmpDir = sys_get_temp_dir() . '/ibl5_import_' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0700, true);
        $extractedFile = $tmpDir . '/IBL5.car';
        touch($extractedFile);

        $this->stubExtractor->method('extractSingleFile')->willReturn($extractedFile);

        $result = new JsbImportResult();
        $this->stubJsb->method('processCarFile')->willReturn($result);

        // cleanupTemp is called by the runner — track that the file path was cleaned
        $cleanedPaths = [];
        $this->stubExtractor->method('cleanupTemp')
            ->willReturnCallback(static function (string $path) use (&$cleanedPaths): void {
                $cleanedPaths[] = $path;
                if (file_exists($path)) {
                    unlink($path);
                }
            });

        ob_start();
        $this->runner->run(
            $this->backupsDir,
            [JsbFileType::Car],
            false,
            false,
            null,
        );
        ob_end_clean();

        self::assertContains($extractedFile, $cleanedPaths);

        if (is_dir($tmpDir)) {
            $this->removeDir($tmpDir);
        }
    }

    public function testRunCleansUpEvenWhenImportThrows(): void
    {
        $seasonDir = $this->backupsDir . '/06-07';
        mkdir($seasonDir, 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')->willReturn(2007);
        $this->stubExtractor->method('findLastArchive')->willReturn('/tmp/fake-archive.zip');
        $this->stubExtractor->method('jsbFilename')->willReturn('IBL5.car');

        $tmpDir = sys_get_temp_dir() . '/ibl5_import_' . bin2hex(random_bytes(8));
        mkdir($tmpDir, 0700, true);
        $extractedFile = $tmpDir . '/IBL5.car';
        touch($extractedFile);

        $this->stubExtractor->method('extractSingleFile')->willReturn($extractedFile);

        $this->stubJsb->method('processCarFile')
            ->willThrowException(new \RuntimeException('Import failed'));

        $cleanedPaths = [];
        $this->stubExtractor->method('cleanupTemp')
            ->willReturnCallback(static function (string $path) use (&$cleanedPaths): void {
                $cleanedPaths[] = $path;
                if (file_exists($path)) {
                    unlink($path);
                }
            });

        ob_start();
        $summary = $this->runner->run(
            $this->backupsDir,
            [JsbFileType::Car],
            false,
            false,
            null,
        );
        ob_end_clean();

        self::assertContains($extractedFile, $cleanedPaths);
        self::assertSame(1, $summary->totalErrors);

        if (is_dir($tmpDir)) {
            $this->removeDir($tmpDir);
        }
    }

    public function testDryRunPrintsExpectedReport(): void
    {
        $seasonDir = $this->backupsDir . '/06-07';
        mkdir($seasonDir, 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')->willReturn(2007);
        $this->stubExtractor->method('findLastArchive')->willReturn('/tmp/fake-archive.zip');
        $this->stubExtractor->method('jsbFilename')->willReturn('IBL5.car');

        ob_start();
        $summary = $this->runner->run(
            $this->backupsDir,
            [JsbFileType::Car],
            true,
            false,
            null,
        );
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('06-07', $output);
        self::assertStringContainsString('2007', $output);
        self::assertStringContainsString('Regular Season/Playoffs', $output);
        self::assertStringContainsString('Total: 1 entries for .car (career stats)', $output);
        self::assertSame(0, $summary->filesProcessed);
    }

    public function testRunWithSeasonFilterSkipsNonMatchingSeasons(): void
    {
        mkdir($this->backupsDir . '/06-07', 0700, true);
        mkdir($this->backupsDir . '/07-08', 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')
            ->willReturnMap([
                ['06-07', 2007],
                ['07-08', 2008],
            ]);
        $this->stubExtractor->method('findLastArchive')->willReturn('/tmp/fake.zip');
        $this->stubExtractor->method('jsbFilename')->willReturn('IBL5.car');
        $this->stubExtractor->method('extractSingleFile')->willReturn(false);

        ob_start();
        $summary = $this->runner->run(
            $this->backupsDir,
            [JsbFileType::Car],
            false,
            false,
            '07-08',
        );
        ob_end_clean();

        self::assertSame(0, $summary->filesProcessed);
    }

    public function testRunSkipsUnparseableSeasonLabel(): void
    {
        mkdir($this->backupsDir . '/invalid-label', 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')->willReturn(0);

        ob_start();
        $summary = $this->runner->run(
            $this->backupsDir,
            [JsbFileType::Car],
            false,
            false,
            null,
        );
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('WARNING', $output);
        self::assertStringContainsString('invalid-label', $output);
        self::assertSame(0, $summary->filesProcessed);
    }
}
