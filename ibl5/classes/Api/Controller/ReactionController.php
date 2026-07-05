<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use Discord\Discord;

class ReactionController implements ControllerInterface
{
    private const APPROVAL_EMOJI = '✅';

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
        $messageId = $body['message_id'] ?? null;
        $emoji     = $body['emoji']      ?? null;
        $reactorId = $body['reactor_id'] ?? null;
        if (!is_string($messageId) || $messageId === ''
            || !is_string($emoji) || $emoji === ''
            || !is_string($reactorId) || $reactorId === ''
        ) {
            $responder->error(400, 'bad_request', 'Missing message_id, emoji, or reactor_id.');
            return;
        }

        // Gate 1 (emoji) AND Gate 2 (approver identity, string compare). Empty approver
        // (unconfigured) never equals a real snowflake => fail-closed, no advance.
        $approverId = Discord::getBugPipelineApproverDiscordId();
        $isApproval = $emoji === self::APPROVAL_EMOJI && $reactorId === $approverId;

        // advanceOnApproval adds Gate 3 (status='awaiting_ajay') and is a no-op otherwise.
        $advanced = $isApproval ? $this->bugRepo->advanceOnApproval($messageId) : false;
        $responder->success(['advanced' => $advanced]);
    }
}
