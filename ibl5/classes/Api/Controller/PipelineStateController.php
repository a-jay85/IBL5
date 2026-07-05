<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;

class PipelineStateController implements ControllerInterface
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
        if (!is_string($channelId) || $channelId === '') {
            $responder->error(400, 'bad_request', 'Missing channel_id.');
            return;
        }
        // null on first boot is a valid response (PR #4 treats it as "no cursor → start fresh").
        $responder->success(['last_processed_message_id' => $this->bugRepo->findPipelineState($channelId)]);
    }
}
