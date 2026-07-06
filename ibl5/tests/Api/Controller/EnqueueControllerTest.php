<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\EnqueueController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

class EnqueueControllerTest extends TestCase
{
    private function makeController(BugReportRepository $bugRepo, TeamIdentityRepositoryInterface $teamRepo): EnqueueController
    {
        return new EnqueueController($bugRepo, $teamRepo);
    }

    public function testAuthorizedBranchCallsEnqueueAndReturnsReportId(): void
    {
        $bugRepo  = $this->createMock(BugReportRepository::class);
        $teamRepo = $this->createMock(TeamIdentityRepositoryInterface::class);

        $teamRepo->expects($this->once())
            ->method('isKnownDiscordID')
            ->with('100000000000000001')
            ->willReturn(true);

        $bugRepo->expects($this->once())
            ->method('enqueueAuthorizedAndAdvance')
            ->willReturn(7);

        $bugRepo->expects($this->never())
            ->method('upsertPipelineState');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['authorized' => true, 'report_id' => 7]);

        $this->makeController($bugRepo, $teamRepo)->handle([], [], $responder, [
            'author_id'  => '100000000000000001',
            'channel_id' => '200000000000000002',
            'message_id' => '300000000000000003',
            'text'       => 'app crashes',
        ]);
    }

    public function testUnauthorizedBranchCallsUpsertStateAndReturnsNull(): void
    {
        $bugRepo  = $this->createMock(BugReportRepository::class);
        $teamRepo = $this->createMock(TeamIdentityRepositoryInterface::class);

        $teamRepo->expects($this->once())
            ->method('isKnownDiscordID')
            ->willReturn(false);

        $bugRepo->expects($this->never())
            ->method('enqueueAuthorizedAndAdvance');

        $bugRepo->expects($this->once())
            ->method('upsertPipelineState')
            ->with('200000000000000002', '300000000000000003');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['authorized' => false, 'report_id' => null]);

        $this->makeController($bugRepo, $teamRepo)->handle([], [], $responder, [
            'author_id'  => '999999999999999999',
            'channel_id' => '200000000000000002',
            'message_id' => '300000000000000003',
            'text'       => 'spam',
        ]);
    }

    public function testReturns400WhenBodyIsNull(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $teamRepo = self::createStub(TeamIdentityRepositoryInterface::class);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo, $teamRepo)->handle([], [], $responder, null);
    }

    public function testReturns400WhenTextMissing(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $teamRepo = self::createStub(TeamIdentityRepositoryInterface::class);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo, $teamRepo)->handle([], [], $responder, [
            'author_id'  => '100000000000000001',
            'channel_id' => '200000000000000002',
            'message_id' => '300000000000000003',
            // 'text' deliberately omitted
        ]);
    }
}
