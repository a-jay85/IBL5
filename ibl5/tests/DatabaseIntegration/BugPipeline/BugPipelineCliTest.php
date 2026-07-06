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

    // ── Happy paths ───────────────────────────────────────────────────────────

    public function testClaimNextClaimsQueuedRow(): void
    {
        $this->insertReport(self::ID_QUEUED, ['status' => 'queued']);

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

        $r = $this->runCli('list-active-conversations.php');
        self::assertSame(0, $r['code'], $r['stderr']);
        $rows = json_decode(trim($r['stdout']), true);
        self::assertIsArray($rows);

        $ids = array_column($rows, 'id');
        self::assertContains(self::ID_AWAITING, $ids, 'awaiting_info row must be actionable');
        self::assertNotContains(self::ID_DROPPED, $ids, 'dropped (terminal) row must be excluded');
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
        $this->insertReport(self::ID_QUEUED, ['status' => 'queued']);

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
}
