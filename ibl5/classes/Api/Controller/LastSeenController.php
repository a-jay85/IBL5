<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;

class LastSeenController implements ControllerInterface
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
        $channelId = $body['channel_id'] ?? null;
        $messageId = $body['message_id'] ?? null;
        if (!is_string($channelId) || $channelId === '' || !is_string($messageId) || $messageId === '') {
            $responder->error(400, 'bad_request', 'Missing channel_id or message_id.');
            return;
        }

        $this->bugRepo->upsertPipelineState($channelId, $messageId);
        $responder->success(['ok' => true]);
    }
}
