<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\WakeController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WakeControllerTest extends TestCase
{
    private const OPERATOR_ID = '100000000000000001';

    /**
     * @return array{source_message_id: string, thread_id: string, message_id: string, author_id: string, content: string}
     */
    private static function validBody(): array
    {
        return [
            'source_message_id' => '111',
            'thread_id'         => '222',
            'message_id'        => '333',
            'author_id'         => self::OPERATOR_ID,
            'content'           => 'text',
        ];
    }

    public function testOperatorAuthorCallsWakeAndReturnsOutcome(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findByOriginalMessageId')
            ->with('111')
            ->willReturn(['id' => 7, 'status' => 'dormant']);
        $bugRepo->expects($this->once())
            ->method('wakeByOperator')
            ->with(7, '222', '333', self::OPERATOR_ID, 'text')
            ->willReturn(['outcome' => 'woke', 'status' => 'queued']);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['id' => 7, 'outcome' => 'woke', 'status' => 'queued']);

        (new WakeController($bugRepo, self::OPERATOR_ID))->handle([], [], $responder, self::validBody());
    }

    public function testNonOperatorAuthorRejected403AndNoRepositoryCall(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('findByOriginalMessageId');
        $bugRepo->expects($this->never())->method('wakeByOperator');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', self::anything());

        $body = self::validBody();
        $body['author_id'] = '999999999999999999';

        (new WakeController($bugRepo, self::OPERATOR_ID))->handle([], [], $responder, $body);
    }

    public function testEmptyConfiguredOperatorRejects403(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('findByOriginalMessageId');
        $bugRepo->expects($this->never())->method('wakeByOperator');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', self::anything());

        $body = self::validBody();
        $body['author_id'] = '0';

        (new WakeController($bugRepo, ''))->handle([], [], $responder, $body);
    }

    public function testUnknownSourceReturns404AndNoWrite(): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->once())
            ->method('findByOriginalMessageId')
            ->with('111')
            ->willReturn(null);
        $bugRepo->expects($this->never())->method('wakeByOperator');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(404, 'not_found', self::anything());

        (new WakeController($bugRepo, self::OPERATOR_ID))->handle([], [], $responder, self::validBody());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredFieldProvider(): array
    {
        return [
            'source_message_id' => ['source_message_id'],
            'thread_id'         => ['thread_id'],
            'message_id'        => ['message_id'],
            'author_id'         => ['author_id'],
            'content'           => ['content'],
        ];
    }

    #[DataProvider('requiredFieldProvider')]
    public function testReturns400WhenAnyFieldMissing(string $missingField): void
    {
        $bugRepo = $this->createMock(BugReportRepository::class);
        $bugRepo->expects($this->never())->method('findByOriginalMessageId');
        $bugRepo->expects($this->never())->method('wakeByOperator');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(400, 'bad_request', self::anything());

        $body = self::validBody();
        unset($body[$missingField]);

        (new WakeController($bugRepo, self::OPERATOR_ID))->handle([], [], $responder, $body);
    }
}
