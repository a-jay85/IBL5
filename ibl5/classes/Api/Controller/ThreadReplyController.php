<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;

class ThreadReplyController implements ControllerInterface
{
    private BugReportRepository $bugRepo;

    private ServiceKeyGuard $serviceKeys;

    public function __construct(BugReportRepository $bugRepo, ApiKeyRepository $apiKeys)
    {
        $this->bugRepo = $bugRepo;
        $this->serviceKeys = new ServiceKeyGuard($apiKeys);
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        // Runs before any body field is read, so a rejected request reads and writes nothing.
        if (!$this->serviceKeys->callerIsServiceKey()) {
            $responder->error(403, 'forbidden', 'Only the pipeline bot can stamp a thread reply.');
            return;
        }

        $threadId  = $body['thread_id']  ?? null;
        $messageId = $body['message_id'] ?? null;
        if (!is_string($threadId) || $threadId === '' || !is_string($messageId) || $messageId === '') {
            $responder->error(400, 'bad_request', 'Missing thread_id or message_id.');
            return;
        }

        // Keyed on thread_id; message_id is not used for matching (see stampThreadReply).
        // No pipeline-state upsert here — payload carries no channel_id (resolved decision).
        $matched = $this->bugRepo->stampThreadReply($threadId);
        $responder->success(['matched' => $matched]);
    }
}
