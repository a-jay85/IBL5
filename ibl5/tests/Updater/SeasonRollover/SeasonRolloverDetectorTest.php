<?php

declare(strict_types=1);

namespace Tests\Updater\SeasonRollover;

use BulkImport\ArchiveSelection;
use BulkImport\Contracts\ArchiveExtractorInterface;
use BulkImport\Contracts\BackupArchiveLocatorInterface;
use LeagueConfig\LgeFileParser;
use PHPUnit\Framework\TestCase;
use Updater\SeasonRollover\RolloverOutcome;
use Updater\SeasonRollover\SeasonRolloverDetector;

final class SeasonRolloverDetectorTest extends TestCase
{
    private const BASE_PATH   = '/data/ibl';
    private const FILE_PREFIX = 'IBL';
    private const CURRENT_DIR = self::BASE_PATH . '/backups/25-26';
    private const NEXT_DIR    = self::BASE_PATH . '/backups/26-27';

    private const CURRENT_BEGINNING_YEAR = 2025;
    private const CURRENT_ENDING_YEAR    = 2026;

    // -----------------------------------------------------------------------
    // LGE byte-string helpers
    // -----------------------------------------------------------------------

    /**
     * Build a minimal .lge byte string that parseSeasonMetadata() can read.
     *
     * Layout at SEASON_INFO_OFFSET: year(4) + season_number(4) + field1(2) + team_count(2)
     *
     * @param string $year              4-char string, e.g. '2026'
     * @param string $seasonNumPadded   4-char string, e.g. '   1' (left-padded)
     */
    private function lgeBytes(string $year, string $seasonNumPadded): string
    {
        return str_pad('', LgeFileParser::SEASON_INFO_OFFSET)
            . $year
            . $seasonNumPadded
            . '  '    // field1 (2 bytes)
            . '16';   // team_count (2 bytes)
    }

    // -----------------------------------------------------------------------
    // Stub factories
    // -----------------------------------------------------------------------

    /**
     * Build a stub locator that returns a fixed path per directory key.
     *
     * @param array<string, string|null> $map  dir -> path (null = no archive)
     */
    private function stubLocator(array $map): BackupArchiveLocatorInterface
    {
        return new class ($map) implements BackupArchiveLocatorInterface {
            /** @param array<string, string|null> $map */
            public function __construct(private readonly array $map)
            {
            }

            public function findLatestArchive(string $seasonBackupDir): ?string
            {
                return $this->map[$seasonBackupDir] ?? null;
            }

            public function describeSelection(string $seasonBackupDir): ?ArchiveSelection
            {
                return null;
            }

            public function isProperlyNamed(string $archivePath): bool
            {
                return false;
            }

            public function generateStandardizedName(
                string $seasonBackupDir,
                string $archiveExtension,
                string $seasonLabel,
                string $phase,
                int $phaseSimNumber,
            ): string {
                return '';
            }
        };
    }

    /**
     * Build a stub extractor that returns fixed values for the two methods
     * SeasonRolloverDetector calls.
     *
     * @param string|false                                                          $lgeContents
     * @param array{season: string, seq: int, phase: string, ending_year: int}|null $parsedName
     */
    private function stubExtractor(
        string|false $lgeContents,
        ?array $parsedName,
    ): ArchiveExtractorInterface {
        return new class ($lgeContents, $parsedName) implements ArchiveExtractorInterface {
            /** @param array{season: string, seq: int, phase: string, ending_year: int}|null $parsedName */
            public function __construct(
                private readonly string|false $lgeContents,
                private readonly ?array $parsedName,
            ) {
            }

            public function extractToString(string $archivePath, string $filename): string|false
            {
                return $this->lgeContents;
            }

            /** @return array{season: string, seq: int, phase: string, ending_year: int}|null */
            public function parseArchiveName(string $filename): ?array
            {
                return $this->parsedName;
            }

            public function extractSingleFile(string $archivePath, string $filename, string $targetDir): string|false
            {
                return false;
            }

            public function cleanupTemp(string $tempPath): void
            {
            }

            public function detectFormat(string $archivePath): string
            {
                return 'zip';
            }

            public function findLastArchive(string $seasonDir): ?string
            {
                return null;
            }

            public function findHeatEndArchive(string $seasonDir): ?string
            {
                return null;
            }

            public function seasonLabelToEndingYear(string $seasonLabel): int
            {
                return 0;
            }

            /** @return list<array{path: string, season: string, seq: int, phase: string, ending_year: int}> */
            public function findAllArchives(string $seasonDir): array
            {
                return [];
            }

            public function jsbFilename(string $extension): string
            {
                return '';
            }
        };
    }

