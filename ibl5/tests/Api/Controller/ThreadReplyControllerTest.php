<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\ThreadReplyController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;

class ThreadReplyControllerTest extends TestCase
{
    private function makeController(BugReportRepository $bugRepo): ThreadReplyController
    {
        return new ThreadReplyController($bugRepo);
    }

    public function testMatchedReturnsTrue(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('stampThreadReply')
            ->with('400000000000000004')
            ->willReturn(true);

        $bugRepo->expects($this->never())
            ->method('upsertPipelineState');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => true]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'thread_id'  => '400000000000000004',
            'message_id' => '500000000000000005',
        ]);
    }

    public function testUnmatchedReturnsFalse(): void
    {
        $bugRepo = self::createStub(BugReportRepository::class);
        $bugRepo->method('stampThreadReply')->willReturn(false);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => false]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'thread_id'  => '000000000000000000',
            'message_id' => '500000000000000005',
        ]);
    }

    public function testReturns400WhenThreadIdMissing(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '500000000000000005',
        ]);
    }

    public function testReturns400WhenMessageIdMissing(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'thread_id' => '400000000000000004',
        ]);
    }
}
