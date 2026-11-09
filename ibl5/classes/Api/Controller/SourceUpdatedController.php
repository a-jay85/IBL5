<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use BugPipeline\Contracts\BugReportClaimRepositoryInterface;

class SourceUpdatedController implements ControllerInterface
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
            $responder->error(403, 'forbidden', 'Only the pipeline bot can report a source edit.');
            return;
        }

        $messageId = is_string($body['message_id'] ?? null) ? $body['message_id'] : '';
        $text      = is_string($body['text'] ?? null) ? $body['text'] : null;
        if ($messageId === '' || $text === null) {
            $responder->error(400, 'bad_request', 'Missing message_id or text.');
            return;
        }
        $row = $this->bugRepo->findByOriginalMessageId($messageId);
        if ($row === null) {
            $responder->success(['matched' => false, 'changed' => false, 'revived' => false,
                                 'status' => null, 'thread_id' => null]);
            return;
        }
        // Embed hydration fires MessageUpdate with unchanged content — write nothing.
        if ($row['original_text'] === $text) {
            $responder->success(['matched' => true, 'changed' => false, 'revived' => false,
                                 'status' => $row['status'], 'thread_id' => $row['thread_id']]);
            return;
        }
        $this->bugRepo->updateSourceText($messageId, $text);
        $revived = in_array($row['status'], BugReportClaimRepositoryInterface::RECLASSIFIABLE_ON_EDIT, true);
        if ($revived) {
            $this->bugRepo->reviveForReclassify($messageId);
        }
        $responder->success(['matched' => true, 'changed' => true, 'revived' => $revived,
                             'status' => $revived ? 'queued' : $row['status'],
                             'thread_id' => $row['thread_id']]);
    }
}
