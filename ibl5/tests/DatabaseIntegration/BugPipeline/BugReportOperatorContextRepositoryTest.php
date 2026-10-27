<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\BugPipeline;

use BugPipeline\BugReportOperatorContextRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
final class BugReportOperatorContextRepositoryTest extends DatabaseTestCase
{
    private const AUTHOR   = '100000000000000001';
    private const CHANNEL  = '200000000000000002';
    private const OP_AUTH  = '123456789012345678';
    private const MSG_A    = '123456789012345678';
    private const MSG_B    = '123456789012345679';

    private BugReportOperatorContextRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BugReportOperatorContextRepository($this->db);
    }

    public function testAppendReturnsTrueOnceThenFalseOnDuplicateMessage(): void
    {
        $id = $this->insertBugReport('310000000000000001');

        self::assertTrue($this->repo->append($id, self::MSG_A, self::OP_AUTH, 'first'));
        self::assertFalse($this->repo->append($id, self::MSG_A, self::OP_AUTH, 'replayed'));

        $rows = $this->repo->listForReportIds([$id])[$id];
        self::assertCount(1, $rows);
        self::assertSame('first', $rows[0]['content']);
    }

    public function testAppendTruncatesTo4000Chars(): void
    {
        $id = $this->insertBugReport('310000000000000001');

        $this->repo->append($id, self::MSG_A, self::OP_AUTH, str_repeat('é', 4001));

        $rows = $this->repo->listForReportIds([$id])[$id];
        self::assertSame(4000, mb_strlen($rows[0]['content']));
    }

    public function testFindOperatorContextForReportIdsReturnsStringMessageIds(): void
    {
        $id = $this->insertBugReport('310000000000000001');
        $this->repo->append($id, self::MSG_A, self::OP_AUTH, 'hello');

        $rows = $this->repo->listForReportIds([$id])[$id];

        self::assertSame('123456789012345678', $rows[0]['message_id']);
        self::assertSame('hello', $rows[0]['content']);
        self::assertNotSame('', $rows[0]['created_at']);
    }

    public function testListForReportIdsGroupsByReportAndOrdersOldestFirst(): void
    {
        $a = $this->insertBugReport('310000000000000001');
        $b = $this->insertBugReport('310000000000000002');
        $this->repo->append($a, self::MSG_A, self::OP_AUTH, 'a-first');
        $this->repo->append($b, '123456789012345680', self::OP_AUTH, 'b-only');
        $this->repo->append($a, self::MSG_B, self::OP_AUTH, 'a-second');

        $map = $this->repo->listForReportIds([$a, $b]);

        self::assertSame(['a-first', 'a-second'], array_column($map[$a], 'content'));
        self::assertSame(['b-only'], array_column($map[$b], 'content'));
    }

    public function testListForReportIdsEmptyInputReturnsEmpty(): void
    {
        self::assertSame([], $this->repo->listForReportIds([]));
    }

    private function insertBugReport(string $originalMessageId): int
    {
        return $this->insertRow('ibl_bug_reports', [
            'discord_author_id'   => self::AUTHOR,
            'channel_id'          => self::CHANNEL,
            'original_message_id' => $originalMessageId,
            'original_text'       => 'test bug report',
            'status'              => 'dormant',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
    }
}
