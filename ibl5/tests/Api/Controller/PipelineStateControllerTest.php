<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\PipelineStateController;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;

class PipelineStateControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'test-bot-key';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY'], $_GET['key']);
    }

    private function makeController(BugReportRepository $bugRepo, string $tier = 'unlimited'): PipelineStateController
    {
        $keys = self::createStub(ApiKeyRepository::class);
        $keys->method('findByHash')->willReturn([
            'key_hash'         => hash('sha256', 'test-bot-key'),
            'key_prefix'       => 'test-bot',
            'owner_name'       => 'x',
            'permission_level' => 'public',
            'rate_limit_tier'  => $tier,
        ]);
        return new PipelineStateController($bugRepo, $keys);
    }

    public function testStandardTierKeyRejected403AndNoRepositoryCall(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('findPipelineState');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can read pipeline state.');
        $responder->expects($this->never())->method('success');

        $this->makeController($bugRepo, 'standard')->handle([], [], $responder, [
            'channel_id' => '200000000000000002',
        ]);
    }

    public function testMissingKeyRejected403AndNoRepositoryCall(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);

        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('findPipelineState');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can read pipeline state.');
        $responder->expects($this->never())->method('success');

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'channel_id' => '200000000000000002',
        ]);
    }

    public function testElevatedTierKeyPassesGuard(): void
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

        $this->makeController($bugRepo, 'elevated')->handle([], [], $responder, [
            'channel_id' => '200000000000000002',
        ]);
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
