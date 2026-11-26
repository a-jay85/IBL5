<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use TradeSuggestions\Feedback\DealKey;
use TradeSuggestions\Feedback\FeedbackRepository;
use TradeSuggestions\Tuning\TuningFeedbackRepository;

/**
 * Reads rows the real unit 5 writer stored. Uses tids 5 and 6 only: both
 * `tid` and `partner_tid` carry a foreign key to `ibl_team_info`, so an
 * out-of-range tid cannot be inserted. The parent case rolls the transaction
 * back, and setUp/tearDown also delete these tids' rows.
 */
#[Group('database')]
class TuningFeedbackRepositoryIntegrationTest extends DatabaseTestCase
{
    private const int TID_A = 5;
    private const int TID_B = 6;

    private FeedbackRepository $writer;
    private TuningFeedbackRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new FeedbackRepository($this->db);
        $this->repo = new TuningFeedbackRepository($this->db);
        $this->clearRows();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->clearRows();
        }
        parent::tearDown();
    }

    public function testRowsForSelectedTeamsOnly(): void
    {
        $this->writer->record(self::TID_A, self::TID_B, self::key('a', 'b'), '{}', 'TOO_OLD', '');
        $this->writer->record(self::TID_B, self::TID_A, self::key('c', 'd'), '{}', 'OTHER', '');

        $rows = $this->repo->forTeams([self::TID_A]);

        self::assertCount(1, $rows);
        self::assertSame(self::TID_A, $rows[0]->tid);
        self::assertSame(self::TID_B, $rows[0]->partnerTid);
        self::assertSame(str_repeat('a', 40), $rows[0]->dealKey);
        self::assertSame(['TOO_OLD'], $rows[0]->reasonCodes);
    }

    public function testEmptyTableGivesEmptyList(): void
    {
        self::assertSame([], $this->repo->forTeams([self::TID_A, self::TID_B]));
    }

    public function testFreeTextRoundTripsRawIncludingScript(): void
    {
        $note = '<script>alert("x")</script> & more';
        $this->writer->record(self::TID_A, self::TID_B, self::key('a', 'b'), '{}', 'OTHER', $note);

        self::assertSame($note, $this->repo->forTeams([self::TID_A])[0]->freeText);
    }

    public function testNullFreeTextReadsAsNull(): void
    {
        $this->writer->record(self::TID_A, self::TID_B, self::key('a', 'b'), '{}', 'OTHER', '');

        self::assertNull($this->repo->forTeams([self::TID_A])[0]->freeText);
    }

    public function testFiveHundredCharNoteRoundTrips(): void
    {
        $note = str_repeat('abcdefghi<', 50);
        self::assertSame(500, strlen($note));
        $this->writer->record(self::TID_A, self::TID_B, self::key('a', 'b'), '{}', 'OTHER', $note);

        self::assertSame($note, $this->repo->forTeams([self::TID_A])[0]->freeText);
    }

    public function testOrderIsNewestFirstWithinATeam(): void
    {
        $this->writer->record(self::TID_A, self::TID_B, self::key('a', 'b'), '{}', 'OTHER', '');
        $this->writer->record(self::TID_A, self::TID_B, self::key('c', 'd'), '{}', 'OTHER', '');
        // The first insert gets the later timestamp, so id order and time order disagree.
        $this->db->query("UPDATE `ibl_trade_suggestion_feedback` SET created_at = '2026-01-02 00:00:00' WHERE tid = " . self::TID_A . " AND deal_key = '" . str_repeat('a', 40) . "'");
        $this->db->query("UPDATE `ibl_trade_suggestion_feedback` SET created_at = '2026-01-01 00:00:00' WHERE tid = " . self::TID_A . " AND deal_key = '" . str_repeat('c', 40) . "'");

        $rows = $this->repo->forTeams([self::TID_A]);

        self::assertSame([str_repeat('a', 40), str_repeat('c', 40)], array_map(static fn ($r): string => $r->dealKey, $rows));
    }

    private function clearRows(): void
    {
        $this->db->query('DELETE FROM `ibl_trade_suggestion_feedback` WHERE tid IN (' . self::TID_A . ', ' . self::TID_B . ')');
    }

    private static function key(string $dealChar, string $coreChar): DealKey
    {
        return new DealKey(str_repeat($dealChar, 40), str_repeat($coreChar, 40));
    }
}
