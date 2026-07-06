<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\LastSeenController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;

class LastSeenControllerTest extends TestCase
{
    private function makeController(BugReportRepository $bugRepo): LastSeenController
    {
        return new LastSeenController($bugRepo);
    }

    public function testCallsUpsertPipelineStateAndReturnsOk(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('upsertPipelineState')
            ->with('200000000000000002', '300000000000000003');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['ok' => true]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'channel_id' => '200000000000000002',
            'message_id' => '300000000000000003',
        ]);
    }

    public function testReturns400WhenChannelIdMissing(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '300000000000000003',
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
            'channel_id' => '200000000000000002',
        ]);
    }
}
