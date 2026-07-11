<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\BugPipeline;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Shell-out integration for the ibl5/scripts/bug-pipeline/*.php CLI wrappers.
 *
 * Isolation note: each wrapper runs as a SEPARATE `php` child process that opens
 * its OWN mysqli connection, so DatabaseTestCase's begin_transaction()/rollback()
 * isolation cannot reach it. This test therefore commits its fixtures (so the child
 * sees them) and DELETEs them explicitly in setUp + tearDown, using a reserved
 * high id range (>= 990001) that can never collide with seed rows (the CI
 * db-seed.sql carries no ibl_bug_reports / ibl_bug_reporter_profile rows).
 *
 * The child inherits DB_HOST/DB_USER/DB_PASS/DB_NAME from this process's env
 * (config.php:41-44 reads them via getenv), so `php <cmd>.php` hits the SAME
 * test DB bin/db-test-up bootstrapped — not main.
 */
#[Group('database')]
class BugPipelineCliTest extends DatabaseTestCase
{
    /** Reserved fixture id range — never collides with seed rows. */
    private const ID_QUEUED = 990001;
    private const ID_AWAITING = 990002;
    private const ID_DROPPED = 990003;
    private const ID_BLOCKED = 990004;

    /** Snowflake-shaped fixture ids (19 digits) — asserted to survive as strings. */
    private const AUTHOR_ID = '990000000000000001';
    private const CHANNEL_ID = '990000000000000002';
    private const MESSAGE_ID = '990000000000000003';
    private const THREAD_ID = '990000000000000004';
    private const PROFILE_ID = '990000000000000009';

    private string $scriptsDir;

    protected function setUp(): void
    {
        parent::setUp();
        // End the parent's isolating transaction and run autocommit so the shelled
        // child (its own connection) sees our fixtures.
        $this->db->commit();
        $this->db->autocommit(true);
        $this->scriptsDir = dirname(__DIR__, 3) . '/scripts/bug-pipeline';
        $this->cleanupFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        parent::tearDown();
    }

    private function cleanupFixtures(): void
    {
        $this->db->query('DELETE FROM `ibl_bug_reports` WHERE id >= 990001');
        $this->db->query("DELETE FROM `ibl_bug_reporter_profile` WHERE discord_author_id = " . self::PROFILE_ID);
    }

    /**
     * @param array<string, int|string|null> $overrides
     */
    private function insertReport(int $id, array $overrides = []): void
    {
        $cols = array_merge([
            'id'                  => $id,
            'discord_author_id'   => self::AUTHOR_ID,
            'channel_id'          => self::CHANNEL_ID,
            'original_message_id' => (string) ((int) self::MESSAGE_ID + $id),
            'original_text'       => 'fixture report',
            'status'              => 'queued',
        ], $overrides);

        $names = implode(', ', array_map(static fn (string $c): string => "`$c`", array_keys($cols)));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $types = '';
        $values = [];
        foreach ($cols as $v) {
            $types .= is_int($v) ? 'i' : 's';
            $values[] = $v;
        }
        $stmt = $this->db->prepare("INSERT INTO `ibl_bug_reports` ($names) VALUES ($placeholders)");
        self::assertNotFalse($stmt, 'fixture insert prepare failed: ' . $this->db->error);
        $stmt->bind_param($types, ...$values);
        self::assertTrue($stmt->execute(), 'fixture insert failed: ' . $stmt->error);
        $stmt->close();
    }

    /**
     * @param list<string> $args
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runCli(string $script, array $args = []): array
    {
        $cmd = array_merge(['php', $this->scriptsDir . '/' . $script], $args);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        self::assertIsResource($proc, "failed to launch $script");
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        return [
            'code' => $code,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        ];
    }

    private function statusOf(int $id): ?string
    {
        $res = $this->db->query("SELECT status FROM `ibl_bug_reports` WHERE id = $id");
        self::assertNotFalse($res);
        $row = $res->fetch_assoc();
        return $row === null ? null : (string) $row['status'];
    }

    /** @return array<string, string|null> The named columns of a fixture row. */
    private function columnsOf(int $id, string ...$cols): array
    {
        $list = implode(', ', array_map(static fn (string $c): string => "`$c`", $cols));
        $res = $this->db->query("SELECT $list FROM `ibl_bug_reports` WHERE id = $id");
        self::assertNotFalse($res);
        $row = $res->fetch_assoc();
        self::assertNotNull($row, "row $id should exist");
        return array_map(static fn ($v): ?string => $v === null ? null : (string) $v, $row);
    }

