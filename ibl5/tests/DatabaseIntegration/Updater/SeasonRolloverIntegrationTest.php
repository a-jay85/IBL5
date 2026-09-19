<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Updater;

use BulkImport\ArchiveExtractor;
use BulkImport\BackupArchiveLocator;
use LeagueConfig\LgeFileParser;
use LeagueControlPanel\LeagueControlPanelRepository;
use PHPUnit\Framework\Attributes\Group;
use Season\SeasonQueryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\SeasonRollover\RolloverOutcome;
use Updater\SeasonRollover\SeasonRolloverApplier;
use Updater\SeasonRollover\SeasonRolloverDetector;

#[Group('database')]
class SeasonRolloverIntegrationTest extends DatabaseTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed ibl_settings to a known baseline. DatabaseTestCase::tearDown() calls
        // $this->db->rollback(), which automatically restores these rows — no manual
        // tearDown() restore needed for the DB side.
        $this->db->query(
            "UPDATE `ibl_settings` SET value = '2026'"
            . " WHERE setting_key = 'Current Season Ending Year' AND league = 'ibl'"
        );
        $this->db->query(
            "UPDATE `ibl_settings` SET value = 'Regular Season'"
            . " WHERE setting_key = 'Current Season Phase' AND league = 'ibl'"
        );
        $this->db->query(
            "UPDATE `ibl_settings` SET value = 'Off'"
            . " WHERE setting_key = 'Show Draft Link' AND league = 'ibl'"
        );

        $this->tmpDir = sys_get_temp_dir() . '/ibl_rollover_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
            $this->removeTree($this->tmpDir);
        }
        parent::tearDown();
    }

    // ==================== Write-path tests ====================

    /**
     * The detector prefers the next-season folder when it contains an archive;
     * a freshly-uploaded new-season file lands there while the current-season
     * folder keeps the last regular-season sim. After applying the decision,
     * both settings rows and the application-layer read path must reflect 2027.
     */
    public function testDetectorPrefersNextSeasonFolderAndAdvancesSettings(): void
    {
        $this->makeBackupDirs(['25-26', '26-27']);
        // Current-season archive: beginYear=2025 → endingYear=2026 (matches settings)
        $this->createArchive('25-26', '25-26_04_reg-sim', 2025);
        // Next-season archive: beginYear=2026 → endingYear=2027.
        // season_number=0 in the lge → LgeFileParser returns phase='Unknown';
        // the detector falls back to the archive filename slug 'preseason',
        // which BackupArchiveLocator::phaseFromSlug maps to 'Preseason'.
        $this->createArchive('26-27', '26-27_01_preseason', 2026);

        $result = $this->buildDetector()->detect(2025, 2026);

        self::assertSame(RolloverOutcome::Advance, $result->outcome);
        self::assertSame(2027, $result->targetYear);
        self::assertSame('Preseason', $result->targetPhase);

        $applier = new SeasonRolloverApplier(new LeagueControlPanelRepository($this->db));
        $applier->apply($result);

        // Verify via direct SQL — never through the repository that wrote the rows,
        // so a repository that silently no-ops cannot pass itself.
        self::assertSame('2027', $this->readSetting('Current Season Ending Year'));
        self::assertSame('Preseason', $this->readSetting('Current Season Phase'));

        // Close the loop: verify the application-layer read path round-trips correctly.
        //
        // NOTE: Season\Season is aliased to the test mock (Tests\WideUnit\Mocks\Season)
        // in every PHPUnit run by TestAliasesBootstrap in tests/bootstrap.php; the mock
        // ignores the DB and hardcodes endingYear=2024. We therefore use
        // SeasonQueryRepository — the real production query path that Season\Season uses
        // internally — to prove the DB row is 2027 as the application would read it.
        $settings = (new SeasonQueryRepository($this->db))
            ->getBulkSettings(['Current Season Ending Year']);
        self::assertSame('2027', $settings['Current Season Ending Year']);
    }

    /**
     * When the applier advances into Preseason, setSeasonPhase() must also flip
     * 'Show Draft Link' to 'Off'. Seeding it to 'On' makes the side-effect
     * observable — this test would silently pass if someone replaced
     * setSeasonPhase() with a plain updateSetting() call.
     */
    public function testPreseasonAdvanceForcesShowDraftLinkOff(): void
    {
        $this->db->query(
            "UPDATE `ibl_settings` SET value = 'On'"
            . " WHERE setting_key = 'Show Draft Link' AND league = 'ibl'"
        );

        $this->makeBackupDirs(['25-26', '26-27']);
        $this->createArchive('25-26', '25-26_04_reg-sim', 2025);
        $this->createArchive('26-27', '26-27_01_preseason', 2026);

        $result = $this->buildDetector()->detect(2025, 2026);
        self::assertSame(RolloverOutcome::Advance, $result->outcome);

        $applier = new SeasonRolloverApplier(new LeagueControlPanelRepository($this->db));
        $applier->apply($result);

        self::assertSame('Preseason', $this->readSetting('Current Season Phase'));
        self::assertSame('Off', $this->readSetting('Show Draft Link'));
    }

    // ==================== Negative / boundary paths ====================

    /**
     * Archive declares endingYear=2028 while settings hold 2026 — a gap of two.
     * Decision must be Halt, and both ibl_settings rows must be unchanged.
     */
    public function testGapOfTwoSeasonsLeavesSettingsUntouched(): void
    {
        $this->makeBackupDirs(['25-26', '26-27']);
        $this->createArchive('25-26', '25-26_04_reg-sim', 2025);
        // beginYear=2027 → endingYear=2028; gap of 2 from settings year 2026
        $this->createArchive('26-27', '26-27_01_preseason', 2027);

        $beforeYear  = $this->readSetting('Current Season Ending Year');
        $beforePhase = $this->readSetting('Current Season Phase');

        $result = $this->buildDetector()->detect(2025, 2026);

        self::assertSame(RolloverOutcome::Halt, $result->outcome);
        self::assertSame($beforeYear,  $this->readSetting('Current Season Ending Year'));
        self::assertSame($beforePhase, $this->readSetting('Current Season Phase'));
    }

    /**
     * Archive declares endingYear=2025 while settings hold 2026 — a backwards advance.
     * Decision must be Halt, and both ibl_settings rows must be unchanged.
     */
    public function testBackwardsArchiveLeavesSettingsUntouched(): void
    {
        $this->makeBackupDirs(['25-26', '26-27']);
        // beginYear=2024 → endingYear=2025; backwards from settings year 2026
        $this->createArchive('25-26', '25-26_04_reg-sim', 2024);
        $this->createArchive('26-27', '26-27_01_preseason', 2024);

        $beforeYear  = $this->readSetting('Current Season Ending Year');
        $beforePhase = $this->readSetting('Current Season Phase');

        $result = $this->buildDetector()->detect(2025, 2026);

        self::assertSame(RolloverOutcome::Halt, $result->outcome);
        self::assertSame($beforeYear,  $this->readSetting('Current Season Ending Year'));
        self::assertSame($beforePhase, $this->readSetting('Current Season Phase'));
    }

    /**
     * No backups/ directory exists at all. The detector must return NoOp,
     * throw no exception, and leave settings unchanged.
     */
    public function testEmptyBackupTreeIsNoOp(): void
    {
        // $this->tmpDir exists but has no backups/ subdirectory
        $beforeYear  = $this->readSetting('Current Season Ending Year');
        $beforePhase = $this->readSetting('Current Season Phase');

        $result = $this->buildDetector()->detect(2025, 2026);

        self::assertSame(RolloverOutcome::NoOp, $result->outcome);
        self::assertSame($beforeYear,  $this->readSetting('Current Season Ending Year'));
        self::assertSame($beforePhase, $this->readSetting('Current Season Phase'));
    }

    /**
     * Archive declares endingYear=2026, matching the current settings year.
     * Decision must be NoOp and settings must be unchanged.
     */
    public function testMatchingSeasonIsNoOp(): void
    {
        $this->makeBackupDirs(['25-26', '26-27']);
        // beginYear=2025 → endingYear=2026 = settings year → NoOp
        $this->createArchive('25-26', '25-26_04_reg-sim', 2025);
        $this->createArchive('26-27', '26-27_01_preseason', 2025);

        $beforeYear  = $this->readSetting('Current Season Ending Year');
        $beforePhase = $this->readSetting('Current Season Phase');

        $result = $this->buildDetector()->detect(2025, 2026);

        self::assertSame(RolloverOutcome::NoOp, $result->outcome);
        self::assertSame($beforeYear,  $this->readSetting('Current Season Ending Year'));
        self::assertSame($beforePhase, $this->readSetting('Current Season Phase'));
    }

    // ==================== Helpers ====================

    /**
     * Build an IBL5.lge buffer with the season-info block at SEASON_INFO_OFFSET.
     *
     * Layout (from LgeFileParser::parseSeasonMetadata):
     *   offset+0  : year (4 chars)         → beginYear
     *   offset+4  : season_number (4 chars) → '0' maps to phase='Unknown' (non-'1', non-'2')
     *   offset+8  : field1 (2 chars)        → unused padding
     *   offset+10 : team_count (2 chars)    → teamCount
     *
     * season_ending_year = beginYear + 1 (computed by parser, not stored).
     * Using season_number=0 triggers the 'Unknown' branch in the detector,
     * which falls back to the archive filename's phase slug.
     */
    private function buildLgeBuffer(int $beginYear, int $seasonNumber = 0, int $teamCount = 8): string
    {
        $offset = LgeFileParser::SEASON_INFO_OFFSET;
        return str_repeat(' ', $offset)
            . str_pad((string) $beginYear, 4)
            . str_pad((string) $seasonNumber, 4)
            . str_pad('', 2)
            . str_pad((string) $teamCount, 2);
    }

    /**
     * Create season-backup subdirectories under $this->tmpDir/backups/.
     *
     * @param list<string> $labels e.g. ['25-26', '26-27']
     */
    private function makeBackupDirs(array $labels): void
    {
        foreach ($labels as $label) {
            mkdir($this->tmpDir . '/backups/' . $label, 0777, true);
        }
    }

    /**
     * Write a ZIP archive at $this->tmpDir/backups/$seasonLabel/$name.zip
     * containing a single entry 'IBL5.lge' built from $beginYear.
     * season_ending_year = beginYear + 1 (as LgeFileParser computes it).
     */
    private function createArchive(string $seasonLabel, string $name, int $beginYear): void
    {
        $zipPath    = $this->tmpDir . '/backups/' . $seasonLabel . '/' . $name . '.zip';
        $lgeContent = $this->buildLgeBuffer($beginYear);

        $zip    = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($opened !== true) {
            self::fail(sprintf('Failed to create ZIP at %s: ZipArchive error %d', $zipPath, $opened));
        }
        $zip->addFromString('IBL5.lge', $lgeContent);
        $zip->close();
    }

    /**
     * Build a SeasonRolloverDetector wired with real production classes.
     */
    private function buildDetector(): SeasonRolloverDetector
    {
        $extractor = new ArchiveExtractor();
        $locator   = new BackupArchiveLocator($extractor);
        return new SeasonRolloverDetector($locator, $extractor, $this->tmpDir, 'IBL5');
    }

    /**
     * Read a setting value directly from ibl_settings (league='ibl').
     * Uses a raw prepared statement so a repository that silently no-ops
     * cannot make this assertion pass when the write did not happen.
     */
    private function readSetting(string $key): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT value FROM `ibl_settings` WHERE setting_key = ? AND league = 'ibl'"
        );
        self::assertNotFalse($stmt, 'Failed to prepare readSetting: ' . $this->db->error);
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        return is_array($row) ? (string) $row['value'] : null;
    }

    /**
     * Recursively remove a directory tree.
     */
    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        /** @var list<string>|false $items */
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
