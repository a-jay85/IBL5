<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;

class SourceDeletedController implements ControllerInterface
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
            $responder->error(403, 'forbidden', 'Only the pipeline bot can report a source deletion.');
            return;
        }

        $messageId = is_string($body['message_id'] ?? null) ? $body['message_id'] : '';
        if ($messageId === '') {
            $responder->error(400, 'bad_request', 'Missing message_id.');
            return;
        }
        $row = $this->bugRepo->findByOriginalMessageId($messageId);
        if ($row === null) {
            $responder->success(['matched' => false, 'dropped' => false, 'status' => null, 'thread_id' => null]);
            return;
        }
        $responder->success([
            'matched'   => true,
            'dropped'   => $this->bugRepo->markSourceDeleted($messageId),
            'status'    => $row['status'],
            'thread_id' => $row['thread_id'],
        ]);
    }
}
