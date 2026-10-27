<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use Discord\Discord;

/**
 * POST bug-pipeline/wake — the operator @mentioned the bot in a bug thread.
 *
 * Wakes a dormant report (or appends operator context to one already moving).
 * Authorization is the message AUTHOR, never the mention: author_id must equal the
 * configured operator snowflake, and an unconfigured operator fails closed.
 */
class WakeController implements ControllerInterface
{
    private BugReportRepository $bugRepo;

    private ApiKeyRepository $apiKeys;

    private string $operatorId;

    public function __construct(BugReportRepository $bugRepo, ApiKeyRepository $apiKeys, ?string $operatorId = null)
    {
        $this->bugRepo = $bugRepo;
        $this->apiKeys = $apiKeys;
        $this->operatorId = $operatorId ?? Discord::getBugPipelineApproverDiscordId();
    }

    /**
     * True only for a service key. A GM can mint a key for themselves on the API Keys page,
     * and those are always the 'standard' rate-limit tier. The bot's key must be elevated or
     * unlimited (README.bugbot.md), so a self-issued key can never reach this route.
     */
    private function callerIsServiceKey(): bool
    {
        $raw = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if (!is_string($raw) || $raw === '') {
            $raw = $_GET['key'] ?? '';
        }
        if (!is_string($raw) || $raw === '') {
            return false;
        }
        $key = $this->apiKeys->findByHash(hash('sha256', $raw));
        return $key !== null && $key['rate_limit_tier'] !== 'standard';
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        $sourceMessageId = $body['source_message_id'] ?? null;
        $threadId        = $body['thread_id']         ?? null;
        $messageId       = $body['message_id']        ?? null;
        $authorId        = $body['author_id']         ?? null;
        $content         = $body['content']           ?? null;
        if (!is_string($sourceMessageId) || $sourceMessageId === ''
            || !is_string($threadId) || $threadId === ''
            || !is_string($messageId) || $messageId === ''
            || !is_string($authorId) || $authorId === ''
            || !is_string($content) || $content === ''
        ) {
            $responder->error(400, 'bad_request', 'Missing source_message_id, thread_id, message_id, author_id, or content.');
            return;
        }

        // Runs before any repository call, so a rejected request reads and writes nothing.
        // An empty operator (unconfigured) is rejected outright => fail-closed.
        // author_id is body data and Discord ids are public, so the caller must also be the bot's key.
        if (!$this->callerIsServiceKey()) {
            $responder->error(403, 'forbidden', 'Only the pipeline bot can wake a report.');
            return;
        }
        if ($this->operatorId === '' || $authorId !== $this->operatorId) {
            $responder->error(403, 'forbidden', 'Only the pipeline operator can wake a report.');
            return;
        }

        $row = $this->bugRepo->findByOriginalMessageId($sourceMessageId);
        if ($row === null) {
            $responder->error(404, 'not_found', 'No bug report for that source message.');
            return;
        }

        $id = (int) $row['id'];
        $result = $this->bugRepo->wakeByOperator($id, $threadId, $messageId, $authorId, $content);
        if ($result['outcome'] === 'unknown') {
            $responder->error(404, 'not_found', 'No bug report for that source message.');
            return;
        }

        $responder->success([
            'id'      => $id,
            'outcome' => $result['outcome'],
            'status'  => $result['status'],
        ]);
    }
}
