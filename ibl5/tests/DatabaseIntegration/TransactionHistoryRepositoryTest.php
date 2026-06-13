<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use TransactionHistory\TransactionHistoryRepository;

/**
 * Tests TransactionHistoryRepository against real MariaDB.
 * Tests only read seed data.
 */
#[Group('database')]
class TransactionHistoryRepositoryTest extends DatabaseTestCase
{
    private TransactionHistoryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TransactionHistoryRepository($this->db);
    }

    public function testGetAvailableYearsReturnsDistinctYears(): void
    {
        $years = $this->repo->getAvailableYears();

        self::assertNotEmpty($years);
        // Seed has entries from 2024 and 2023
        self::assertContains(2024, $years);
        self::assertContains(2023, $years);
        // Should be descending
        self::assertGreaterThanOrEqual($years[1], $years[0]);
    }

    public function testGetTransactionsReturnsAllWithNoFilters(): void
    {
        $transactions = $this->repo->getTransactions(null, null, null);

        self::assertNotEmpty($transactions);
        self::assertArrayHasKey('sid', $transactions[0]);
        self::assertArrayHasKey('catid', $transactions[0]);
        self::assertArrayHasKey('title', $transactions[0]);
        self::assertArrayHasKey('time', $transactions[0]);
    }

    public function testGetTransactionsFiltersByCategory(): void
    {
        $transactions = $this->repo->getTransactions(1, null, null);

        self::assertNotEmpty($transactions);
        foreach ($transactions as $row) {
            // catid is returned as a string by PHPStan's mysqli result typing but as
            // an int at runtime (native types) — cast so the assertion is correct both
            // statically (no staticMethod.impossibleType) and at runtime.
            self::assertSame(1, (int) $row['catid']);
        }
    }

    public function testGetTransactionsFiltersByYear(): void
    {
        $transactions = $this->repo->getTransactions(null, 2024, null);

        self::assertNotEmpty($transactions);
        foreach ($transactions as $row) {
            self::assertStringStartsWith('2024-', $row['time']);
        }
    }

    public function testGetTransactionsFiltersByYearAndMonth(): void
    {
        $transactions = $this->repo->getTransactions(null, 2024, 3);

        self::assertNotEmpty($transactions);
        foreach ($transactions as $row) {
            self::assertStringStartsWith('2024-03-', $row['time']);
        }
    }

    public function testTiesOnTimeResolveBySidDesc(): void
    {
        $timestamp = '2099-06-15 12:00:00';
        $sids = [];
        for ($i = 0; $i < 3; $i++) {
            $sids[] = $this->insertRow('nuke_stories', [
                'catid' => 1,
                'title' => "TieTest Row $i",
                'time' => $timestamp,
                'aid' => '',
            ]);
        }

        $transactions = $this->repo->getTransactions(1, 2099, 6);

        $resultSids = [];
        foreach ($transactions as $row) {
            $sid = (int) $row['sid'];
            if (in_array($sid, $sids, true)) {
                $resultSids[] = $sid;
            }
        }

        // Should be descending by sid
        self::assertSame(array_reverse($sids), $resultSids);
    }

    public function testGetTransactionsReturnsEmptyForNoMatches(): void
    {
        $transactions = $this->repo->getTransactions(null, 1900, null);

        self::assertSame([], $transactions);
    }

    /**
     * Insert a nuke_stories transaction row (auto-cleaned by the test transaction rollback).
     */
    private function insertStory(int $sid, int $catid, string $title): void
    {
        $this->insertRow('nuke_stories', [
            'sid' => $sid,
            'catid' => $catid,
            'aid' => 'admin',
            'title' => $title,
            'time' => '2024-06-01 12:00:00',
            'hometext' => 'Details...',
            'comments' => 0,
            'counter' => 0,
            'topic' => 1,
            'informant' => '',
            'ihome' => 0,
            'acomm' => 0,
            'haspoll' => 0,
            'poll_id' => 0,
            'score' => 0,
            'ratings' => 0,
        ]);
    }

    /**
     * @param array<int, array{sid: string, catid: string, title: string, time: string}> $rows
     * @return list<string>
     */
    private function titlesOf(array $rows): array
    {
        return array_map(static fn (array $row): string => $row['title'], $rows);
    }

    public function testGetTransactionsForTeamScopesByName(): void
    {
        $this->insertStory(900101, 1, 'Metros DBScope sign player');
        $this->insertStory(900102, 2, 'Stars DBScope trade pick');

        $rows = $this->repo->getTransactionsForTeam('Metros');
        $titles = $this->titlesOf($rows);

        self::assertContains('Metros DBScope sign player', $titles);
        self::assertNotContains('Stars DBScope trade pick', $titles);
        // Same row shape as getTransactions().
        self::assertArrayHasKey('sid', $rows[0]);
        self::assertArrayHasKey('catid', $rows[0]);
        self::assertArrayHasKey('title', $rows[0]);
        self::assertArrayHasKey('time', $rows[0]);
    }

    public function testGetTransactionsForTeamExcludesPartialWordMatches(): void
    {
        $this->insertStory(900111, 1, 'Metros DBBoundary win');
        $this->insertStory(900112, 1, 'Metroski DBBoundary waiver');

        $titles = $this->titlesOf($this->repo->getTransactionsForTeam('Metros'));

        self::assertContains('Metros DBBoundary win', $titles);
        // Word-boundary REGEXP must NOT match "Metroski" when scoping by "Metros".
        self::assertNotContains('Metroski DBBoundary waiver', $titles);
    }

    public function testGetTransactionsForTeamReturnsEmptyForUnknownTeam(): void
    {
        self::assertSame([], $this->repo->getTransactionsForTeam('Nonexistentteamxyz'));
    }
}
