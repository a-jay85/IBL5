<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\ThreadByPrController;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;

class ThreadByPrControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'test-bot-key';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY'], $_GET['key']);
    }

    private function makeController(BugReportRepository $bugRepo, string $tier = 'unlimited'): ThreadByPrController
    {
        $keys = self::createStub(ApiKeyRepository::class);
        $keys->method('findByHash')->willReturn([
            'key_hash'         => hash('sha256', 'test-bot-key'),
            'key_prefix'       => 'test-bot',
            'owner_name'       => 'x',
            'permission_level' => 'public',
            'rate_limit_tier'  => $tier,
        ]);
        return new ThreadByPrController($bugRepo, $keys);
    }

    public function testStandardTierKeyRejected403AndNoRepositoryCall(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('findThreadIdByPrNumber');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can look up a thread by PR.');
        $responder->expects($this->never())->method('success');

        $this->makeController($bugRepo, 'standard')->handle([], [], $responder, ['pr_number' => 42]);
    }

    public function testMissingKeyRejected403AndNoRepositoryCall(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);

        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('findThreadIdByPrNumber');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can look up a thread by PR.');
        $responder->expects($this->never())->method('success');

        $this->makeController($bugRepo)->handle([], [], $responder, ['pr_number' => 42]);
    }

    public function testElevatedTierKeyPassesGuard(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findThreadIdByPrNumber')
            ->with(42)
            ->willReturn('700000000000000007');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['thread_id' => '700000000000000007']);

        $this->makeController($bugRepo, 'elevated')->handle([], [], $responder, ['pr_number' => 42]);
    }

    public function testReturnsThreadIdForKnownPr(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findThreadIdByPrNumber')
            ->with(42)
            ->willReturn('700000000000000007');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['thread_id' => '700000000000000007']);

        $this->makeController($bugRepo)->handle([], [], $responder, ['pr_number' => 42]);
    }

    public function testReturnsNullThreadIdWhenPrHasNoThread(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findThreadIdByPrNumber')
            ->willReturn(null);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['thread_id' => null]);

        $this->makeController($bugRepo)->handle([], [], $responder, ['pr_number' => 99]);
    }

    public function testAcceptsNumericStringPrNumber(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findThreadIdByPrNumber')
            ->with(42);

        $responder = self::createStub(JsonResponder::class);

        $this->makeController($bugRepo)->handle([], [], $responder, ['pr_number' => '42']);
    }

    public function testReturns400WhenPrNumberMissing(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, []);
    }

    public function testReturns400WhenPrNumberIsZero(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, ['pr_number' => 0]);
    }

    public function testReturns400WhenPrNumberIsNonNumericString(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $this->makeController($bugRepo)->handle([], [], $responder, ['pr_number' => 'abc']);
    }
}
