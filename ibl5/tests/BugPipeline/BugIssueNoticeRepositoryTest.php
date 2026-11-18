<?php

declare(strict_types=1);

namespace Tests\BugPipeline;

use BugPipeline\BugIssueNoticeRepository;
use BugPipeline\NoticeNotClaimedException;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
#[Group('mutation-unsafe')]
class BugIssueNoticeRepositoryTest extends DatabaseTestCase
{
    private BugIssueNoticeRepository $repo;

    // Issue numbers in the 900000000+ range: no collision with seeded or prod rows.
    private const ISSUE = 900000001;
    private const ISSUE_B = 900000002;
    private const LEGACY = 900000101;
    // Snowflake above 2^53: must round-trip as an exact decimal string.
    private const REPORTER = '90071992547409931';
    private const REPORTER_BIG = '123456789012345678';
    private const CHANNEL = '200000000000000002';
    private const THREAD = '400000000000000004';
    private const MESSAGE = '300000000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BugIssueNoticeRepository($this->db);
    }

    public function testUpsertDoesNotResetNoticeState(): void
    {
        $this->repo->upsert(self::ISSUE, self::LEGACY, self::REPORTER_BIG, self::CHANNEL, self::THREAD, self::MESSAGE);
        self::assertTrue($this->repo->claim(self::ISSUE));
        self::assertTrue($this->repo->markNotified(self::ISSUE, 'completed'));

        $this->repo->upsert(self::ISSUE, self::LEGACY, self::REPORTER_BIG, self::CHANNEL, self::THREAD, self::MESSAGE);

        self::assertSame('notified', $this->stateOf(self::ISSUE));
        self::assertSame([], $this->repo->listPending(50));
    }

    public function testClaimTwiceSecondReturnsFalse(): void
    {
        $this->repo->upsert(self::ISSUE, null, null, null, null, null);

        self::assertTrue($this->repo->claim(self::ISSUE));
        // Backdate the claim so an unguarded second UPDATE would change claimed_at and report
        // an affected row; within the same second it would be a no-op and mask a missing guard.
        $this->backdateClaim(self::ISSUE, 10);
        self::assertFalse($this->repo->claim(self::ISSUE));
        self::assertSame('claimed', $this->stateOf(self::ISSUE));
    }

    public function testMarkNotifiedRequiresClaimedState(): void
    {
        $this->repo->upsert(self::ISSUE, null, null, null, null, null);

        self::assertFalse($this->repo->markNotified(self::ISSUE, 'completed'), 'pending row must not be markable');
        self::assertSame('pending', $this->stateOf(self::ISSUE));

        self::assertTrue($this->repo->claim(self::ISSUE));
        self::assertTrue($this->repo->markNotified(self::ISSUE, 'not_planned'));
        self::assertSame('notified', $this->stateOf(self::ISSUE));
        self::assertFalse($this->repo->markNotified(self::ISSUE, 'completed'), 'a notified row is terminal');
    }

    public function testReleaseReturnsFailedAtFifthAttempt(): void
    {
        $this->repo->upsert(self::ISSUE, null, null, null, null, null);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            self::assertTrue($this->repo->claim(self::ISSUE), "claim before release {$attempt}");
            self::assertSame('pending', $this->repo->release(self::ISSUE), "release {$attempt} returns the row to pending");
        }

        self::assertTrue($this->repo->claim(self::ISSUE));
        self::assertSame('failed', $this->repo->release(self::ISSUE));
        self::assertSame('failed', $this->stateOf(self::ISSUE));
        self::assertFalse($this->repo->claim(self::ISSUE), 'a failed row is no longer claimable');
    }

    public function testReleaseThrowsWhenRowNotClaimed(): void
    {
        $this->repo->upsert(self::ISSUE, null, null, null, null, null);

        $this->expectException(NoticeNotClaimedException::class);
        $this->repo->release(self::ISSUE);
    }

    public function testSeedNotifiedSkipsNonPendingRow(): void
    {
        $this->repo->upsert(self::ISSUE, null, null, null, null, null);
        $this->repo->upsert(self::ISSUE_B, null, null, null, null, null);
        self::assertTrue($this->repo->claim(self::ISSUE_B));

        self::assertTrue($this->repo->seedNotified(self::ISSUE));
        self::assertFalse($this->repo->seedNotified(self::ISSUE), 'already seeded');
        self::assertFalse($this->repo->seedNotified(self::ISSUE_B), 'claimed row is not seeded');
        self::assertSame('seeded', $this->stateOf(self::ISSUE));
        self::assertSame('claimed', $this->stateOf(self::ISSUE_B));
    }

    public function testRejectsNonDigitDiscordId(): void
    {
        // 20 digits is past Discord's 19-digit max and can overflow BIGINT UNSIGNED.
        foreach (['abc', '12345', '1234567890123456x', '99999999999999999999', '123456789012345678901', ''] as $bad) {
            try {
                $this->repo->upsert(self::ISSUE, null, $bad, null, null, null);
                self::fail("reporter id '{$bad}' should have been rejected");
            } catch (\InvalidArgumentException) {
                // expected
            }
        }
        foreach (['channel', 'thread', 'message'] as $slot) {
            $args = [null, null, null];
            $args[['channel' => 0, 'thread' => 1, 'message' => 2][$slot]] = 'not-a-snowflake';
            try {
                $this->repo->upsert(self::ISSUE, null, null, $args[0], $args[1], $args[2]);
                self::fail("{$slot} id should have been rejected");
            } catch (\InvalidArgumentException) {
                // expected
            }
        }

        self::assertNull($this->stateOf(self::ISSUE), 'no row is written when validation fails');
    }

    public function testRejectsUnknownReason(): void
    {
        $this->repo->upsert(self::ISSUE, null, null, null, null, null);
        self::assertTrue($this->repo->claim(self::ISSUE));

        try {
            $this->repo->markNotified(self::ISSUE, 'wontfix');
            self::fail('unknown reason should have been rejected');
        } catch (\InvalidArgumentException) {
            // expected
        }

        self::assertSame('claimed', $this->stateOf(self::ISSUE));
    }

    public function testListPendingClampsLimit(): void
    {
        for ($i = 0; $i < 52; $i++) {
            $this->repo->upsert(910000000 + $i, null, null, null, null, null);
        }

        self::assertCount(1, $this->repo->listPending(0), 'limit below 1 clamps up to 1');
        self::assertCount(1, $this->repo->listPending(-7));
        self::assertCount(3, $this->repo->listPending(3));
        $capped = $this->repo->listPending(1000);
        self::assertLessThanOrEqual(50, count($capped), 'limit above 50 clamps down to 50');
        self::assertCount(50, $capped);
    }

    public function testListPendingReturnsIdsAsExactStrings(): void
    {
        $this->repo->upsert(self::ISSUE, self::LEGACY, self::REPORTER, self::CHANNEL, self::THREAD, self::MESSAGE);
        $this->repo->upsert(self::ISSUE_B, null, null, null, null, null);

        $rows = array_values(array_filter(
            $this->repo->listPending(50),
            static fn (array $r): bool => $r['issue_number'] === self::ISSUE || $r['issue_number'] === self::ISSUE_B
        ));

        self::assertSame(
            [
                'issue_number' => self::ISSUE,
                'reporter_discord_id' => self::REPORTER,
                'thread_id' => self::THREAD,
                'original_message_id' => self::MESSAGE,
            ],
            $rows[0]
        );
        self::assertNull($rows[1]['reporter_discord_id']);
    }

    public function testMarkSkippedAndListStaleClaims(): void
    {
        $this->repo->upsert(self::ISSUE, null, null, null, null, null);
        $this->repo->upsert(self::ISSUE_B, null, null, null, null, null);
        self::assertTrue($this->repo->claim(self::ISSUE));
        self::assertTrue($this->repo->claim(self::ISSUE_B));

        self::assertSame([], $this->repo->listStaleClaims(60), 'fresh claims are not stale');

        $this->backdateClaim(self::ISSUE, 120);
        self::assertSame([self::ISSUE], $this->repo->listStaleClaims(60));

        self::assertTrue($this->repo->markSkipped(self::ISSUE));
        self::assertFalse($this->repo->markSkipped(self::ISSUE), 'skipped is terminal');
        self::assertSame([], $this->repo->listStaleClaims(60));
        self::assertSame('skipped', $this->stateOf(self::ISSUE));
    }

    private function stateOf(int $issue): ?string
    {
        $stmt = $this->db->prepare('SELECT notice_state FROM ibl_bug_issue_notices WHERE issue_number = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $issue);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        return is_array($row) && is_string($row['notice_state']) ? $row['notice_state'] : null;
    }

    private function backdateClaim(int $issue, int $minutes): void
    {
        $stmt = $this->db->prepare(
            'UPDATE ibl_bug_issue_notices SET claimed_at = NOW() - INTERVAL ? MINUTE WHERE issue_number = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $minutes, $issue);
        $stmt->execute();
        $stmt->close();
    }
}
