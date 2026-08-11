<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;

class SourceDeletedController implements ControllerInterface
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
