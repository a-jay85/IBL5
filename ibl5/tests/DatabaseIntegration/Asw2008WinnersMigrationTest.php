<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the shipped 2008 All-Star winners/MVPs migration against seeded rows:
 * winner rows replace entrant rows, the game MVP keeps the conference row,
 * reruns change nothing, and a DB without 2008 rosters gets no rows.
 */
#[Group('database')]
final class Asw2008WinnersMigrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->query(
            "INSERT IGNORE INTO ibl_jsb_allstar_rosters (season_year, event_type, roster_slot, pid, player_name)"
            . " VALUES (2008, 'allstar_1', 1, NULL, NULL)"
        );
        // CI already applied the migration, so reset 2008 (rolled back in tearDown).
        $this->db->query('DELETE FROM ibl_awards WHERE year = 2008');
        $this->db->query(
            "INSERT INTO ibl_awards (year, award, name) VALUES"
            . " (2008, 'Western Conference All-Star', 'Kobe Bryant'),"
            . " (2008, 'Rookie-Sophomore Challenge', 'Modestas Paulauskas'),"
            . " (2008, 'Three-Point Contest', 'Karl-Anthony Towns'),"
            . " (2008, 'Three-Point Contest', 'Stephen Curry'),"
            . " (2008, 'Slam Dunk Competition', 'Pete Maravich')"
        );
    }

    public function testWinnerRowsReplaceEntrantRows(): void
    {
        $this->runMigration();

        self::assertSame(['Rookie-Sophomore Challenge - MVP'], $this->awardsFor('Modestas Paulauskas'));
        self::assertSame(['Three-Point Contest - Winner'], $this->awardsFor('Karl-Anthony Towns'));
        self::assertSame(['Slam Dunk Competition - Winner'], $this->awardsFor('Pete Maravich'));
        self::assertSame(['Three-Point Contest'], $this->awardsFor('Stephen Curry'));
    }

    public function testGameMvpKeepsConferenceRow(): void
    {
        $this->runMigration();

        self::assertSame(['All-Star Game MVP', 'Western Conference All-Star'], $this->awardsFor('Kobe Bryant'));
    }

    public function testSecondRunChangesNothing(): void
    {
        $this->runMigration();
        // 5 seeded rows, 3 entrant rows replaced, 4 winner/MVP rows added.
        self::assertSame(6, $this->countAwards());

        $this->runMigration();

        self::assertSame(6, $this->countAwards());
    }

    public function testSkipsWhen2008RostersAreMissing(): void
    {
        $this->db->query('DELETE FROM ibl_jsb_allstar_rosters WHERE season_year = 2008');

        $this->runMigration();

        self::assertSame(5, $this->countAwards());
        self::assertSame(['Three-Point Contest'], $this->awardsFor('Karl-Anthony Towns'));
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function runMigration(): void
    {
        $matches = glob(dirname(__DIR__, 2) . '/migrations/*_asw_2008_allstar_winners_mvps.sql');
        self::assertIsArray($matches);
        self::assertCount(1, $matches, 'expected exactly one asw_2008_allstar_winners_mvps migration');
        $sql = file_get_contents($matches[0]); // phpunit-hygiene-allow: migration SQL has no executable seam other than its text
        self::assertIsString($sql);
        $sql = (string) preg_replace('/--[^\n]*/', '', $sql);

        $ran = 0;
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            self::assertTrue($this->db->query($statement) !== false, $this->db->error);
            $ran++;
        }
        self::assertSame(2, $ran);
    }

    /**
     * @return list<string>
     */
    private function awardsFor(string $name): array
    {
        $result = $this->db->query(
            "SELECT award FROM ibl_awards WHERE year = 2008 AND name = '"
            . $this->db->real_escape_string($name) . "' ORDER BY award"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $awards = [];
        while ($row = $result->fetch_assoc()) {
            $awards[] = (string) $row['award'];
        }
        $result->free();
        return $awards;
    }

    private function countAwards(): int
    {
        $result = $this->db->query('SELECT COUNT(*) AS c FROM ibl_awards WHERE year = 2008');
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row);
        return (int) $row['c'];
    }
}
