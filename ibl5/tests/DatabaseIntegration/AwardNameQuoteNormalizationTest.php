<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use JsbParser\Repositories\AswRepository;
use JsbParser\Repositories\AwaRepository;
use LeagueControlPanel\LeagueControlPanelRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * Every ibl_awards writer stores a double quote in a player name as an apostrophe
 * (backlog#1828). Fixtures roll back with the DatabaseTestCase transaction.
 */
#[Group('database')]
final class AwardNameQuoteNormalizationTest extends DatabaseTestCase
{
    private const YEAR = 2008;

    public function testAwaRepositoryUpsertStoresApostrophe(): void
    {
        $this->clearYear();

        (new AwaRepository($this->db))->upsertAward(self::YEAR, 'Scoring Leader (5th)', 'Shaquille O"Neal');

        self::assertSame(["Shaquille O'Neal"], $this->namesFor('Scoring Leader (5th)'));
    }

    public function testLeagueControlPanelRepositoryUpsertStoresApostrophe(): void
    {
        $this->clearYear();

        (new LeagueControlPanelRepository($this->db))
            ->upsertAward(self::YEAR, 'Most Valuable Player (2nd)', 'Jermaine O"Neal');

        self::assertSame(["Jermaine O'Neal"], $this->namesFor('Most Valuable Player (2nd)'));
    }

    public function testAswInsertStoresApostrophe(): void
    {
        $this->clearYear();

        $inserted = (new AswRepository($this->db))->insertAwardUnlessFamilyExists(
            self::YEAR,
            'Western Conference All-Star',
            'A"Ja Wilson',
            'Eastern Conference All-Star'
        );

        self::assertSame(1, $inserted);
        self::assertSame(["A'Ja Wilson"], $this->namesFor('Western Conference All-Star'));
    }

    public function testQuoteUpsertCollapsesOntoExistingApostropheRow(): void
    {
        $this->clearYear();
        $stmt = $this->db->prepare('INSERT INTO ibl_awards (year, award, name) VALUES (?, ?, ?)');
        self::assertInstanceOf(\mysqli_stmt::class, $stmt);
        $year = self::YEAR;
        $award = 'Blocks Leader (2nd)';
        $name = "Shaquille O'Neal";
        $stmt->bind_param('iss', $year, $award, $name);
        $stmt->execute();
        $stmt->close();

        (new AwaRepository($this->db))->upsertAward(self::YEAR, 'Blocks Leader (2nd)', 'Shaquille O"Neal');

        self::assertSame(["Shaquille O'Neal"], $this->namesFor('Blocks Leader (2nd)'));
    }

    public function testPlainNameStoredVerbatim(): void
    {
        $this->clearYear();

        (new AwaRepository($this->db))->upsertAward(self::YEAR, 'Assists Leader (1st)', 'Karl-Anthony Towns');

        self::assertSame(['Karl-Anthony Towns'], $this->namesFor('Assists Leader (1st)'));
    }

    private function clearYear(): void
    {
        $this->db->query('DELETE FROM ibl_awards WHERE year = ' . self::YEAR);
    }

    /**
     * @return list<string>
     */
    private function namesFor(string $award): array
    {
        $stmt = $this->db->prepare('SELECT name FROM ibl_awards WHERE year = ? AND award = ? ORDER BY table_id');
        self::assertInstanceOf(\mysqli_stmt::class, $stmt);
        $year = self::YEAR;
        $stmt->bind_param('is', $year, $award);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertInstanceOf(\mysqli_result::class, $result);
        $names = [];
        while ($row = $result->fetch_assoc()) {
            $names[] = (string) $row['name'];
        }
        $stmt->close();
        return $names;
    }
}