    private function futureTs(): string
    {
        return date('Y-m-d H:i:s', time() + 3600);
    }

    private function pastTs(): string
    {
        return date('Y-m-d H:i:s', time() - 3600);
    }

    // ── Happy paths ───────────────────────────────────────────────────────────

    public function testClaimNextClaimsQueuedRow(): void
    {
        // Only a CLASSIFIED queued row (class IS NOT NULL) is huntable (#5b claim semantics).
        $this->insertReport(self::ID_QUEUED, ['status' => 'queued', 'class' => 'bug']);

        $r = $this->runCli('claim-next.php', ['--owner=testworker:1']);
        self::assertSame(0, $r['code'], $r['stderr']);

        $json = json_decode(trim($r['stdout']), true);
        self::assertIsArray($json, 'claim-next should print a JSON row; got: ' . $r['stdout']);
        self::assertSame('hunting', $json['status']);
        // Snowflake ids survive as JSON strings (never int-cast).
        self::assertSame(self::AUTHOR_ID, $json['discord_author_id']);
        self::assertIsString($json['discord_author_id']);

        self::assertSame('hunting', $this->statusOf(self::ID_QUEUED), 'DB row should now be hunting');
        $lease = $this->db->query("SELECT lease_owner FROM `ibl_bug_reports` WHERE id = " . self::ID_QUEUED);
        self::assertNotFalse($lease);
        $leaseRow = $lease->fetch_assoc();
        self::assertSame('testworker:1', $leaseRow['lease_owner']);
    }

    public function testListActiveConversationsSurfacesActionableRows(): void
    {
        $this->insertReport(self::ID_AWAITING, [
            'status' => 'awaiting_info',
            'class' => 'bug',
            'thread_id' => self::THREAD_ID,
            'last_gm_reply_at' => '2026-01-01 12:00:00',
            'last_processed_at' => '2026-01-01 11:00:00',
        ]);
        $this->insertReport(self::ID_DROPPED, ['status' => 'dropped', 'class' => 'not_a_thing']);
        // Ready-for-plan (approval NULLed) is actionable; still-parked (approval set) is NOT.
        $this->insertReport(990005, ['status' => 'awaiting_ajay', 'class' => 'feature']); // approval_message_id NULL
        $this->insertReport(990006, ['status' => 'awaiting_ajay', 'class' => 'feature', 'approval_message_id' => '990000000000000077']);
        // Usage-limit parked with a future blocked_until must be skipped.
        $future = date('Y-m-d H:i:s', time() + 3600);
        $this->insertReport(990007, ['status' => 'queued', 'blocked_until' => $future]);

        $r = $this->runCli('list-active-conversations.php');
        self::assertSame(0, $r['code'], $r['stderr']);
        $rows = json_decode(trim($r['stdout']), true);
        self::assertIsArray($rows);

        $ids = array_column($rows, 'id');
        self::assertContains(self::ID_AWAITING, $ids, 'awaiting_info row must be actionable');
        self::assertContains(990005, $ids, 'awaiting_ajay + approval NULL (ready-for-plan) must be actionable');
        self::assertNotContains(self::ID_DROPPED, $ids, 'dropped (terminal) row must be excluded');
        self::assertNotContains(990006, $ids, 'awaiting_ajay + approval SET (parked) must be excluded');
        self::assertNotContains(990007, $ids, 'future blocked_until (parked) must be excluded');
    }

    public function testTransitionAndReporterTechRoundTrip(): void
    {
        $this->insertReport(self::ID_QUEUED, ['status' => 'queued']);

        $t = $this->runCli('transition.php', [(string) self::ID_QUEUED, 'awaiting_info', '--class=bug']);
        self::assertSame(0, $t['code'], $t['stderr']);
        $tj = json_decode(trim($t['stdout']), true);
        self::assertTrue($tj['ok']);
        self::assertSame('awaiting_info', $this->statusOf(self::ID_QUEUED));

        // Reporter tech level round-trip.
        $unknown = $this->runCli('get-reporter-tech-level.php', [self::PROFILE_ID]);
        self::assertSame(0, $unknown['code']);
        self::assertNull(json_decode(trim($unknown['stdout']), true)['tech_level']);

        $set = $this->runCli('set-reporter-tech-level.php', [self::PROFILE_ID, 'technical']);
        self::assertSame(0, $set['code'], $set['stderr']);

        $get = $this->runCli('get-reporter-tech-level.php', [self::PROFILE_ID]);
        self::assertSame('technical', json_decode(trim($get['stdout']), true)['tech_level']);
    }

