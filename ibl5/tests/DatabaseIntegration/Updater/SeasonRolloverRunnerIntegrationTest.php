<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Updater;

use LeagueControlPanel\LeagueControlPanelRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Trading\BuyoutLedgerRepository;
use Updater\SeasonRollover\CashConsiderationsYearAdvancer;
use Updater\SeasonRollover\SeasonRolloverApplier;
use Updater\SeasonRollover\SeasonRolloverRunner;
use Updater\SeasonRollover\SeasonRolloverRunResult;

/**
 * Drives the real detector, applier, advancer and runner twice against real
 * ZIP archives built in a tmpDir, so the season-rollover and cash-year paths
 * are exercised end to end.
 */
#[Group('database')]
class SeasonRolloverRunnerIntegrationTest extends DatabaseTestCase
{
    use RolloverArchiveFixtures;

    private const MARKER_KEY = 'Cash Considerations Last Advanced Year';

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseTestCase::tearDown() rolls the transaction back, restoring these rows.
        $this->db->query(
            "UPDATE `ibl_settings` SET value = '2026'"
            . " WHERE setting_key = 'Current Season Ending Year' AND league = 'ibl'"
        );
        $this->db->query(
            "UPDATE `ibl_settings` SET value = 'Regular Season'"
            . " WHERE setting_key = 'Current Season Phase' AND league = 'ibl'"
        );