    private function buildDetector(
        BackupArchiveLocatorInterface $locator,
        ArchiveExtractorInterface $extractor,
    ): SeasonRolloverDetector {
        return new SeasonRolloverDetector($locator, $extractor, self::BASE_PATH, self::FILE_PREFIX);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testNextFolderArchiveWinsWhenBothFoldersHaveArchives(): void
    {
        $nextPath    = self::NEXT_DIR    . '/26-27_01_preseason.zip';
        $currentPath = self::CURRENT_DIR . '/25-26_15_reg-sim15.zip';

        // Advance: lge reports beginning year 2026 -> ending year 2027 = currentEndingYear + 1
        $lge = $this->lgeBytes('2026', '   1');

        // Track which archive path extractToString was called with
        $extractor = new class ($lge) implements ArchiveExtractorInterface {
            public ?string $calledWithPath = null;

            public function __construct(private readonly string $lgeContents)
            {
            }

            // @phpstan-ignore return.unusedType (interface contract requires string|false; this stub always returns string)
            public function extractToString(string $archivePath, string $filename): string|false
            {
                $this->calledWithPath = $archivePath;

                return $this->lgeContents;
            }

            /** @return array{season: string, seq: int, phase: string, ending_year: int}|null */
            // @phpstan-ignore return.unusedType (interface contract requires nullable; this stub always returns array)
            public function parseArchiveName(string $filename): ?array
            {
                return ['season' => '26-27', 'seq' => 1, 'phase' => 'preseason', 'ending_year' => 2027];
            }

            public function extractSingleFile(string $archivePath, string $filename, string $targetDir): string|false
            {
                return false;
            }

            public function cleanupTemp(string $tempPath): void
            {
            }

            public function detectFormat(string $archivePath): string
            {
                return 'zip';
            }

            public function findLastArchive(string $seasonDir): ?string
            {
                return null;
            }

            public function findHeatEndArchive(string $seasonDir): ?string
            {
                return null;
            }

            public function seasonLabelToEndingYear(string $seasonLabel): int
            {
                return 0;
            }

            /** @return list<array{path: string, season: string, seq: int, phase: string, ending_year: int}> */
            public function findAllArchives(string $seasonDir): array
            {
                return [];
            }

            public function jsbFilename(string $extension): string
            {
                return '';
            }
        };

        $locator   = $this->stubLocator([self::NEXT_DIR => $nextPath, self::CURRENT_DIR => $currentPath]);
        $detector  = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::Advance, $result->outcome);
        self::assertSame($nextPath, $extractor->calledWithPath);
    }

