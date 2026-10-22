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
use PlrParser\PlrOrdinalMap;
use PlrParser\PlrParseResult;

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

    private function runnerWith(
        ?PlrParserServiceInterface $plr = null,
        ?BoxscoreProcessor $boxscore = null,
    ): BulkImportRunner {
        $handler = new FileTypeHandler(
            $this->stubJsb,
            $boxscore ?? self::createStub(BoxscoreProcessor::class),
            $plr ?? self::createStub(PlrParserServiceInterface::class),
            self::createStub(LeagueConfigService::class),
        );

        return new BulkImportRunner(
            $this->stubExtractor,
            $handler,
            $this->stubResolver,
            self::createStub(\mysqli::class),
        );
    }

    /** @param list<string> $extracted */
    private function recordExtraction(array &$extracted): void
    {
        $this->stubExtractor->method('jsbFilename')
            ->willReturnCallback(static fn (string $ext): string => 'IBL5.' . $ext);
        $this->stubExtractor->method('extractSingleFile')->willReturnCallback(
            static function (string $archive, string $filename, string $targetDir) use (&$extracted): string {
                $extracted[] = $archive;
                $path = $targetDir . '/' . $filename;
                file_put_contents($path, 'x');
                return $path;
            },
        );
        $this->stubExtractor->method('cleanupTemp')->willReturnCallback(
            static function (string $path): void {
                if (file_exists($path)) {
                    unlink($path);
                }
            },
        );
    }

    /**
     * One .plr row in the fixed-width layout PlrOrdinalMap::fromPlrFile() parses.
     *
     * Format: ordinal(4) | name(32) | age(2) | pid(6) + padding
     */
    private function plrLine(int $ordinal, string $name, int $pid): string
    {
        $line = str_pad((string) $ordinal, 4, ' ', STR_PAD_LEFT);
        $line .= str_pad($name, 32);
        $line .= str_pad('25', 2); // age placeholder
        $line .= str_pad((string) $pid, 6, ' ', STR_PAD_LEFT);
        return str_pad($line, 100);
    }

    /** Three valid rows: ordinals 1, 2, 1440 map to pids 101, 202, 303. */
    private function validPlrBytes(): string
    {
        return $this->plrLine(1, 'Alpha', 101) . "\n"
            . $this->plrLine(2, 'Bravo', 202) . "\n"
            . $this->plrLine(1440, 'Charlie', 303) . "\n";
    }

    /** One season dir ('06-07' -> 2007) with a single Regular Season .plb archive and a HEAT-end archive. */
    private function seedHeatSeason(): void
    {
        mkdir($this->backupsDir . '/06-07', 0700, true);
        $this->stubExtractor->method('seasonLabelToEndingYear')->willReturn(2007);
        $this->stubExtractor->method('findAllArchives')->willReturn([
            ['path' => '/fake/06-07/0607-sim01.zip', 'season' => '06-07', 'seq' => 1, 'phase' => 'Regular Season', 'ending_year' => 2007],
        ]);
        $this->stubExtractor->method('findHeatEndArchive')->willReturn('/fake/06-07/0607-heat.zip');
    }

    /**
     * Configures jsbFilename, extractSingleFile and cleanupTemp ONCE for the whole test.
     *
     * @param \Closure(string): (string|false) $plrResult receives the real tmp $targetDir
     *        production created (ibl5_plb_<hex>) and returns the .plr path, or false
     * @param list<string> $plrTargetDirs filled with each $targetDir the IBL5.plr call received
     * @param list<string> $cleaned filled with each path passed to cleanupTemp
     */
    private function stubHeatArchiveExtraction(\Closure $plrResult, array &$plrTargetDirs, array &$cleaned): void
    {
        $this->stubExtractor->method('jsbFilename')
            ->willReturnCallback(static fn (string $ext): string => 'IBL5.' . $ext);
        $this->stubExtractor->method('extractSingleFile')->willReturnCallback(
            static function (string $archive, string $filename, string $targetDir) use ($plrResult, &$plrTargetDirs): string|false {
                if ($filename === 'IBL5.plr') {
                    $plrTargetDirs[] = $targetDir;
                    return $plrResult($targetDir);
                }
                $path = $targetDir . '/' . $filename; // IBL5.plb main extraction, same as recordExtraction()
                file_put_contents($path, 'x');
                return $path;
            },
        );
        $this->stubExtractor->method('cleanupTemp')->willReturnCallback(
            static function (string $path) use (&$cleaned): void {
                $cleaned[] = $path;
                if (file_exists($path)) {
                    unlink($path);
                }
            },
        );
    }

    /** @param list<array{0: PlrOrdinalMap, 1: int, 2: int}> $plbCalls filled with [map, seasonYear, simNumber] */
    private function capturePlbCalls(array &$plbCalls): void
    {
        $this->stubJsb->method('processPlbFile')->willReturnCallback(
            static function (string $filePath, PlrOrdinalMap $map, int $seasonYear, int $simNumber, string $sourceArchive) use (&$plbCalls): JsbImportResult {
                $plbCalls[] = [$map, $seasonYear, $simNumber];
                return new JsbImportResult();
            },
        );
    }

    /** @return list<string> sorted ibl5_plb_* and ibl5_import_* dirs currently under the system temp dir */
    private function tempDirSnapshot(): array
    {
        $plbDirs = glob(sys_get_temp_dir() . '/ibl5_plb_*');
        $importDirs = glob(sys_get_temp_dir() . '/ibl5_import_*');
        $dirs = array_merge(
            $plbDirs === false ? [] : $plbDirs,
            $importDirs === false ? [] : $importDirs,
        );
        sort($dirs);
        return $dirs;
    }

    /** @param list<string> $before result of tempDirSnapshot() taken before run() */
    private function assertNoLeakedTempDirs(array $before): void
    {
        $leaked = array_values(array_diff($this->tempDirSnapshot(), $before));
        foreach ($leaked as $dir) {
            $this->removeDir($dir); // keep a failing run from polluting later runs
        }
        self::assertSame([], $leaked);
    }

    public function testSnapshotPlrEmitsOneEntryPerArchiveWithPhaseYearAndSourceLabel(): void
    {
        mkdir($this->backupsDir . '/06-07', 0700, true);
        mkdir($this->backupsDir . '/07-08', 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')
            ->willReturnMap([
                ['06-07', 2007],
                ['07-08', 2008],
            ]);
        $this->stubExtractor->method('findAllArchives')->willReturnCallback(
            static fn (string $dir): array => match (basename($dir)) {
                '06-07' => [
                    ['path' => '/fake/06-07/0607-sim03.zip', 'season' => '06-07', 'seq' => 3, 'phase' => 'Regular Season', 'ending_year' => 2007],
                    ['path' => '/fake/06-07/0607-sim07.final.zip', 'season' => '06-07', 'seq' => 7, 'phase' => 'Playoffs', 'ending_year' => 2007],
                ],
                '07-08' => [
                    ['path' => '/fake/07-08/0708-heat.zip', 'season' => '07-08', 'seq' => 1, 'phase' => 'HEAT', 'ending_year' => 2009],
                ],
                default => [],
            },
        );

        $extracted = [];
        $this->recordExtraction($extracted);

        $plrCalls = [];
        $plrStub = self::createStub(PlrParserServiceInterface::class);
        $plrStub->method('processPlrFileForYear')->willReturnCallback(
            static function (string $filePath, int $endingYear, $mode, ?string $snapshotPhase = null, ?string $sourceArchive = null) use (&$plrCalls): PlrParseResult {
                $plrCalls[] = [$endingYear, $snapshotPhase, $sourceArchive];
                return new PlrParseResult();
            },
        );

        ob_start();
        $summary = $this->runnerWith(plr: $plrStub)->run($this->backupsDir, [JsbFileType::Plr], false, false, null);
        $output = ob_get_clean();

        self::assertIsString($output);
        // sourceLabel strips only the last extension: PATHINFO_BASENAME would record '0607-sim03.zip'.
        self::assertSame(
            [[2007, 'Regular Season', '0607-sim03'], [2007, 'Playoffs', '0607-sim07.final'], [2009, 'HEAT', '0708-heat']],
            $plrCalls,
        );
        self::assertSame(
            ['/fake/06-07/0607-sim03.zip', '/fake/06-07/0607-sim07.final.zip', '/fake/07-08/0708-heat.zip'],
            $extracted,
        );
        self::assertStringContainsString('[1/3] 0607-sim03.zip (2007 Regular Season)', $output);
        self::assertStringContainsString('[3/3] 0708-heat.zip (2009 HEAT)', $output);
        self::assertSame(3, $summary->filesProcessed);
        self::assertStringNotContainsString('ERROR:', $output);
    }

    public function testScoEmitsHeatThenFinalEntryAndSkipsNullArchives(): void
    {
        mkdir($this->backupsDir . '/06-07', 0700, true);
        mkdir($this->backupsDir . '/07-08', 0700, true);
        mkdir($this->backupsDir . '/08-09', 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')
            ->willReturnMap([
                ['06-07', 2007],
                ['07-08', 2008],
                ['08-09', 2009],
            ]);
        $this->stubExtractor->method('findHeatEndArchive')->willReturnCallback(
            static fn (string $dir): ?string => basename($dir) === '06-07' ? '/fake/06-07/0607-heat.zip' : null,
        );
        $this->stubExtractor->method('findLastArchive')->willReturnCallback(
            static fn (string $dir): ?string => match (basename($dir)) {
                '06-07' => '/fake/06-07/0607-final.zip',
                '07-08' => '/fake/07-08/0708-final.zip',
                default => null,
            },
        );

        $extracted = [];
        $this->recordExtraction($extracted);

        $scoCalls = [];
        $boxscoreStub = self::createStub(BoxscoreProcessor::class);
        $boxscoreStub->method('processScoFile')->willReturnCallback(
            static function (string $filePath, int $seasonEndingYear, string $seasonPhase) use (&$scoCalls): array {
                $scoCalls[] = [$seasonEndingYear, $seasonPhase];
                return [
                    'success' => true,
                    'gamesInserted' => 0,
                    'gamesUpdated' => 0,
                    'gamesSkipped' => 0,
                    'linesProcessed' => 0,
                    'messages' => [],
                ];
            },
        );

        ob_start();
        $summary = $this->runnerWith(boxscore: $boxscoreStub)->run($this->backupsDir, [JsbFileType::Sco], false, false, null);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertSame(
            ['/fake/06-07/0607-heat.zip', '/fake/06-07/0607-final.zip', '/fake/07-08/0708-final.zip'],
            $extracted,
        );
        self::assertSame(
            [[2007, 'HEAT'], [2007, 'Regular Season/Playoffs'], [2008, 'Regular Season/Playoffs']],
            $scoCalls,
        );
        self::assertStringContainsString('[1/3] 0607-heat.zip (2007 HEAT)', $output);
        self::assertStringContainsString('[2/3] 0607-final.zip (2007 Regular Season/Playoffs)', $output);
        self::assertSame(3, $summary->filesProcessed);
        self::assertStringNotContainsString('ERROR:', $output);
        self::assertStringNotContainsString('File not found', $output);
    }

    public function testPlbEntriesCarrySimNumberAndSourceLabelThroughProcessPlbEntries(): void
    {
        mkdir($this->backupsDir . '/06-07', 0700, true);
        mkdir($this->backupsDir . '/07-08', 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')
            ->willReturnMap([
                ['06-07', 2007],
                ['07-08', 2008],
            ]);
        $this->stubExtractor->method('findAllArchives')->willReturnCallback(
            static fn (string $dir): array => match (basename($dir)) {
                '06-07' => [
                    ['path' => '/fake/06-07/0607-sim03.zip', 'season' => '06-07', 'seq' => 3, 'phase' => 'Regular Season', 'ending_year' => 2007],
                    ['path' => '/fake/06-07/0607-sim07.zip', 'season' => '06-07', 'seq' => 7, 'phase' => 'Playoffs', 'ending_year' => 2007],
                ],
                '07-08' => [
                    ['path' => '/fake/07-08/0708-sim01.zip', 'season' => '07-08', 'seq' => 1, 'phase' => 'HEAT', 'ending_year' => 2008],
                ],
                default => [],
            },
        );
        $this->stubExtractor->method('findHeatEndArchive')->willReturn(null);

        $extracted = [];
        $this->recordExtraction($extracted);

        $plbCalls = [];
        $this->stubJsb->method('processPlbFile')->willReturnCallback(
            static function (string $filePath, $map, int $seasonYear, int $simNumber, string $sourceArchive) use (&$plbCalls): JsbImportResult {
                $plbCalls[] = [$seasonYear, $simNumber, $sourceArchive];
                return new JsbImportResult();
            },
        );

        ob_start();
        $summary = $this->runnerWith()->run($this->backupsDir, [JsbFileType::Plb], false, false, null);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertSame(
            [[2007, 3, '0607-sim03'], [2007, 7, '0607-sim07'], [2008, 1, '0708-sim01']],
            $plbCalls,
        );
        self::assertSame(
            ['/fake/06-07/0607-sim03.zip', '/fake/06-07/0607-sim07.zip', '/fake/07-08/0708-sim01.zip'],
            $extracted,
        );
        self::assertStringContainsString('  [1/3] 0607-sim03.zip', $output);
        self::assertStringContainsString('  [3/3] 0708-sim01.zip', $output);
        self::assertStringContainsString('No HEAT archive found', $output);
        self::assertSame(3, $summary->filesProcessed);
    }

    public function testPlbOrdinalMapBuiltFromHeatPlrReachesProcessPlbFileAndIsReported(): void
    {
        $before = $this->tempDirSnapshot();
        $this->seedHeatSeason();

        $plrPath = null;
        $plrTargetDirs = [];
        $cleaned = [];
        $this->stubHeatArchiveExtraction(
            function (string $targetDir) use (&$plrPath): string {
                $plrPath = $targetDir . '/IBL5.plr';
                file_put_contents($plrPath, $this->validPlrBytes());
                return $plrPath;
            },
            $plrTargetDirs,
            $cleaned,
        );
        $plbCalls = [];
        $this->capturePlbCalls($plbCalls);

        ob_start();
        $this->runnerWith()->run($this->backupsDir, [JsbFileType::Plb], false, false, null);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertCount(1, $plbCalls);
        self::assertSame(2007, $plbCalls[0][1]);
        $map = $plbCalls[0][0];
        self::assertSame(3, $map->count());
        self::assertSame(['pid' => 101, 'name' => 'Alpha'], $map->getSlotPlayer(1, 0));
        self::assertSame(['pid' => 202, 'name' => 'Bravo'], $map->getSlotPlayer(1, 1));
        self::assertSame(['pid' => 303, 'name' => 'Charlie'], $map->getSlotPlayer(48, 29));
        self::assertStringContainsString('PLR ordinal map: 3 players from 0607-heat.zip', $output);
        self::assertCount(1, $plrTargetDirs);
        self::assertDirectoryDoesNotExist($plrTargetDirs[0]);
        self::assertContains($plrPath, $cleaned);
        $this->assertNoLeakedTempDirs($before);
    }

    public function testPlbOrdinalMapWithNoUsableRowsIsReportedAsZeroPlayersNotAsFailure(): void
    {
        $before = $this->tempDirSnapshot();
        $this->seedHeatSeason();

        $plrTargetDirs = [];
        $cleaned = [];
        $this->stubHeatArchiveExtraction(
            function (string $targetDir): string {
                $plrPath = $targetDir . '/IBL5.plr';
                file_put_contents(
                    $plrPath,
                    $this->plrLine(5, 'Zero', 0) . "\n"
                    . $this->plrLine(1441, 'Over', 909) . "\n"
                    . "short\n",
                );
                return $plrPath;
            },
            $plrTargetDirs,
            $cleaned,
        );
        $plbCalls = [];
        $this->capturePlbCalls($plbCalls);

        ob_start();
        $this->runnerWith()->run($this->backupsDir, [JsbFileType::Plb], false, false, null);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertCount(1, $plbCalls);
        self::assertSame(0, $plbCalls[0][0]->count());
        self::assertStringContainsString('PLR ordinal map: 0 players from 0607-heat.zip', $output);
        self::assertStringNotContainsString('WARNING', $output);
        $this->assertNoLeakedTempDirs($before);
    }

    public function testPlbOrdinalMapFallsBackToEmptyMapWhenHeatArchiveHasNoPlr(): void
    {
        $before = $this->tempDirSnapshot();
        $this->seedHeatSeason();

        $plrTargetDirs = [];
        $cleaned = [];
        $this->stubHeatArchiveExtraction(
            static fn (string $targetDir): false => false,
            $plrTargetDirs,
            $cleaned,
        );
        $plbCalls = [];
        $this->capturePlbCalls($plbCalls);

        ob_start();
        $summary = $this->runnerWith()->run($this->backupsDir, [JsbFileType::Plb], false, false, null);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('WARNING: IBL5.plr not found in HEAT archive', $output);
        self::assertStringNotContainsString('PLR ordinal map:', $output);
        self::assertCount(1, $plbCalls);
        self::assertSame(0, $plbCalls[0][0]->count());
        self::assertSame(1, $summary->filesProcessed);
        self::assertCount(1, $plrTargetDirs);
        self::assertDirectoryDoesNotExist($plrTargetDirs[0]);
        foreach ($cleaned as $path) {
            self::assertStringEndsNotWith('IBL5.plr', $path);
        }
        $this->assertNoLeakedTempDirs($before);
    }

    public function testPlbOrdinalMapParseFailureWarnsAndStillCleansUpTmpDir(): void
    {
        $before = $this->tempDirSnapshot();
        $this->seedHeatSeason();

        $plrPath = null;
        $plrTargetDirs = [];
        $cleaned = [];
        // Returned but never written, so PlrOrdinalMap::fromPlrFile() throws "PLR file not found".
        $this->stubHeatArchiveExtraction(
            static function (string $targetDir) use (&$plrPath): string {
                $plrPath = $targetDir . '/IBL5.plr';
                return $plrPath;
            },
            $plrTargetDirs,
            $cleaned,
        );
        $plbCalls = [];
        $this->capturePlbCalls($plbCalls);

        ob_start();
        $summary = $this->runnerWith()->run($this->backupsDir, [JsbFileType::Plb], false, false, null);
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertIsString($plrPath);
        self::assertStringContainsString('WARNING: PLR parse failed: PLR file not found: ' . $plrPath, $output);
        self::assertStringNotContainsString('PLR ordinal map:', $output);
        self::assertCount(1, $plbCalls);
        self::assertSame(0, $plbCalls[0][0]->count());
        self::assertSame(0, $summary->totalErrors);
        self::assertContains($plrPath, $cleaned);
        self::assertCount(1, $plrTargetDirs);
        self::assertDirectoryDoesNotExist($plrTargetDirs[0]);
        $this->assertNoLeakedTempDirs($before);
    }

    public function testCumulativeSkipsNullArchiveAndWarnsOnUnparseableLabel(): void
    {
        mkdir($this->backupsDir . '/06-07', 0700, true);
        mkdir($this->backupsDir . '/07-08', 0700, true);
        mkdir($this->backupsDir . '/zz-bad', 0700, true);

        $this->stubExtractor->method('seasonLabelToEndingYear')
            ->willReturnMap([
                ['06-07', 2007],
                ['07-08', 2008],
                ['zz-bad', 0],
            ]);
        $this->stubExtractor->method('findLastArchive')->willReturnCallback(
            static fn (string $dir): ?string => basename($dir) === '06-07' ? '/fake/06-07/0607-final.zip' : null,
        );

        ob_start();
        $this->runner->run($this->backupsDir, [JsbFileType::Car], true, false, null);
        $output = ob_get_clean();

        self::assertIsString($output);
        $warning = "  WARNING: Cannot parse season label 'zz-bad', skipping\n";
        self::assertStringContainsString($warning, $output);
        self::assertLessThan(
            (int) strpos($output, 'Total: 1 entries for .car (career stats)'),
            (int) strpos($output, $warning),
        );
        self::assertStringContainsString(
            str_pad('06-07', 45) . str_pad('2007', 8) . "Regular Season/Playoffs\n",
            $output,
        );
        self::assertStringNotContainsString('07-08', $output);
        self::assertStringNotContainsString('0607-final', $output);
    }
}
