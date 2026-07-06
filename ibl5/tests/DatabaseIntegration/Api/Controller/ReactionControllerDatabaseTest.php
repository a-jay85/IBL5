<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Api\Controller;

use Api\Controller\ReactionController;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use Discord\Discord;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * DB-grounded tests for ReactionController — verifies the advanceOnApproval
 * status guard actually fires against a real row.
 */
#[Group('database')]
class ReactionControllerDatabaseTest extends DatabaseTestCase
{
    private ReactionController $controller;
    private BugReportRepository $bugRepo;

    private const APPROVAL_MSG = '600000000000000006';
    private const AUTHOR       = '100000000000000001';
    private const CHANNEL      = '200000000000000002';
    private const MSG_ID       = '300000000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bugRepo  = new BugReportRepository($this->db);
        $this->controller = new ReactionController($this->bugRepo);
    }

    public function testApproverReactionAdvancesAwaitingAjayRow(): void
    {
        // Insert a row in awaiting_ajay with the approval_message_id set
        $id = $this->insertRow('ibl_bug_reports', [
            'discord_author_id'   => self::AUTHOR,
            'channel_id'          => self::CHANNEL,
            'original_message_id' => self::MSG_ID,
            'original_text'       => 'bug report',
            'status'              => 'awaiting_ajay',
            'approval_message_id' => self::APPROVAL_MSG,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        $approverId = Discord::getBugPipelineApproverDiscordId();
        $responder  = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['advanced' => true]);

        $this->controller->handle([], [], $responder, [
            'message_id' => self::APPROVAL_MSG,
            'emoji'      => '✅',
            'reactor_id' => $approverId,
        ]);

        // approval_message_id must be NULL, status stays 'awaiting_ajay'
        $row = $this->bugRepo->findById($id);
        self::assertNotNull($row);
        self::assertNull($row['approval_message_id']);
        self::assertSame('awaiting_ajay', $row['status']);
    }

    public function testApproverReactionReturnsFalseWhenStatusIsNotAwaitingAjay(): void
    {
        $this->insertRow('ibl_bug_reports', [
            'discord_author_id'   => self::AUTHOR,
            'channel_id'          => self::CHANNEL,
            'original_message_id' => self::MSG_ID,
            'original_text'       => 'bug report',
            'status'              => 'queued',
            'approval_message_id' => self::APPROVAL_MSG,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        $approverId = Discord::getBugPipelineApproverDiscordId();
        $responder  = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['advanced' => false]);

        $this->controller->handle([], [], $responder, [
            'message_id' => self::APPROVAL_MSG,
            'emoji'      => '✅',
            'reactor_id' => $approverId,
        ]);
    }
}
