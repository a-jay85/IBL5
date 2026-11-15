<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the shipped awards double-quote-names migration against seeded rows:
 * quote rows are renamed to apostrophes, a quote row whose apostrophe twin
 * already exists is deleted, plain names are untouched, and reruns change nothing.
 */
#[Group('database')]
final class AwardsDoubleQuoteNamesMigrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset 2008 so seeded rows are the only ones (rolled back in tearDown).
        $this->db->query('DELETE FROM ibl_awards WHERE year = 2008');
    }

    public function testRenamesQuoteRows(): void
    {
        $tableId = $this->seedAward('Scoring Leader (5th)', 'Shaquille O"Neal');

        $this->runMigration();

        $rows = $this->rowsForYear();
        self::assertCount(1, $rows);
        self::assertSame($tableId, $rows[0]['table_id']);
        self::assertSame("Shaquille O'Neal", $rows[0]['name']);
    }

    public function testDeletesQuoteRowWhenApostropheTwinExists(): void
    {
        $this->seedAward('Blocks Leader (2nd)', 'Jermaine O"Neal');
        $this->seedAward('Blocks Leader (2nd)', "Jermaine O'Neal");

        $this->runMigration();

        $rows = $this->rowsForYear();
        self::assertCount(1, $rows);
        self::assertSame('Blocks Leader (2nd)', $rows[0]['award']);
        self::assertSame("Jermaine O'Neal", $rows[0]['name']);
    }

    public function testRenamesQuoteRowWhenTwinIsUnderAnotherAward(): void
    {
        $this->seedAward('All-League First Team', 'A"Ja Wilson');
        $this->seedAward('Western Conference All-Star', "A'Ja Wilson");

        $this->runMigration();

        $rows = $this->rowsForYear();
        self::assertCount(2, $rows);
        self::assertSame("A'Ja Wilson", $rows[0]['name']);
        self::assertSame("A'Ja Wilson", $rows[1]['name']);
    }

    public function testLeavesPlainNamesAlone(): void
    {
        $this->seedAward('Assists Leader (1st)', 'Karl-Anthony Towns');

        $this->runMigration();

        $rows = $this->rowsForYear();
        self::assertCount(1, $rows);
        self::assertSame('Karl-Anthony Towns', $rows[0]['name']);
    }

    public function testSecondRunChangesNothing(): void
    {
        $this->seedAward('Scoring Leader (5th)', 'Shaquille O"Neal');
        $this->seedAward('Blocks Leader (2nd)', 'Jermaine O"Neal');
        $this->seedAward('Blocks Leader (2nd)', "Jermaine O'Neal");
        $this->seedAward('Assists Leader (1st)', 'Karl-Anthony Towns');

        $this->runMigration();
        $afterFirst = $this->snapshot();

        $this->runMigration();

        self::assertSame($afterFirst, $this->snapshot());
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function seedAward(string $award, string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO ibl_awards (year, award, name) VALUES (2008, ?, ?)');
        self::assertInstanceOf(\mysqli_stmt::class, $stmt);
        $stmt->bind_param('ss', $award, $name);
        self::assertTrue($stmt->execute(), $stmt->error);
        $id = $stmt->insert_id;
        $stmt->close();

        return (int) $id;
    }

    private function runMigration(): void
    {
        $matches = glob(dirname(__DIR__, 2) . '/migrations/*_awards_double_quote_names.sql');
        self::assertIsArray($matches);
        self::assertCount(1, $matches, 'expected exactly one awards_double_quote_names migration');
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
     * @return list<array{table_id: int, award: string, name: string}>
     */
    private function rowsForYear(): array
    {
        $result = $this->db->query('SELECT table_id, award, name FROM ibl_awards WHERE year = 2008 ORDER BY table_id');
        self::assertInstanceOf(\mysqli_result::class, $result);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'table_id' => (int) $row['table_id'],
                'award' => (string) $row['award'],
                'name' => (string) $row['name'],
            ];
        }
        $result->free();

        return $rows;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function snapshot(): array
    {
        $result = $this->db->query('SELECT table_id, year, award, name FROM ibl_awards ORDER BY table_id');
        self::assertInstanceOf(\mysqli_result::class, $result);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();

        return $rows;
    }
}
