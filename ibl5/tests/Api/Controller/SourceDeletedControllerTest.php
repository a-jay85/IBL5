<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\SourceDeletedController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type BugReportRow from \BugPipeline\BugReportRowCasting
 */
class SourceDeletedControllerTest extends TestCase
{
    private function makeController(BugReportRepository $bugRepo): SourceDeletedController
    {
        return new SourceDeletedController($bugRepo);
    }

    /** @phpstan-return BugReportRow */
    private static function makeRow(string $status = 'queued', ?string $threadId = null): array
    {
        return [
            'id'                  => 1,
            'discord_author_id'   => '100000000000000001',
            'channel_id'          => '200000000000000002',
            'original_message_id' => '300000000000000003',
            'original_text'       => 'app crashes',
            'thread_id'           => $threadId,
            'class'               => null,
            'status'              => $status,
            'lease_owner'         => null,
            'lease_expires'       => null,
            'hunt_attempts'       => 0,
            'pr_number'           => null,
            'issue_number'        => null,
            'approval_message_id' => null,
            'blocked_until'       => null,
            'last_gm_reply_at'    => null,
            'last_processed_at'   => null,
            'reminder_sent_at'    => null,
            'created_at'          => '2026-01-01 00:00:00',
            'updated_at'          => '2026-01-01 00:00:00',
        ];
    }

    public function testMissingMessageIdReturnsBadRequest(): void
    {
        $bugRepo   = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, []);
    }

    public function testUnknownMessageIdReturnsUnmatchedWithoutWriting(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findByOriginalMessageId')
            ->with('300000000000000003')
            ->willReturn(null);
        $bugRepo->expects($this->never())->method('markSourceDeleted');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => false, 'dropped' => false, 'status' => null, 'thread_id' => null]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '300000000000000003',
        ]);
    }

    public function testMarksSourceDeletedAndReturnsDroppedTrue(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findByOriginalMessageId')
            ->willReturn(self::makeRow('queued', null));
        $bugRepo->expects($this->once())
            ->method('markSourceDeleted')
            ->with('300000000000000003')
            ->willReturn(true);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => true, 'dropped' => true, 'status' => 'queued', 'thread_id' => null]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '300000000000000003',
        ]);
    }

    public function testReportsDroppedFalseWhenMarkReturnsAlreadyHandled(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findByOriginalMessageId')
            ->willReturn(self::makeRow('dropped', '500000000000000005'));
        $bugRepo->expects($this->once())
            ->method('markSourceDeleted')
            ->with('300000000000000003')
            ->willReturn(false);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => true, 'dropped' => false, 'status' => 'dropped',
                    'thread_id' => '500000000000000005']);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '300000000000000003',
        ]);
    }
}