    public function testConditionalWritersReminderAndClearBlocked(): void
    {
        $this->insertReport(self::ID_BLOCKED, [
            'status' => 'awaiting_info',
            'class' => 'bug',
            'blocked_until' => '2026-01-01 00:00:00',
        ]);

        // --reminder-now stamps reminder_sent_at once.
        $this->runCli('transition.php', [(string) self::ID_BLOCKED, 'awaiting_info', '--reminder-now']);
        $res = $this->db->query("SELECT reminder_sent_at, blocked_until FROM `ibl_bug_reports` WHERE id = " . self::ID_BLOCKED);
        self::assertNotFalse($res);
        $row = $res->fetch_assoc();
        self::assertNotNull($row['reminder_sent_at'], 'reminder_sent_at should be stamped');
        $firstStamp = $row['reminder_sent_at'];

        // At-most-once: a second --reminder-now is a no-op (guarded WHERE reminder_sent_at IS NULL).
        $this->runCli('transition.php', [(string) self::ID_BLOCKED, 'awaiting_info', '--reminder-now']);
        $res2 = $this->db->query("SELECT reminder_sent_at FROM `ibl_bug_reports` WHERE id = " . self::ID_BLOCKED);
        self::assertSame($firstStamp, $res2->fetch_assoc()['reminder_sent_at'], 'reminder must not re-stamp');

        // --clear-blocked NULLs blocked_until.
        $this->runCli('transition.php', [(string) self::ID_BLOCKED, 'awaiting_info', '--clear-blocked']);
        $res3 = $this->db->query("SELECT blocked_until FROM `ibl_bug_reports` WHERE id = " . self::ID_BLOCKED);
        self::assertNull($res3->fetch_assoc()['blocked_until'], 'blocked_until should be cleared');
    }

    // ── Negative paths ─────────────────────────────────────────────────────────

    public function testClaimRaceSecondCallEmpty(): void
    {
        $this->insertReport(self::ID_QUEUED, ['status' => 'queued', 'class' => 'bug']);

        $first = $this->runCli('claim-next.php', ['--owner=w1']);
        self::assertSame(0, $first['code']);
        self::assertNotSame('', trim($first['stdout']), 'first claim should print the row');

        // No queued rows remain — the second claim is a lost race: empty stdout, exit 0.
        $second = $this->runCli('claim-next.php', ['--owner=w2']);
        self::assertSame(0, $second['code']);
        self::assertSame('', trim($second['stdout']), 'second claim should print nothing');

        // DB unchanged by the second call — still owned by w1.
        $lease = $this->db->query("SELECT lease_owner FROM `ibl_bug_reports` WHERE id = " . self::ID_QUEUED);
        self::assertSame('w1', $lease->fetch_assoc()['lease_owner']);
    }

    public function testMalformedArgvRejectedWithoutDbMutation(): void
    {
        $this->insertReport(self::ID_QUEUED, ['status' => 'queued']);

        foreach ([
            [(string) self::ID_QUEUED, 'not_a_status'],
            ['abc', 'queued'],
        ] as $badArgs) {
            $r = $this->runCli('transition.php', $badArgs);
            self::assertSame(1, $r['code'], 'expected exit 1 for: ' . implode(' ', $badArgs));
            self::assertNotSame('', trim($r['stderr']), 'expected STDERR message');
        }
        // No mutation: the row is still queued.
        self::assertSame('queued', $this->statusOf(self::ID_QUEUED));

        // Bad tech level rejected, no profile written.
        $bad = $this->runCli('set-reporter-tech-level.php', [self::PROFILE_ID, 'superuser']);
        self::assertSame(1, $bad['code']);
        $chk = $this->db->query("SELECT COUNT(*) c FROM `ibl_bug_reporter_profile` WHERE discord_author_id = " . self::PROFILE_ID);
        self::assertSame(0, (int) $chk->fetch_assoc()['c']);
    }

    public function testSnowflakeWithSqlMetacharactersCannotInject(): void
    {
        $this->insertReport(self::ID_QUEUED, ['status' => 'queued']);

        $r = $this->runCli('get-reporter-tech-level.php', ['1;DROP TABLE ibl_bug_reports;--']);
        self::assertSame(0, $r['code'], $r['stderr']);
        $json = json_decode(trim($r['stdout']), true);
        self::assertNull($json['tech_level'], 'metacharacter key is a literal profile lookup → no row');

        // The table still exists with the fixture row intact — the "s" bind never interpolated.
        $count = $this->db->query('SELECT COUNT(*) c FROM `ibl_bug_reports`');
        self::assertNotFalse($count, 'ibl_bug_reports must still exist');
        self::assertGreaterThanOrEqual(1, (int) $count->fetch_assoc()['c']);
    }

