<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

class EnqueueController implements ControllerInterface
{
    private BugReportRepository $bugRepo;
    private TeamIdentityRepositoryInterface $teamRepo;

    public function __construct(BugReportRepository $bugRepo, TeamIdentityRepositoryInterface $teamRepo)
    {
        $this->bugRepo = $bugRepo;
        $this->teamRepo = $teamRepo;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        if ($body === null) {
            $responder->error(400, 'bad_request', 'Missing request body.');
            return;
        }

        // Snowflakes read as strings — never (int)-cast. Text required non-empty.
        $authorId  = $body['author_id']  ?? null;
        $channelId = $body['channel_id'] ?? null;
        $messageId = $body['message_id'] ?? null;
        $text      = $body['text']       ?? null;

        if (!is_string($authorId) || $authorId === ''
            || !is_string($channelId) || $channelId === ''
            || !is_string($messageId) || $messageId === ''
            || !is_string($text) || $text === ''
        ) {
            $responder->error(400, 'bad_request', 'Missing author_id, channel_id, message_id, or text.');
            return;
        }

        // Authz: only known GMs enqueue. Snowflake compared as a string in the repo.
        if (!$this->teamRepo->isKnownDiscordID($authorId)) {
            // Unauthorized: no report row, but STILL advance the channel watermark so the
            // message isn't re-fetched forever. Monotonic upsert — never regresses.
            $this->bugRepo->upsertPipelineState($channelId, $messageId);
            $responder->success(['authorized' => false, 'report_id' => null]);
            return;
        }

        // Authorized: INSERT + watermark advance run atomically & idempotently inside the repo
        // (crash-safe, replay-safe — see enqueueAuthorizedAndAdvance).
        $reportId = $this->bugRepo->enqueueAuthorizedAndAdvance($authorId, $channelId, $messageId, $text);
        $responder->success(['authorized' => true, 'report_id' => $reportId]);
    }
}
