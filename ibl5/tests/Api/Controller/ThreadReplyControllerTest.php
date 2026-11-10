<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\ThreadReplyController;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;

class ThreadReplyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'test-bot-key';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY'], $_GET['key']);
    }

    private function makeController(BugReportRepository $bugRepo, string $tier = 'unlimited'): ThreadReplyController
    {
        $keys = self::createStub(ApiKeyRepository::class);
        $keys->method('findByHash')->willReturn([
            'key_hash'         => hash('sha256', 'test-bot-key'),
            'key_prefix'       => 'test-bot',
            'owner_name'       => 'x',
            'permission_level' => 'public',
            'rate_limit_tier'  => $tier,
        ]);
        return new ThreadReplyController($bugRepo, $keys);
    }

    public function testStandardTierKeyRejected403AndNoRepositoryCall(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('stampThreadReply');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can stamp a thread reply.');
        $responder->expects($this->never())->method('success');

        $this->makeController($bugRepo, 'standard')->handle([], [], $responder, [
            'thread_id'  => '400000000000000004',
            'message_id' => '500000000000000005',
        ]);
    }

    public function testMissingKeyRejected403AndNoRepositoryCall(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);

        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('stampThreadReply');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can stamp a thread reply.');
        $responder->expects($this->never())->method('success');

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'thread_id'  => '400000000000000004',
            'message_id' => '500000000000000005',
        ]);
    }

    /**
     * Deleting the stampThreadReply call in ThreadReplyController::handle turns this red.
     */
    public function testElevatedTierKeyPassesGuard(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('stampThreadReply')
            ->with('400000000000000004')
            ->willReturn(true);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['matched' => true]);

        $this->makeController($bugRepo, 'elevated')->handle([], [], $responder, [
            'thread_id'  => '400000000000000004',
            'message_id' => '500000000000000005',
        ]);
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
