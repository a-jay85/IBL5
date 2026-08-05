<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\EnqueueController;
use Api\Response\JsonResponder;
use BugPipeline\AttachmentInputValidator;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

class EnqueueControllerTest extends TestCase
{
    private function makeController(BugReportRepository $bugRepo, TeamIdentityRepositoryInterface $teamRepo): EnqueueController
    {
        // Real validator: it is a pure, dependency-free value object — mocking it would only
        // restate its rules. Attachment-branch tests below drive it with real payloads.
        return new EnqueueController($bugRepo, $teamRepo, new AttachmentInputValidator());
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
        $bugRepo->expects($this->never())
            ->method('insertAttachments');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['authorized' => true, 'report_id' => 7, 'attachments_stored' => 0]);

        // No `attachments` key at all → attachments_stored: 0, insertAttachments never called.
        $this->makeController($bugRepo, $teamRepo)->handle([], [], $responder, [
            'author_id'  => '100000000000000001',
            'channel_id' => '200000000000000002',
            'message_id' => '300000000000000003',
            'text'       => 'app crashes',
        ]);
    }

    public function testAuthorizedBranchValidatesAndPersistsAttachments(): void
    {
        $bugRepo  = $this->createMock(BugReportRepository::class);
        $teamRepo = self::createStub(TeamIdentityRepositoryInterface::class);

        $teamRepo->method('isKnownDiscordID')->willReturn(true);
        $bugRepo->method('enqueueAuthorizedAndAdvance')->willReturn(42);

        // Two supplied, one malformed (bad host) → exactly one validated survivor reaches storage.
        $bugRepo->expects($this->once())
            ->method('insertAttachments')
            ->with(42, [[
                'attachment_id' => '700000000000000001',
                'original_url'  => 'https://cdn.discordapp.com/attachments/1/2/shot.png',
                'local_path'    => null,
                'filename'      => 'shot.png',
                'content_type'  => 'image/png',
                'file_size'     => 12345,
            ]]);

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['authorized' => true, 'report_id' => 42, 'attachments_stored' => 1]);

        $this->makeController($bugRepo, $teamRepo)->handle([], [], $responder, [
            'author_id'  => '100000000000000001',
            'channel_id' => '200000000000000002',
            'message_id' => '300000000000000003',
            'text'       => 'crash with screenshot',
            'attachments' => [
                [
                    'attachment_id' => '700000000000000001',
                    'original_url'  => 'https://cdn.discordapp.com/attachments/1/2/shot.png',
                    'local_path'    => null,
                    'filename'      => 'shot.png',
                    'content_type'  => 'image/png',
                    'file_size'     => 12345,
                ],
                [
                    'attachment_id' => '700000000000000002',
                    'original_url'  => 'https://evil.example.com/steal.png', // rejected: host not allowed
                    'local_path'    => null,
                    'filename'      => 'steal.png',
                    'content_type'  => 'image/png',
                    'file_size'     => 999,
                ],
            ],
        ]);
    }

    public function testAttachmentPersistenceFailureDoesNotFailEnqueue(): void
    {
        $bugRepo  = self::createStub(BugReportRepository::class);
        $teamRepo = self::createStub(TeamIdentityRepositoryInterface::class);

        $teamRepo->method('isKnownDiscordID')->willReturn(true);
        $bugRepo->method('enqueueAuthorizedAndAdvance')->willReturn(42);

        // Storage throws — a real orphan FK cannot (INSERT IGNORE downgrades to a warning), so the
        // degrade-never-block wrapper must be exercised with a genuinely throwing repo (see NOTE 2).
        $bugRepo->method('insertAttachments')
            ->willThrowException(new \RuntimeException('db exploded'));

        // Enqueue still succeeds; attachments_stored reflects the validated survivor count (1),
        // frozen to the pre-persist count regardless of the storage failure.
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['authorized' => true, 'report_id' => 42, 'attachments_stored' => 1]);
        $responder->expects($this->never())->method('error');

        $this->makeController($bugRepo, $teamRepo)->handle([], [], $responder, [
            'author_id'  => '100000000000000001',
            'channel_id' => '200000000000000002',
            'message_id' => '300000000000000003',
            'text'       => 'crash with screenshot',
            'attachments' => [[
                'attachment_id' => '700000000000000001',
                'original_url'  => 'https://cdn.discordapp.com/attachments/1/2/shot.png',
                'local_path'    => null,
                'filename'      => 'shot.png',
                'content_type'  => 'image/png',
                'file_size'     => 12345,
            ]],
        ]);
    }

    public function testNonArrayAttachmentsFieldIsIgnoredAndDoesNotFailEnqueue(): void
    {
        $bugRepo  = $this->createMock(BugReportRepository::class);
        $teamRepo = self::createStub(TeamIdentityRepositoryInterface::class);

        $teamRepo->method('isKnownDiscordID')->willReturn(true);
        $bugRepo->method('enqueueAuthorizedAndAdvance')->willReturn(9);

        // An off-shape `attachments` (a string here) must cost nothing: no persist, no 400.
        $bugRepo->expects($this->never())->method('insertAttachments');

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['authorized' => true, 'report_id' => 9, 'attachments_stored' => 0]);
        $responder->expects($this->never())->method('error');

        $this->makeController($bugRepo, $teamRepo)->handle([], [], $responder, [
            'author_id'   => '100000000000000001',
            'channel_id'  => '200000000000000002',
            'message_id'  => '300000000000000003',
            'text'        => 'crash with screenshot',
            'attachments' => 'not-an-array',
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
