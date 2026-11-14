<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Api\Controller;

use Api\Controller\LastSeenController;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * DB-grounded tests for the LastSeenController service-key guard.
 */
#[Group('database')]
class LastSeenControllerDatabaseTest extends DatabaseTestCase
{
    private LastSeenController $controller;

    private const CHANNEL = '200000000000000012';
    private const MSG_ID  = '300000000000000013';
    private const BOT_KEY = 'svkguard-bot-key';
    private const GM_KEY  = 'svkguard-gm-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertRow('ibl_api_keys', [
            'key_hash'         => hash('sha256', self::BOT_KEY),
            'key_prefix'       => 'svkbotky',
            'owner_name'       => 'Service key guard bot',
            'permission_level' => 'commissioner',
            'rate_limit_tier'  => 'unlimited',
            'is_active'        => 1,
        ]);
        $this->insertRow('ibl_api_keys', [
            'key_hash'         => hash('sha256', self::GM_KEY),
            'key_prefix'       => 'svkgmkey',
            'owner_name'       => 'Service key guard GM',
            'permission_level' => 'team_owner',
            'rate_limit_tier'  => 'standard',
            'is_active'        => 1,
        ]);
        $_SERVER['HTTP_X_API_KEY'] = self::BOT_KEY;

        $this->controller = new LastSeenController(
            new BugReportRepository($this->db),
            new ApiKeyRepository($this->db)
        );
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);
        parent::tearDown();
    }

    public function testStandardTierKeyWritesNoPipelineState(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = self::GM_KEY;

        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('error')
            ->with(403, 'forbidden', 'Only the pipeline bot can record the last-seen message.');
        $responder->expects($this->never())->method('success');

        $this->controller->handle([], [], $responder, [
            'channel_id' => self::CHANNEL,
            'message_id' => self::MSG_ID,
        ]);

        self::assertSame(
            0,
            $this->countRows('SELECT COUNT(*) AS cnt FROM ibl_bug_pipeline_state WHERE channel_id = ?', self::CHANNEL)
        );
    }

    public function testServiceKeyUpsertsPipelineState(): void
    {
        $responder = $this->createMock(JsonResponder::class);
        $responder->expects($this->once())
            ->method('success')
            ->with(['ok' => true]);

        $this->controller->handle([], [], $responder, [
            'channel_id' => self::CHANNEL,
            'message_id' => self::MSG_ID,
        ]);

        self::assertSame(
            1,
            $this->countRows('SELECT COUNT(*) AS cnt FROM ibl_bug_pipeline_state WHERE channel_id = ?', self::CHANNEL)
        );
    }

    private function countRows(string $sql, string $param): int
    {
        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $param);
        $stmt->execute();
        /** @var array{cnt: int|string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['cnt'] ?? 0);
    }
}
