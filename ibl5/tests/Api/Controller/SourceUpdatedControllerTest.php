<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\SourceUpdatedController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type BugReportRow from \BugPipeline\BugReportRowCasting
 */
class SourceUpdatedControllerTest extends TestCase
{
    private function makeController(BugReportRepository $bugRepo): SourceUpdatedController
    {
        return new SourceUpdatedController($bugRepo);
    }

    /** @phpstan-return BugReportRow */
    private static function makeRow(string $originalText = '', string $status = 'queued', ?string $threadId = null): array
    {
        return [
            'id'                  => 1,
            'discord_author_id'   => '100000000000000001',
            'channel_id'          => '200000000000000002',
            'original_message_id' => '300000000000000003',
            'original_text'       => $originalText,
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

        $this->makeController($bugRepo)->handle([], [], $responder, ['text' => 'some text']);
    }

    public function testMissingTextReturnsBadRequest(): void
    {
        $bugRepo   = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, ['message_id' => '300000000000000003']);
    }

    public function testUnknownMessageIdReturnsUnmatchedWithoutWriting(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findByOriginalMessageId')
            ->with('300000000000000003')
            ->willReturn(null);
        $bugRepo->expects($this->never())->method('updateSourceText');
        $bugRepo->expects($this->never())->method('reviveForReclassify');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => false, 'changed' => false, 'revived' => false,
                    'status' => null, 'thread_id' => null]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '300000000000000003',
            'text'       => 'app crashes',
        ]);
    }

    public function testIdenticalTextPerformsNoWrites(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findByOriginalMessageId')
            ->willReturn(self::makeRow('app crashes', 'gathering', '500000000000000005'));
        $bugRepo->expects($this->never())->method('updateSourceText');
        $bugRepo->expects($this->never())->method('reviveForReclassify');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => true, 'changed' => false, 'revived' => false,
                    'status' => 'gathering', 'thread_id' => '500000000000000005']);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '300000000000000003',
            'text'       => 'app crashes',
        ]);
    }

    public function testHuntingRowIsNotRevived(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findByOriginalMessageId')
            ->willReturn(self::makeRow('app crashes', 'hunting', '500000000000000005'));
        $bugRepo->expects($this->once())
            ->method('updateSourceText')
            ->with('300000000000000003', 'app crashes — updated');
        $bugRepo->expects($this->never())->method('reviveForReclassify');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => true, 'changed' => true, 'revived' => false,
                    'status' => 'hunting', 'thread_id' => '500000000000000005']);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '300000000000000003',
            'text'       => 'app crashes — updated',
        ]);
    }

    public function testReclassifiableRowGetsRevived(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findByOriginalMessageId')
            ->willReturn(self::makeRow('app crashes', 'dropped', null));
        $bugRepo->expects($this->once())
            ->method('updateSourceText')
            ->with('300000000000000003', 'app crashes — updated with more detail');
        $bugRepo->expects($this->once())
            ->method('reviveForReclassify')
            ->with('300000000000000003');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => true, 'changed' => true, 'revived' => true,
                    'status' => 'queued', 'thread_id' => null]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '300000000000000003',
            'text'       => 'app crashes — updated with more detail',
        ]);
    }
}
