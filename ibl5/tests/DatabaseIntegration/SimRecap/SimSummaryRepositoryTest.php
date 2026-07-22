<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
final class SimSummaryRepositoryTest extends DatabaseTestCase
{
    private SimSummaryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SimSummaryRepository($this->db);
    }

    // ── Happy-path tests ───────────────────────────────────────────────────────

    public function testQueuePendingIfAbsentCreatesARow(): void
    {
        $created = $this->repo->queuePendingIfAbsent(999001);
        self::assertTrue($created);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, $row['attempts']);
    }

    public function testClaimNextPendingClaimsTheOldestFirst(): void
    {
        $this->repo->queuePendingIfAbsent(999002);
        $this->repo->queuePendingIfAbsent(999001);

        $claimed = $this->repo->claimNextPending();
        self::assertSame(999001, $claimed);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('generating', $row['status']);
        self::assertSame(1, $row['attempts']);
        self::assertNotNull($row['claimed_at']);
    }

    public function testMarkDoneStoresTextAndThemes(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        $this->repo->markDone(999001, 'Recap prose.', '["comeback"]');

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('done', $row['status']);
        self::assertSame('Recap prose.', $row['recap_text']);
        self::assertSame('["comeback"]', $row['themes_used']);
        self::assertNotNull($row['generated_at']);
    }

    public function testMarkDoneUpsertsWhenNoRowWasQueued(): void
    {
        $this->repo->markDone(999005, 'Upserted recap.', null);

        $row = $this->repo->find(999005);
        self::assertNotNull($row);
        self::assertSame('done', $row['status']);
    }

    public function testRecentThemesReturnsNewestFirstAndCapsAtTheLimit(): void
    {
        // Insert six done rows for sims 999010–999015 with distinct themes
        for ($sim = 999010; $sim <= 999015; $sim++) {
            $this->repo->markDone($sim, "Recap for sim {$sim}.", "[\"theme-{$sim}\"]");
        }

        $rows = $this->repo->recentThemes(5);

        self::assertCount(5, $rows, 'recentThemes(5) must return exactly 5 rows');
        // Newest first: 999015, 999014, 999013, 999012, 999011
        self::assertSame('["theme-999015"]', $rows[0]['themes_used'], 'First row must be newest sim');
        self::assertSame('["theme-999011"]', $rows[4]['themes_used'], 'Last row must be fifth-newest sim');
    }

    // ── Negative / boundary tests ──────────────────────────────────────────────

    public function testQueuePendingIfAbsentIsIdempotent(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        $created = $this->repo->queuePendingIfAbsent(999001);
        self::assertFalse($created);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('generating', $row['status'], 'Status must not be reset to pending');
    }

    public function testClaimPendingLosesTheRace(): void
    {
        $this->repo->queuePendingIfAbsent(999001);

        $first = $this->repo->claimPending(999001);
        self::assertTrue($first);

        $second = $this->repo->claimPending(999001);
        self::assertFalse($second);
    }

    public function testClaimNextPendingReturnsNullWhenQueueIsEmpty(): void
    {
        self::assertNull($this->repo->claimNextPending());
    }

    public function testClaimNextPendingSkipsABlockedRow(): void
    {
        // Part 1: 999001 blocked, 999002 unblocked — expect 999002
        $this->repo->queuePendingIfAbsent(999001);
        $this->db->query(
            "UPDATE `ibl_sim_summaries` SET `blocked_until` = NOW() + INTERVAL 60 MINUTE WHERE `sim` = 999001"
        );
        $this->repo->queuePendingIfAbsent(999002);

        $claimed = $this->repo->claimNextPending();
        self::assertSame(999002, $claimed);

        // Part 2 (boundary): blocked_until = NOW() should be eligible (<=)
        // 999001 is still blocked 60 minutes out, 999002 is now generating.
        $this->repo->queuePendingIfAbsent(999003);
        $this->db->query(
            "UPDATE `ibl_sim_summaries` SET `blocked_until` = NOW() WHERE `sim` = 999003"
        );

        $boundary = $this->repo->claimNextPending();
        self::assertSame(999003, $boundary, 'blocked_until = NOW() must be eligible for claiming');
    }

    public function testParkOrFailParksBelowTheCeiling(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        $result = $this->repo->parkOrFail(999001);
        self::assertSame('pending', $result);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);
        self::assertNull($row['claimed_at']);
        self::assertNotNull($row['blocked_until'], 'blocked_until must be set after parking');
    }

    public function testParkOrFailFailsAtTheCeiling(): void
    {
        // Queue and claim once (attempts=1)
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        // Reset to pending manually so we can claim again
        $this->db->query(
            "UPDATE `ibl_sim_summaries` SET `status` = 'pending', `claimed_at` = NULL WHERE `sim` = 999001"
        );

        // Claim again (attempts=2, at ceiling)
        $this->repo->claimPending(999001);

        $result = $this->repo->parkOrFail(999001);
        self::assertSame('failed', $result, 'At attempts >= 2 ceiling, parkOrFail must fail the row');

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('failed', $row['status']);
        self::assertNull($row['blocked_until']);
    }

    public function testParkOrFailReturnsNoneWhenNotGenerating(): void
    {
        $this->repo->markDone(999001, 'text', null);

        $result = $this->repo->parkOrFail(999001);
        self::assertSame('none', $result);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('done', $row['status'], 'parkOrFail(none) must not touch a done row');
    }

    public function testReclaimStaleClaimIgnoresAFreshClaim(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        $reclaimed = $this->repo->reclaimStaleClaim(45);
        self::assertNull($reclaimed, 'A freshly-claimed row must not be reclaimed');
    }

    public function testReclaimStaleClaimRecoversAnAbandonedRow(): void
    {
        $this->repo->queuePendingIfAbsent(999001);
        $this->repo->claimPending(999001);

        // Back-date claimed_at to simulate abandonment
        $this->db->query(
            "UPDATE `ibl_sim_summaries` SET `claimed_at` = NOW() - INTERVAL 46 MINUTE WHERE `sim` = 999001"
        );

        $reclaimed = $this->repo->reclaimStaleClaim(45);
        self::assertSame(999001, $reclaimed);

        $row = $this->repo->find(999001);
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);
        self::assertNull($row['claimed_at']);

        // Second call must return null — no double reclaim
        $second = $this->repo->reclaimStaleClaim(45);
        self::assertNull($second, 'Re-asserted predicates must prevent double reclaim');
    }

    public function testRecentThemesToleratesMalformedJson(): void
    {
        $this->repo->markDone(999001, 'text', '"not-a-list"');

        $rows = $this->repo->recentThemes(5);

        self::assertNotEmpty($rows, 'recentThemes must return rows even with malformed JSON');
        $found = false;
        foreach ($rows as $row) {
            if ($row['themes_used'] === '"not-a-list"') {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Malformed JSON must be returned as-is without throwing');
    }
}
