<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Api\Controller;

use Api\Controller\ReactionController;
use Api\Repository\ApiKeyRepository;
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
    private const BOT_KEY      = 'tiergate-bot-key';
    private const GM_KEY       = 'tiergate-gm-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bugRepo  = new BugReportRepository($this->db);

        $this->insertRow('ibl_api_keys', [
            'key_hash'         => hash('sha256', self::BOT_KEY),
            'key_prefix'       => 'tgbotkey',
            'owner_name'       => 'Tier gate bot',
            'permission_level' => 'commissioner',
            'rate_limit_tier'  => 'unlimited',
            'is_active'        => 1,
        ]);
        $this->insertRow('ibl_api_keys', [
            'key_hash'         => hash('sha256', self::GM_KEY),
            'key_prefix'       => 'tggmkey1',
            'owner_name'       => 'Tier gate GM',
            'permission_level' => 'team_owner',
            'rate_limit_tier'  => 'standard',
            'is_active'        => 1,
        ]);
        $_SERVER['HTTP_X_API_KEY'] = self::BOT_KEY;

        $this->controller = new ReactionController($this->bugRepo, new ApiKeyRepository($this->db));
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);
        parent::tearDown();
    }

    public function testStandardTierKeyLeavesAwaitingAjayRowUntouched(): void
    {
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
        $_SERVER['HTTP_X_API_KEY'] = self::GM_KEY;

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can report a reaction.');
        $responder->expects($this->never())->method('success');

        $this->controller->handle([], [], $responder, [
            'message_id' => self::APPROVAL_MSG,
            'emoji'      => '✅',
            'reactor_id' => Discord::getBugPipelineApproverDiscordId(),
        ]);

        $row = $this->bugRepo->findById($id);
        self::assertNotNull($row);
        self::assertSame('awaiting_ajay', $row['status']);
        self::assertSame(self::APPROVAL_MSG, $row['approval_message_id']);
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
