<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimPublishProgressRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
final class SimPublishProgressRepositoryTest extends DatabaseTestCase
{
    private const TOKEN_A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const TOKEN_B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

    private SimPublishProgressRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SimPublishProgressRepository($this->db);
    }

    /** Runs fixed test-only SQL on the test connection (inside the rolled-back transaction). */
    private function exec(string $sql): void
    {
        $this->db->query($sql);
    }

    // ── Claim ──────────────────────────────────────────────────────────────────

    public function testClaimRefusesSecondStartForSameSim(): void
    {
        $this->repo->enqueue(999001);

        $first = $this->repo->claimNext(self::TOKEN_A);
        self::assertNotNull($first);
        self::assertSame(999001, $first['sim']);
        self::assertSame(-1, $first['last_step']);
        self::assertNull($first['thread_id']);

        self::assertNull($this->repo->claimNext(self::TOKEN_B));
    }

    public function testClaimAfterStaleHeartbeatResumes(): void
    {
        $this->repo->enqueue(999001);
        self::assertNotNull($this->repo->claimNext(self::TOKEN_A));
        self::assertTrue($this->repo->advance(999001, self::TOKEN_A, 0, '123456789012345678'));
        self::assertTrue($this->repo->advance(999001, self::TOKEN_A, 1, null));
        $this->exec("UPDATE `ibl_sim_publish_progress` SET `heartbeat_at` = NOW() - INTERVAL 301 SECOND WHERE `sim` = 999001");

        $resumed = $this->repo->claimNext(self::TOKEN_B);

        self::assertNotNull($resumed);
        self::assertSame(999001, $resumed['sim']);
        self::assertSame(1, $resumed['last_step']);
        self::assertSame('123456789012345678', $resumed['thread_id']);
        $row = $this->repo->findForSim(999001);
        self::assertNotNull($row);
        self::assertSame(self::TOKEN_B, $row['claim_token']);
        self::assertSame(2, $row['attempts']);
    }

    public function testClaimSkipsExhaustedAttempts(): void
    {
        $this->repo->enqueue(999001);
        $this->exec("UPDATE `ibl_sim_publish_progress` SET `attempts` = 5 WHERE `sim` = 999001");

        self::assertNull($this->repo->claimNext(self::TOKEN_A));
    }

    public function testFinishedRowNotClaimable(): void
    {
        $this->repo->enqueue(999001);
        self::assertNotNull($this->repo->claimNext(self::TOKEN_A));
        self::assertTrue($this->repo->finish(999001, self::TOKEN_A));
        $this->exec("UPDATE `ibl_sim_publish_progress` SET `heartbeat_at` = NOW() - INTERVAL 1 DAY WHERE `sim` = 999001");

        self::assertNull($this->repo->claimNext(self::TOKEN_B));
    }

    public function testOverrideWithoutEnqueueIsNotClaimable(): void
    {
        $this->repo->setTitleOverride(999001, 'BRIDE WARS');

        self::assertNull($this->repo->claimNext(self::TOKEN_A));
        $row = $this->repo->findForSim(999001);
        self::assertNotNull($row);
        self::assertNull($row['enqueued_at']);
        self::assertSame('BRIDE WARS', $row['title_override']);
    }

    public function testClaimSkipsStaleUnstartedRow(): void
    {
        $this->repo->enqueue(999001);
        $this->exec("UPDATE `ibl_sim_publish_progress` SET `enqueued_at` = NOW() - INTERVAL 7 HOUR WHERE `sim` = 999001");

        self::assertNull($this->repo->claimNext(self::TOKEN_A));
    }

    public function testClaimResumesOldStartedRow(): void
    {
        $this->repo->enqueue(999001);
        self::assertNotNull($this->repo->claimNext(self::TOKEN_A));
        self::assertTrue($this->repo->advance(999001, self::TOKEN_A, 0, '123456789012345678'));
        $this->exec(
            "UPDATE `ibl_sim_publish_progress`
             SET `enqueued_at` = NOW() - INTERVAL 7 HOUR, `heartbeat_at` = NOW() - INTERVAL 1 HOUR
             WHERE `sim` = 999001"
        );

        $resumed = $this->repo->claimNext(self::TOKEN_B);

        self::assertNotNull($resumed);
        self::assertSame(999001, $resumed['sim']);
        self::assertSame(0, $resumed['last_step']);
    }

    // ── Advance and finish ─────────────────────────────────────────────────────

    public function testAdvanceRejectsOutOfOrderStep(): void
    {
        $this->repo->enqueue(999001);
        self::assertNotNull($this->repo->claimNext(self::TOKEN_A));

        self::assertFalse($this->repo->advance(999001, self::TOKEN_A, 2, 'should-not-store'));
        $row = $this->repo->findForSim(999001);
        self::assertNotNull($row);
        self::assertSame(-1, $row['last_step']);
        self::assertNull($row['thread_id']);

        self::assertTrue($this->repo->advance(999001, self::TOKEN_A, 0, null));
        self::assertFalse($this->repo->advance(999001, self::TOKEN_A, 0, null), 'a duplicate step is refused');
        $row = $this->repo->findForSim(999001);
        self::assertNotNull($row);
        self::assertSame(0, $row['last_step']);
    }

    public function testAdvanceRejectsForeignToken(): void
    {
        $this->repo->enqueue(999001);
        self::assertNotNull($this->repo->claimNext(self::TOKEN_A));

        self::assertFalse($this->repo->advance(999001, self::TOKEN_B, 0, null));
        self::assertFalse($this->repo->finish(999001, self::TOKEN_B));
        $row = $this->repo->findForSim(999001);
        self::assertNotNull($row);
        self::assertSame(-1, $row['last_step']);
        self::assertNull($row['finished_at']);
    }

    // ── Enqueue, override, retry ───────────────────────────────────────────────

    public function testEnqueueIsIdempotent(): void
    {
        $this->repo->enqueue(999001);
        self::assertNotNull($this->repo->claimNext(self::TOKEN_A));
        self::assertTrue($this->repo->advance(999001, self::TOKEN_A, 0, null));
        $this->exec("UPDATE `ibl_sim_publish_progress` SET `enqueued_at` = '2031-01-02 03:04:05' WHERE `sim` = 999001");

        $this->repo->enqueue(999001);

        $row = $this->repo->findForSim(999001);
        self::assertNotNull($row);
        self::assertSame(0, $row['last_step']);
        self::assertSame('2031-01-02 03:04:05', $row['enqueued_at']);
    }

    public function testTitleOverrideStoredVerbatim(): void
    {
        $title = "x'); --";
        $this->repo->setTitleOverride(999001, $title);

        $row = $this->repo->findForSim(999001);
        self::assertNotNull($row);
        self::assertSame($title, $row['title_override']);

        $this->repo->setTitleOverride(999001, null);
        $row = $this->repo->findForSim(999001);
        self::assertNotNull($row);
        self::assertNull($row['title_override']);
    }

    public function testResetForRetryMakesExhaustedRowClaimable(): void
    {
        $this->repo->enqueue(999001);
        $this->exec("UPDATE `ibl_sim_publish_progress` SET `attempts` = 5, `claim_token` = '" . self::TOKEN_A . "' WHERE `sim` = 999001");
        self::assertNull($this->repo->claimNext(self::TOKEN_B));

        self::assertTrue($this->repo->resetForRetry(999001));

        $claimed = $this->repo->claimNext(self::TOKEN_B);
        self::assertNotNull($claimed);
        self::assertSame(999001, $claimed['sim']);
    }

    public function testResetForRetryRefusesMissingAndFinishedRows(): void
    {
        self::assertFalse($this->repo->resetForRetry(999009));

        $this->repo->enqueue(999001);
        self::assertNotNull($this->repo->claimNext(self::TOKEN_A));
        self::assertTrue($this->repo->finish(999001, self::TOKEN_A));

        self::assertFalse($this->repo->resetForRetry(999001));
    }

    public function testFindForSimReturnsNullForUnknownSim(): void
    {
        self::assertNull($this->repo->findForSim(999009));
    }
}
