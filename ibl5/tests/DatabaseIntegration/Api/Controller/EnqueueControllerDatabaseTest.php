<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Api\Controller;

use Api\Controller\EnqueueController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\Attributes\Group;
use Repositories\TeamIdentityRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * DB-grounded tests for EnqueueController authz paths.
 * Relies on the seed fixture: discord_id = '100000000000000001' on teamid=1.
 */
#[Group('database')]
class EnqueueControllerDatabaseTest extends DatabaseTestCase
{
    private EnqueueController $controller;
    private BugReportRepository $bugRepo;

    private const KNOWN_AUTHOR   = '100000000000000001';
    private const UNKNOWN_AUTHOR = '999999999999999999';
    private const CHANNEL        = '200000000000000002';
    private const MSG_ID         = '300000000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bugRepo  = new BugReportRepository($this->db);
        $this->controller = new EnqueueController(
            $this->bugRepo,
            new TeamIdentityRepository($this->db)
        );
    }

    public function testAuthorizedAuthorInsertsRowAndReturnsReportId(): void
    {
        $captured = null;
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(self::callback(function (array $payload) use (&$captured): bool {
                $captured = $payload;
                return true;
            }));

        $this->controller->handle([], [], $responder, [
            'author_id'  => self::KNOWN_AUTHOR,
            'channel_id' => self::CHANNEL,
            'message_id' => self::MSG_ID,
            'text'       => 'app crashes on save',
        ]);

        self::assertNotNull($captured);
        self::assertTrue($captured['authorized']);
        self::assertIsInt($captured['report_id']);
        self::assertGreaterThan(0, $captured['report_id']);

        // Verify the row actually landed in the DB
        $row = $this->bugRepo->findById($captured['report_id']);
        self::assertNotNull($row);
        self::assertSame('queued', $row['status']);
        self::assertSame(self::KNOWN_AUTHOR, $row['discord_author_id']);
    }

    public function testUnknownAuthorDoesNotInsertRowAndAdvancesWatermarkOnly(): void
    {
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['authorized' => false, 'report_id' => null]);

        $this->controller->handle([], [], $responder, [
            'author_id'  => self::UNKNOWN_AUTHOR,
            'channel_id' => self::CHANNEL,
            'message_id' => self::MSG_ID,
            'text'       => 'random message',
        ]);

        // No row for this message_id
        self::assertNull($this->bugRepo->findByOriginalMessageId(self::MSG_ID));
        // But the watermark was advanced
        self::assertSame(self::MSG_ID, $this->bugRepo->findPipelineState(self::CHANNEL));
    }

    public function testIdempotentReEnqueueReturnsSameReportId(): void
    {
        $captures = [];
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->exactly(2))
            ->method('success')
            ->with(self::callback(function (array $payload) use (&$captures): bool {
                $captures[] = $payload;
                return true;
            }));

        $payload = [
            'author_id'  => self::KNOWN_AUTHOR,
            'channel_id' => self::CHANNEL,
            'message_id' => self::MSG_ID,
            'text'       => 'app crashes',
        ];
        $this->controller->handle([], [], $responder, $payload);
        $this->controller->handle([], [], $responder, $payload);

        self::assertCount(2, $captures);
        self::assertSame($captures[0]['report_id'], $captures[1]['report_id'], 'Re-enqueue must return same id');
    }
}
