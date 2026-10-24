<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\ClaimAnnouncer;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
final class ClaimAnnouncerTest extends DatabaseTestCase
{
    private SimSummaryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SimSummaryRepository($this->db);
    }

    public function testFirstClaimPostsStartedExactlyOnce(): void
    {
        $this->repo->queuePendingIfAbsent(990201);
        self::assertTrue($this->repo->claimPending(990201, 'actions'));

        /** @var list<string> $posted */
        $posted = [];
        $announcer = new ClaimAnnouncer($this->repo, static function (string $line) use (&$posted): void {
            $posted[] = $line;
        });

        self::assertSame(
            ['notified' => 'started', 'error' => null],
            $announcer->announceStart(990201, 'actions'),
        );
        self::assertSame(['Sim 990201 recap started on GitHub Actions.'], $posted);
        self::assertSame('started', $this->findRow(990201)['notified_state']);

        $secondAnnouncer = new ClaimAnnouncer($this->repo, static function (string $line) use (&$posted): void {
            $posted[] = $line;
        });

        self::assertSame(
            ['notified' => 'unchanged', 'error' => null],
            $secondAnnouncer->announceStart(990201, 'mac'),
        );
        self::assertCount(1, $posted, 'A second host must not post a second started line');
    }

    public function testReclaimAfterParkDoesNotPostStarted(): void
    {
        $this->repo->queuePendingIfAbsent(990202);
        // The state a park leaves behind: the sim already posted a retry notice.
        $this->db->query("UPDATE `ibl_sim_summaries` SET `notified_state` = 'retrying' WHERE `sim` = 990202");
        self::assertTrue($this->repo->claimPending(990202, 'mac'));

        /** @var list<string> $posted */
        $posted = [];
        $announcer = new ClaimAnnouncer($this->repo, static function (string $line) use (&$posted): void {
            $posted[] = $line;
        });

        self::assertSame(
            ['notified' => 'unchanged', 'error' => null],
            $announcer->announceStart(990202, 'mac'),
        );
        self::assertSame([], $posted);
        self::assertSame('retrying', $this->findRow(990202)['notified_state']);
    }

    public function testPostFailureKeepsTheClaimAndRevertsNotifiedState(): void
    {
        $this->repo->queuePendingIfAbsent(990203);
        self::assertTrue($this->repo->claimPending(990203, 'actions'));

        $announcer = new ClaimAnnouncer($this->repo, static function (string $line): void {
            throw new \RuntimeException('https://discord.example/secret');
        });

        $result = $announcer->announceStart(990203, 'actions');

        self::assertSame(['notified' => 'failed', 'error' => \RuntimeException::class], $result);
        self::assertStringNotContainsString('discord.example', implode(' ', $result));

        $row = $this->findRow(990203);
        self::assertSame('actions', $row['claimed_by']);
        self::assertSame('generating', $row['status']);
        self::assertNull($row['notified_state']);
    }

    /**
     * @return array{sim: int, status: string, recap_text: ?string, intro_text: ?string, outro_text: ?string, themes_used: ?string, attempts: int, claimed_at: ?string, blocked_until: ?string, generated_at: ?string, route: string, routed_at: ?string, claimed_by: ?string, last_cause: ?string, flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, notified_state: ?string}
     */
    private function findRow(int $sim): array
    {
        $row = $this->repo->find($sim);
        self::assertNotNull($row);

        return $row;
    }
}
