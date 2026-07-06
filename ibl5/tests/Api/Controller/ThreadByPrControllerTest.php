<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\ThreadByPrController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;

class ThreadByPrControllerTest extends TestCase
{
    private function makeController(BugReportRepository $bugRepo): ThreadByPrController
    {
        return new ThreadByPrController($bugRepo);
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