    // ── Hunt lifecycle (PR #5b) ──────────────────────────────────────────────────

    /**
     * Classify-before-hunt: an UNclassified queued row (class IS NULL) is never
     * claimed into hunting — it is still awaiting the classifier, and hunting it
     * would reintroduce the exact deadlock #5a deferred.
     */
    public function testUnclassifiedQueuedRowNotClaimed(): void
    {
        $this->insertReport(self::ID_QUEUED, ['status' => 'queued']); // class NULL

        $r = $this->runCli('claim-next.php', ['--owner=worker:1']);
        self::assertSame(0, $r['code'], $r['stderr']);
        self::assertSame('', trim($r['stdout']), 'unclassified queued row must not be claimable');
        self::assertSame('queued', $this->statusOf(self::ID_QUEUED), 'row must remain queued');
    }

    /**
     * Row 1: `transition <id> needs_human --release-lease` both sets the terminal
     * status AND clears the lease columns, so a given-up hunt frees its slot.
     */
    public function testTransitionNeedsHumanReleasesLease(): void
    {
        $this->insertReport(self::ID_QUEUED, [
            'status'        => 'hunting',
            'class'         => 'bug',
            'lease_owner'   => 'worker:1',
            'lease_expires' => $this->futureTs(),
        ]);

        $r = $this->runCli('transition.php', [(string) self::ID_QUEUED, 'needs_human', '--release-lease']);
        self::assertSame(0, $r['code'], $r['stderr']);

        $cols = $this->columnsOf(self::ID_QUEUED, 'status', 'lease_owner', 'lease_expires');
        self::assertSame('needs_human', $cols['status']);
        self::assertNull($cols['lease_owner'], 'lease_owner must be released');
        self::assertNull($cols['lease_expires'], 'lease_expires must be released');
    }

    /**
     * Row 2: a malformed transition against a leased `hunting` row exits non-zero
     * and leaves the row — status and lease — byte-for-byte unchanged.
     */
    public function testMalformedTransitionLeavesLeasedHuntingRowUntouched(): void
    {
        $lease = $this->futureTs();
        $this->insertReport(self::ID_QUEUED, [
            'status'        => 'hunting',
            'class'         => 'bug',
            'lease_owner'   => 'worker:1',
            'lease_expires' => $lease,
        ]);

        foreach ([
            [(string) self::ID_QUEUED, 'not_a_status'],
            ['abc', 'hunting'],
        ] as $badArgs) {
            $r = $this->runCli('transition.php', $badArgs);
            self::assertSame(1, $r['code'], 'expected exit 1 for: ' . implode(' ', $badArgs));
        }

        $cols = $this->columnsOf(self::ID_QUEUED, 'status', 'lease_owner', 'lease_expires');
        self::assertSame('hunting', $cols['status'], 'status must be untouched');
        self::assertSame('worker:1', $cols['lease_owner'], 'lease_owner must be untouched');
        self::assertSame($lease, $cols['lease_expires'], 'lease_expires must be untouched');
    }

    /**
     * Row 6: a `hunting` row with a FRESH lease is single-flight-protected —
     * claim-next neither reclaims it (lease not expired) nor claims it (not queued),
     * so it returns empty and the existing owner is preserved.
     */
    public function testFreshLeaseHuntingRowNotReclaimed(): void
    {
        $this->insertReport(self::ID_QUEUED, [
            'status'        => 'hunting',
            'class'         => 'bug',
            'lease_owner'   => 'worker:1',
            'lease_expires' => $this->futureTs(),
        ]);

        $r = $this->runCli('claim-next.php', ['--owner=worker:2']);
        self::assertSame(0, $r['code'], $r['stderr']);
        self::assertSame('', trim($r['stdout']), 'a fresh-lease hunting row must not be claimable');

        $cols = $this->columnsOf(self::ID_QUEUED, 'status', 'lease_owner');
        self::assertSame('hunting', $cols['status']);
        self::assertSame('worker:1', $cols['lease_owner'], 'owner must be preserved');
    }

