<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;

class PipelineStateController implements ControllerInterface
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
            $responder->error(403, 'forbidden', 'Only the pipeline bot can read pipeline state.');
            return;
        }

        $channelId = $body['channel_id'] ?? null;
        if (!is_string($channelId) || $channelId === '') {
            $responder->error(400, 'bad_request', 'Missing channel_id.');
            return;
        }
        // null on first boot is a valid response (PR #4 treats it as "no cursor → start fresh").
        $responder->success(['last_processed_message_id' => $this->bugRepo->findPipelineState($channelId)]);
    }
}
