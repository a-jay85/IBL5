<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeSuggestions\Feedback\DealKey;
use TradeSuggestions\Feedback\FeedbackRepository;

/**
 * Tests FeedbackRepository against real MariaDB. Uses tids 1 and 3 only.
 */
#[Group('database')]
class FeedbackRepositoryIntegrationTest extends DatabaseTestCase
{
    private FeedbackRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FeedbackRepository($this->db);
        $this->clearRows();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->clearRows();
        }
        parent::tearDown();
    }

    private function clearRows(): void
    {
        $this->db->query('DELETE FROM `ibl_trade_suggestion_feedback` WHERE tid IN (1, 3)');
    }

    private static function key(string $dealChar, string $coreChar): DealKey
    {
        return new DealKey(str_repeat($dealChar, 40), str_repeat($coreChar, 40));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(int $tid): array
    {
        $result = $this->db->query('SELECT * FROM `ibl_trade_suggestion_feedback` WHERE tid = ' . $tid . ' ORDER BY id');
        self::assertInstanceOf(\mysqli_result::class, $result);

        $rows = [];
        while (($row = $result->fetch_assoc()) !== null) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function testRecordInsertsRow(): void
    {
        $this->repo->record(1, 2, self::key('a', 'b'), '{"keys":[]}', 'TOO_OLD', 'note');

        $rows = $this->rows(1);
        self::assertCount(1, $rows);
        self::assertSame(str_repeat('a', 40), $rows[0]['deal_key']);
        self::assertSame(str_repeat('b', 40), $rows[0]['core_key']);
        self::assertSame(2, $rows[0]['partner_tid']);
        self::assertSame('{"keys":[]}', $rows[0]['deal_json']);
        self::assertSame('TOO_OLD', $rows[0]['reason_codes']);
        self::assertSame('note', $rows[0]['free_text']);
    }

    public function testRecordTwiceKeepsOneRow(): void
    {
        $this->repo->record(1, 2, self::key('a', 'b'), '{}', 'TOO_OLD', 'first');
        $this->repo->record(1, 2, self::key('a', 'b'), '{}', 'OTHER', 'second');

        $rows = $this->rows(1);
        self::assertCount(1, $rows);
        self::assertSame('OTHER', $rows[0]['reason_codes']);
        self::assertSame('second', $rows[0]['free_text']);
    }

    public function testHiddenKeysScopedByTid(): void
    {
        $this->repo->record(1, 2, self::key('a', 'b'), '{}', 'TOO_OLD', '');

        self::assertSame(['deal' => [str_repeat('a', 40)], 'core' => [str_repeat('b', 40)]], $this->repo->hiddenKeys(1));
        self::assertSame(['deal' => [], 'core' => []], $this->repo->hiddenKeys(3));
    }

    public function testHiddenKeysSplitDealAndCore(): void
    {
        $this->repo->record(1, 2, self::key('a', 'c'), '{}', 'TOO_OLD', '');
        $this->repo->record(1, 3, self::key('b', 'd'), '{}', 'OTHER', '');

        $keys = $this->repo->hiddenKeys(1);

        self::assertEqualsCanonicalizing([str_repeat('a', 40), str_repeat('b', 40)], $keys['deal']);
        self::assertEqualsCanonicalizing([str_repeat('c', 40), str_repeat('d', 40)], $keys['core']);
    }

    public function testLatestForTeamReturnsNewest(): void
    {
        $this->repo->record(1, 2, self::key('a', 'b'), '{}', 'TOO_OLD', '');
        $this->repo->record(1, 3, self::key('c', 'd'), '{}', 'OTHER', '');
        $this->db->query(
            "UPDATE `ibl_trade_suggestion_feedback` SET created_at = '2026-01-01 00:00:00' WHERE tid = 1 AND deal_key = '"
            . str_repeat('a', 40) . "'"
        );
        $this->db->query(
            "UPDATE `ibl_trade_suggestion_feedback` SET created_at = '2026-02-01 00:00:00' WHERE tid = 1 AND deal_key = '"
            . str_repeat('c', 40) . "'"
        );

        $latest = $this->repo->latestForTeam(1);

        self::assertNotNull($latest);
        self::assertSame(str_repeat('c', 40), $latest['deal_key']);
        self::assertSame('OTHER', $latest['reason_codes']);
        self::assertNull($this->repo->latestForTeam(3));
    }

    public function testFreeTextStoredRaw(): void
    {
        $this->repo->record(1, 2, self::key('a', 'b'), '{}', 'OTHER', '<script>alert("x")</script>');
        $this->repo->record(1, 3, self::key('c', 'd'), '{}', 'OTHER', '');

        $rows = $this->rows(1);
        self::assertCount(2, $rows);
        self::assertSame('<script>alert("x")</script>', $rows[0]['free_text']);
        self::assertNull($rows[1]['free_text']);

        $latest = $this->repo->latestForTeam(1);
        self::assertNotNull($latest);
        self::assertNull($latest['free_text']);
    }

    public function testFiveHundredCharNoteFits(): void
    {
        $note = str_repeat('é', 500);

        $this->repo->record(1, 2, self::key('a', 'b'), '{}', 'OTHER', $note);

        $rows = $this->rows(1);
        self::assertCount(1, $rows);
        self::assertSame($note, $rows[0]['free_text']);
    }
}
