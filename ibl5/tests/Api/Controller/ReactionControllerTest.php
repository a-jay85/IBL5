<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\ReactionController;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use Discord\Discord;
use PHPUnit\Framework\TestCase;

class ReactionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'test-bot-key';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY'], $_GET['key']);
    }

    private function makeController(BugReportRepository $bugRepo, string $tier = 'unlimited'): ReactionController
    {
        $keys = self::createStub(ApiKeyRepository::class);
        $keys->method('findByHash')->willReturn([
            'key_hash'         => hash('sha256', 'test-bot-key'),
            'key_prefix'       => 'test-bot',
            'owner_name'       => 'x',
            'permission_level' => 'public',
            'rate_limit_tier'  => $tier,
        ]);
        return new ReactionController($bugRepo, $keys);
    }

    public function testStandardTierKeyRejected403AndNoRepositoryCall(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('advanceOnApproval');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can report a reaction.');
        $responder->expects($this->never())->method('success');

        $this->makeController($bugRepo, 'standard')->handle([], [], $responder, [
            'message_id' => '600000000000000006',
            'emoji'      => '✅',
            'reactor_id' => Discord::getBugPipelineApproverDiscordId(),
        ]);
    }

    public function testMissingKeyRejected403AndNoRepositoryCall(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);

        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('advanceOnApproval');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can report a reaction.');
        $responder->expects($this->never())->method('success');

        $this->makeController($bugRepo)->handle([], [], $responder, [
            'message_id' => '600000000000000006',
            'emoji'      => '✅',
            'reactor_id' => Discord::getBugPipelineApproverDiscordId(),
        ]);
    }

    public function testStandardTierKeyWithMalformedBodyRejected403Not400(): void
    {
        $bugRepo = self::createStub(BugReportRepository::class);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can report a reaction.');

        $this->makeController($bugRepo, 'standard')->handle([], [], $responder, []);
    }

    public function testElevatedTierKeyApproverStillAdvances(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('advanceOnApproval')
            ->with('600000000000000006')
            ->willReturn(true);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['advanced' => true]);

        $this->makeController($bugRepo, 'elevated')->handle([], [], $responder, [
            'message_id' => '600000000000000006',
            'emoji'      => '✅',
            'reactor_id' => Discord::getBugPipelineApproverDiscordId(),
        ]);
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