        $this->createRolloverTmpDir();
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM `ibl_cash_considerations` WHERE label LIKE 'CY-ROLLOVER-TEST-%'");
        $this->removeRolloverTmpDir();
        parent::tearDown();
    }

    // ==================== Tests ====================

    public function testFirstRunAdvancesSeasonAndCashYears(): void
    {
        $this->seedLedgerRows();
        $this->setMarker('2026');
        $this->stageNextSeasonArchive();

        $result = $this->runOnce();

        self::assertTrue($result->rolledOver());
        self::assertGreaterThanOrEqual(2, $result->cashRowsAdvanced);
        self::assertSame('2027', $this->readSetting('Current Season Ending Year'));
        self::assertSame('2027', $this->readMarker());
        self::assertSame(2, $this->readCy('CY-ROLLOVER-TEST-A'));
        self::assertSame(4, $this->readCy('CY-ROLLOVER-TEST-B'));
    }

    public function testSecondRunWithSameArchiveIsNoOp(): void
    {
        $this->seedLedgerRows();
        $this->setMarker('2026');
        $this->stageNextSeasonArchive();

        $this->runOnce();
        $second = $this->runOnce();

        self::assertFalse($second->rolledOver());
        self::assertNull($second->cashRowsAdvanced);
        self::assertFalse($second->decision->shouldWrite());
        self::assertSame('2027', $this->readSetting('Current Season Ending Year'));
        self::assertSame('2027', $this->readMarker());
        self::assertSame(2, $this->readCy('CY-ROLLOVER-TEST-A'));
        self::assertSame(4, $this->readCy('CY-ROLLOVER-TEST-B'));
    }

    public function testRerunAfterSeasonResetDoesNotDoubleAdvanceCash(): void
    {
        $this->seedLedgerRows();
        $this->setMarker('2026');
        $this->stageNextSeasonArchive();

        $this->runOnce();

        // Reset only the season year; the cash marker stays at 2027.
        $this->db->query(
            "UPDATE `ibl_settings` SET value = '2026'"
            . " WHERE setting_key = 'Current Season Ending Year' AND league = 'ibl'"
        );

        $second = $this->runOnce();

        self::assertTrue($second->rolledOver());
        self::assertSame(0, $second->cashRowsAdvanced);
        self::assertSame(2, $this->readCy('CY-ROLLOVER-TEST-A'));
        self::assertSame(4, $this->readCy('CY-ROLLOVER-TEST-B'));
        self::assertSame('2027', $this->readMarker());
        self::assertSame('2027', $this->readSetting('Current Season Ending Year'));
    }

    public function testRunWithoutNextSeasonArchiveChangesNothing(): void
    {
        $this->seedLedgerRows();
        $this->setMarker('2026');
        $this->makeBackupDirs(['25-26']);
        $this->createArchive('25-26', '25-26_04_reg-sim', 2025);

        $result = $this->runOnce();

        self::assertFalse($result->rolledOver());
        self::assertNull($result->cashRowsAdvanced);
        self::assertSame('2026', $this->readSetting('Current Season Ending Year'));
        self::assertSame('2026', $this->readMarker());
        self::assertSame(1, $this->readCy('CY-ROLLOVER-TEST-A'));
        self::assertSame(3, $this->readCy('CY-ROLLOVER-TEST-B'));
    }

    public function testRunLeavesOlympicsSettingsUntouched(): void
    {
        $this->seedLedgerRows();
        $this->setMarker('2026');
        // Guarantee both Olympics rows exist so the comparison is never null-vs-null.
        foreach (['Current Season Ending Year', self::MARKER_KEY] as $key) {
            $stmt = $this->db->prepare(
                "INSERT INTO `ibl_settings` (setting_key, value, league) VALUES (?, '2003', 'olympics')"
                . " ON DUPLICATE KEY UPDATE value = '2003'"
            );
            self::assertNotFalse($stmt, 'Failed to prepare Olympics seed: ' . $this->db->error);
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $stmt->close();
        }

        $seasonBefore = $this->readLeagueSetting('Current Season Ending Year', 'olympics');
        $markerBefore = $this->readLeagueSetting(self::MARKER_KEY, 'olympics');
        self::assertSame('2003', $seasonBefore);
        self::assertSame('2003', $markerBefore);

        $this->stageNextSeasonArchive();
        $this->runOnce();

        self::assertSame($seasonBefore, $this->readLeagueSetting('Current Season Ending Year', 'olympics'));
        self::assertSame($markerBefore, $this->readLeagueSetting(self::MARKER_KEY, 'olympics'));
        self::assertSame('2027', $this->readSetting('Current Season Ending Year'));
    }

    public function testMissingMarkerLeavesSeasonYearUnchanged(): void
    {
        $this->seedLedgerRows();
        $this->stageNextSeasonArchive();
        // Transaction rollback restores the deleted marker row.
        $this->db->query(
            "DELETE FROM `ibl_settings`"
            . " WHERE setting_key = 'Cash Considerations Last Advanced Year' AND league = 'ibl'"
        );

        try {
            $this->runOnce();
            self::fail('expected RuntimeException');
        } catch (\RuntimeException) {
            self::assertSame('2026', $this->readSetting('Current Season Ending Year'));
            self::assertSame(1, $this->readCy('CY-ROLLOVER-TEST-A'));
            self::assertSame(3, $this->readCy('CY-ROLLOVER-TEST-B'));
        }
    }

    public function testRetryAfterBadMarkerAdvancesCashExactlyOnce(): void
    {
        $this->seedLedgerRows();
        $this->stageNextSeasonArchive();
        $this->setMarker('not-a-year');

        try {
            $this->runOnce();
            self::fail('expected RuntimeException');
        } catch (\RuntimeException) {
            $this->setMarker('2026');
        }

        $retry = $this->runOnce();

        self::assertTrue($retry->rolledOver());
        self::assertGreaterThanOrEqual(2, $retry->cashRowsAdvanced);
        self::assertSame('2027', $this->readSetting('Current Season Ending Year'));
        self::assertSame('2027', $this->readMarker());
        self::assertSame(2, $this->readCy('CY-ROLLOVER-TEST-A'));
        self::assertSame(4, $this->readCy('CY-ROLLOVER-TEST-B'));
    }

    // ==================== Helpers ====================

    private function seedLedgerRows(): void
    {
        $this->insertRow('ibl_cash_considerations', [
            'teamid' => 1,
            'type' => 'cash',
            'label' => 'CY-ROLLOVER-TEST-A',
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ]);
        $this->insertRow('ibl_cash_considerations', [
            'teamid' => 1,
            'type' => 'cash',
            'label' => 'CY-ROLLOVER-TEST-B',
            'cy' => 3,
            'cyt' => 6,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ]);
    }

    private function buildRunner(): SeasonRolloverRunner
    {
        return new SeasonRolloverRunner(
            $this->buildDetector(),
            new SeasonRolloverApplier(new LeagueControlPanelRepository($this->db, null)),
            new CashConsiderationsYearAdvancer(
                new BuyoutLedgerRepository($this->db),
                new LeagueControlPanelRepository($this->db, null),
            ),
        );
    }

    /**
     * Mirrors the script, which rebuilds Season from the DB on each run.
     */
    private function runOnce(): SeasonRolloverRunResult
    {
        $ending = (int) $this->readSetting('Current Season Ending Year');

        return $this->buildRunner()->run($ending - 1, $ending);
    }

    private function stageNextSeasonArchive(): void
    {
        $this->makeBackupDirs(['25-26', '26-27']);
        $this->createArchive('25-26', '25-26_04_reg-sim', 2025);
        $this->createArchive('26-27', '26-27_01_preseason', 2026);
    }

    private function readLeagueSetting(string $key, string $league): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT value FROM `ibl_settings` WHERE setting_key = ? AND league = ?"
        );
        self::assertNotFalse($stmt, 'Failed to prepare readLeagueSetting: ' . $this->db->error);
        $stmt->bind_param('ss', $key, $league);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return is_array($row) ? (string) $row['value'] : null;
    }

    private function setMarker(string $year): void
    {
        $stmt = $this->db->prepare(
            "UPDATE `ibl_settings` SET value = ?"
            . " WHERE setting_key = 'Cash Considerations Last Advanced Year' AND league = 'ibl'"
        );
        self::assertNotFalse($stmt, 'Failed to prepare setMarker: ' . $this->db->error);
        $stmt->bind_param('s', $year);
        $stmt->execute();
        $stmt->close();
    }

    private function readMarker(): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT value FROM `ibl_settings`"
            . " WHERE setting_key = 'Cash Considerations Last Advanced Year' AND league = 'ibl'"
        );
        self::assertNotFalse($stmt, 'Failed to prepare readMarker: ' . $this->db->error);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return is_array($row) ? (string) $row['value'] : null;
    }

    private function readCy(string $label): int
    {
        $stmt = $this->db->prepare(
            "SELECT cy FROM `ibl_cash_considerations` WHERE label = ?"
        );
        self::assertNotFalse($stmt, 'Failed to prepare readCy: ' . $this->db->error);
        $stmt->bind_param('s', $label);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['cy'] ?? 0);
    }
}
