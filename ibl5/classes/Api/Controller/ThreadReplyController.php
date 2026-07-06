<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;

class ThreadReplyController implements ControllerInterface
{
    private BugReportRepository $bugRepo;

    public function __construct(BugReportRepository $bugRepo)
    {
        $this->bugRepo = $bugRepo;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
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