    /**
     * Row 7: a `hunting` row whose lease has EXPIRED is reclaimed in place by
     * claim-next — it stays `hunting` but is re-stamped to the new owner with a
     * fresh lease, so a crashed worker's slot is recovered without losing the row.
     */
    public function testStaleLeaseHuntingRowIsReclaimed(): void
    {
        $this->insertReport(self::ID_QUEUED, [
            'status'        => 'hunting',
            'class'         => 'bug',
            'lease_owner'   => 'worker:1',
            'lease_expires' => $this->pastTs(),
        ]);

        $r = $this->runCli('claim-next.php', ['--owner=worker:2']);
        self::assertSame(0, $r['code'], $r['stderr']);
        $json = json_decode(trim($r['stdout']), true);
        self::assertIsArray($json, 'stale-lease reclaim should print the row; got: ' . $r['stdout']);
        self::assertSame('hunting', $json['status']);

        $cols = $this->columnsOf(self::ID_QUEUED, 'status', 'lease_owner', 'lease_expires');
        self::assertSame('hunting', $cols['status']);
        self::assertSame('worker:2', $cols['lease_owner'], 'reclaim must re-stamp the new owner');
        self::assertNotNull($cols['lease_expires']);
        self::assertGreaterThan(date('Y-m-d H:i:s'), (string) $cols['lease_expires'], 'lease must be renewed into the future');
    }

    /**
     * Row 24: a `needs_human` row is terminal for automation — claim-next never
     * claims it and list-active-conversations never surfaces it.
     */
    public function testNeedsHumanRowIsNeitherClaimedNorSurfaced(): void
    {
        $this->insertReport(self::ID_QUEUED, ['status' => 'needs_human', 'class' => 'bug']);

        $claim = $this->runCli('claim-next.php', ['--owner=worker:2']);
        self::assertSame(0, $claim['code'], $claim['stderr']);
        self::assertSame('', trim($claim['stdout']), 'needs_human must not be claimable');
        self::assertSame('needs_human', $this->statusOf(self::ID_QUEUED), 'row must be untouched');

        $list = $this->runCli('list-active-conversations.php');
        self::assertSame(0, $list['code'], $list['stderr']);
        $rows = json_decode(trim($list['stdout']), true);
        self::assertIsArray($rows);
        self::assertNotContains(self::ID_QUEUED, array_column($rows, 'id'), 'needs_human must not be surfaced');
    }

    /**
     * Row 28: a `blocked` row with a past lease_expires is immune to stale-lease
     * reclaim (that only targets `hunting`), and the enumerator surfaces it only
     * once blocked_until has elapsed — never while still parked in the future.
     */
    public function testBlockedRowImmuneToReclaimAndSurfacesOnlyWhenRipe(): void
    {
        $this->insertReport(self::ID_QUEUED, [
            'status'        => 'blocked',
            'class'         => 'bug',
            'lease_owner'   => 'worker:1',
            'lease_expires' => $this->pastTs(),
            'blocked_until' => $this->futureTs(),
        ]);

        // Stale-lease reclaim only targets `hunting` — a blocked row is left alone.
        $claim = $this->runCli('claim-next.php', ['--owner=worker:2']);
        self::assertSame(0, $claim['code'], $claim['stderr']);
        self::assertSame('', trim($claim['stdout']), 'blocked row must not be reclaimed');
        $cols = $this->columnsOf(self::ID_QUEUED, 'status', 'lease_owner');
        self::assertSame('blocked', $cols['status']);
        self::assertSame('worker:1', $cols['lease_owner']);

        // Future blocked_until → not yet actionable.
        $parked = $this->runCli('list-active-conversations.php');
        self::assertSame(0, $parked['code'], $parked['stderr']);
        $parkedRows = json_decode(trim($parked['stdout']), true);
        self::assertIsArray($parkedRows);
        self::assertNotContains(self::ID_QUEUED, array_column($parkedRows, 'id'), 'future-blocked row must be skipped');

        // Once blocked_until has elapsed the row becomes actionable.
        $this->db->query("UPDATE `ibl_bug_reports` SET blocked_until = '" . $this->pastTs() . "' WHERE id = " . self::ID_QUEUED);
        $ripe = $this->runCli('list-active-conversations.php');
        self::assertSame(0, $ripe['code'], $ripe['stderr']);
        $ripeRows = json_decode(trim($ripe['stdout']), true);
        self::assertIsArray($ripeRows);
        self::assertContains(self::ID_QUEUED, array_column($ripeRows, 'id'), 'ripe blocked row must surface');
    }
}
