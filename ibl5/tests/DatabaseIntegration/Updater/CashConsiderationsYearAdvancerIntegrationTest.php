<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Updater;

use LeagueControlPanel\LeagueControlPanelRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Trading\BuyoutLedgerRepository;
use Updater\SeasonRollover\CashConsiderationsYearAdvancer;

#[Group('database')]
class CashConsiderationsYearAdvancerIntegrationTest extends DatabaseTestCase
{
    private CashConsiderationsYearAdvancer $advancer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->advancer = new CashConsiderationsYearAdvancer(
            new BuyoutLedgerRepository($this->db),
            new LeagueControlPanelRepository($this->db, null),
        );
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM `ibl_cash_considerations` WHERE label LIKE 'CY-ADVANCE-TEST-%'");
        parent::tearDown();
    }

    public function testAdvancesAllCyRows(): void
    {
        $this->insertRow('ibl_cash_considerations', [
            'teamid' => 1,
            'type' => 'cash',
            'label' => 'CY-ADVANCE-TEST-A',
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
            'label' => 'CY-ADVANCE-TEST-B',
            'cy' => 3,
            'cyt' => 6,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ]);

        $this->setMarker('2009');

        $result = $this->advancer->advance(2010);

        self::assertGreaterThanOrEqual(2, $result);
        self::assertSame(2, $this->readCy('CY-ADVANCE-TEST-A'));
        self::assertSame(4, $this->readCy('CY-ADVANCE-TEST-B'));
        self::assertSame('2010', $this->readMarker());
    }

    public function testIsIdempotentForTheSameTargetYear(): void
    {
        $this->insertRow('ibl_cash_considerations', [
            'teamid' => 1,
            'type' => 'cash',
            'label' => 'CY-ADVANCE-TEST-A',
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
            'label' => 'CY-ADVANCE-TEST-B',
            'cy' => 3,
            'cyt' => 6,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ]);

        $this->setMarker('2009');

        $this->advancer->advance(2010);
        $second = $this->advancer->advance(2010);

        self::assertSame(0, $second);
        self::assertSame(2, $this->readCy('CY-ADVANCE-TEST-A'));
        self::assertSame(4, $this->readCy('CY-ADVANCE-TEST-B'));
    }

    public function testMigrationSeedsMarkerAt2026(): void
    {
        $marker = $this->readMarker();
        self::assertNotNull($marker, 'migration 188 did not seed the marker row');
        self::assertSame('2026', $marker);
    }

    public function testMigrationIsIdempotent(): void
    {
        $sql = $this->readMigrationSql();

        $this->db->query($sql);
        $this->db->query($sql);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM `ibl_settings`"
            . " WHERE setting_key = 'Cash Considerations Last Advanced Year' AND league = 'ibl'"
        );
        self::assertNotFalse($stmt, 'Failed to prepare count query: ' . $this->db->error);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertSame(1, (int) ($row['cnt'] ?? 0));
        self::assertSame('2026', $this->readMarker());
    }

    public function testMigrationDoesNotOverwriteAdvancedMarker(): void
    {
        $this->setMarker('2035');

        $sql = $this->readMigrationSql();
        $this->db->query($sql);

        self::assertSame('2035', $this->readMarker());
    }

    // ==================== Helpers ====================

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

    private function readMigrationSql(): string
    {
        $path = __DIR__ . '/../../../migrations/188_seed_cash_considerations_last_advanced_year.sql';
        $content = file_get_contents($path);
        self::assertNotFalse($content, 'Could not read migration file: ' . $path);

        // Strip comments and blank lines; collect the INSERT statement.
        $lines = explode("\n", $content);
        $sqlLines = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $sqlLines[] = $line;
        }

        return implode("\n", $sqlLines);
    }
}
