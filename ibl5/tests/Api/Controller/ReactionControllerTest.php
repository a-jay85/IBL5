<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\ReactionController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use Discord\Discord;
use PHPUnit\Framework\TestCase;

class ReactionControllerTest extends TestCase
{
    private function makeController(BugReportRepository $bugRepo): ReactionController
    {
        return new ReactionController($bugRepo);
    }

    public function testApproverWithCheckmarkCallsAdvanceAndReturnsTrue(): void
    {
        $bugRepo  = $this->createMock(BugReportRepository::class);
        $approverId = Discord::getBugPipelineApproverDiscordId();

        $bugRepo->expects($this->once())
            ->method('advanceOnApproval')
            ->with('600000000000000006')
            ->willReturn(true);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['advanced' => true]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '600000000000000006',
            'emoji'      => '✅',
            'reactor_id' => $approverId,
        ]);
    }

    public function testNonApproverReactorDoesNotCallAdvance(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('advanceOnApproval');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['advanced' => false]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '600000000000000006',
            'emoji'      => '✅',
            'reactor_id' => '999999999999999999',
        ]);
    }

    public function testWrongEmojiDoesNotCallAdvance(): void
    {
        $bugRepo    = $this->createMock(BugReportRepository::class);
        $approverId = Discord::getBugPipelineApproverDiscordId();

        $bugRepo->expects($this->never())->method('advanceOnApproval');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['advanced' => false]);

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '600000000000000006',
            'emoji'      => '👎',
            'reactor_id' => $approverId,
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
            'emoji'      => '✅',
            'reactor_id' => '999999999999999999',
        ]);
    }

    public function testReturns400WhenEmojiMissing(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '600000000000000006',
            'reactor_id' => '999999999999999999',
        ]);
    }
}