    public function testCurrentFolderUsedWhenNextFolderHasNoArchive(): void
    {
        $currentPath = self::CURRENT_DIR . '/25-26_10_reg-sim10.zip';
        $lge         = $this->lgeBytes('2026', '   1');

        $locator   = $this->stubLocator([self::NEXT_DIR => null, self::CURRENT_DIR => $currentPath]);
        $extractor = $this->stubExtractor($lge, ['season' => '25-26', 'seq' => 10, 'phase' => 'reg-sim10', 'ending_year' => 2026]);
        $detector  = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::Advance, $result->outcome);
    }

    public function testNoOpWhenNeitherFolderHasAnArchive(): void
    {
        // extractToString must never be called; use false to catch any unexpected call
        $locator   = $this->stubLocator([]);
        $extractor = $this->stubExtractor(false, null);
        $detector  = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::NoOp, $result->outcome);
        self::assertStringContainsString('No backup archive found', $result->reason);
    }

    public function testNoOpWhenExtractToStringReturnsFalse(): void
    {
        $archivePath = self::CURRENT_DIR . '/25-26_01_preseason.zip';

        $locator   = $this->stubLocator([self::CURRENT_DIR => $archivePath]);
        $extractor = $this->stubExtractor(false, null);
        $detector  = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::NoOp, $result->outcome);
        self::assertStringContainsString('contains no', $result->reason);
    }

    public function testAdvanceWithRegularSeasonWhenLgeSeasonNumberIsOne(): void
    {
        // season_number '   1' -> 'Regular Season' from LgeFileParser; ending_year 2027 = 2026+1 -> Advance
        $lge         = $this->lgeBytes('2026', '   1');
        $archivePath = self::CURRENT_DIR . '/25-26_01_preseason.zip';

        $locator   = $this->stubLocator([self::CURRENT_DIR => $archivePath]);
        $extractor = $this->stubExtractor($lge, ['season' => '25-26', 'seq' => 1, 'phase' => 'preseason', 'ending_year' => 2026]);
        $detector  = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::Advance, $result->outcome);
        self::assertSame(2027, $result->targetYear);
        self::assertSame('Regular Season', $result->targetPhase);
    }

    public function testAdvanceWithFreeAgencyWhenLgeUnknownAndSlugMatchesOffseasonPostfa(): void
    {
        // season_number '   7' -> 'Unknown'; slug 'offseason-postfa' -> 'Free Agency'
        $lge         = $this->lgeBytes('2026', '   7');
        $archivePath = self::CURRENT_DIR . '/25-26_03_offseason-postfa.zip';

        $locator   = $this->stubLocator([self::CURRENT_DIR => $archivePath]);
        $extractor = $this->stubExtractor(
            $lge,
            ['season' => '25-26', 'seq' => 3, 'phase' => 'offseason-postfa', 'ending_year' => 2026],
        );
        $detector = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::Advance, $result->outcome);
        self::assertSame(2027, $result->targetYear);
        self::assertSame('Free Agency', $result->targetPhase);
    }

    public function testAdvanceWithDefaultPhaseWhenLgeUnknownAndFilenameUnparseable(): void
    {
        // season_number '   7' -> 'Unknown'; parseArchiveName returns null -> DEFAULT_PHASE
        $lge         = $this->lgeBytes('2026', '   7');
        $archivePath = self::CURRENT_DIR . '/IBL2526Sim15.zip';

        $locator   = $this->stubLocator([self::CURRENT_DIR => $archivePath]);
        $extractor = $this->stubExtractor($lge, null);
        $detector  = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::Advance, $result->outcome);
        self::assertSame(2027, $result->targetYear);
        self::assertSame(SeasonRolloverDetector::DEFAULT_PHASE, $result->targetPhase);
    }

    public function testAdvanceWithDefaultPhaseWhenLgeUnknownAndSlugUnrecognized(): void
    {
        // season_number '   7' -> 'Unknown'; slug 'mystery' not in PHASE_SLUG_MAP -> DEFAULT_PHASE
        $lge         = $this->lgeBytes('2026', '   7');
        $archivePath = self::CURRENT_DIR . '/25-26_01_mystery.zip';

        $locator   = $this->stubLocator([self::CURRENT_DIR => $archivePath]);
        $extractor = $this->stubExtractor(
            $lge,
            ['season' => '25-26', 'seq' => 1, 'phase' => 'mystery', 'ending_year' => 2026],
        );
        $detector = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::Advance, $result->outcome);
        self::assertSame(SeasonRolloverDetector::DEFAULT_PHASE, $result->targetPhase);
    }

    public function testNoOpWhenLgeEndingYearEqualsSettingsYear(): void
    {
        // beginning_year '2025' -> ending_year 2026 = currentEndingYear -> NoOp
        $lge         = $this->lgeBytes('2025', '   1');
        $archivePath = self::CURRENT_DIR . '/25-26_05_reg-sim05.zip';

        $locator   = $this->stubLocator([self::CURRENT_DIR => $archivePath]);
        $extractor = $this->stubExtractor($lge, ['season' => '25-26', 'seq' => 5, 'phase' => 'reg-sim05', 'ending_year' => 2026]);
        $detector  = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::NoOp, $result->outcome);
    }

    public function testHaltWhenLgeEndingYearIsSettingsYearPlusTwo(): void
    {
        // beginning_year '2027' -> ending_year 2028 = currentEndingYear + 2 -> Halt
        $lge         = $this->lgeBytes('2027', '   1');
        $archivePath = self::CURRENT_DIR . '/25-26_01_preseason.zip';

        $locator   = $this->stubLocator([self::CURRENT_DIR => $archivePath]);
        $extractor = $this->stubExtractor($lge, ['season' => '25-26', 'seq' => 1, 'phase' => 'preseason', 'ending_year' => 2026]);
        $detector  = $this->buildDetector($locator, $extractor);

        $result = $detector->detect(self::CURRENT_BEGINNING_YEAR, self::CURRENT_ENDING_YEAR);

        self::assertSame(RolloverOutcome::Halt, $result->outcome);
    }
}
