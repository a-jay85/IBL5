<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\PipelineStateController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;

class PipelineStateControllerTest extends TestCase
{
    private function makeController(BugReportRepository $bugRepo): PipelineStateController
    {
        return new PipelineStateController($bugRepo);
    }

    public function testReturnsCursorWhenRowExists(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findPipelineState')
            ->with('200000000000000002')
            ->willReturn('300000000000000003');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['last_processed_message_id' => '300000000000000003']);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'channel_id' => '200000000000000002',
        ]);
    }

    public function testReturnsNullWhenNoCursorExists(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findPipelineState')
            ->willReturn(null);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['last_processed_message_id' => null]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'channel_id' => '200000000000000002',
        ]);
    }

    public function testReturns400WhenChannelIdMissing(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, []);
    }
}
