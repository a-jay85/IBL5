<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Response\JsonResponder;
use BugPipeline\AttachmentInputValidator;
use BugPipeline\BugReportRepository;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

class EnqueueController implements ControllerInterface
{
    private BugReportRepository $bugRepo;
    private TeamIdentityRepositoryInterface $teamRepo;
    private AttachmentInputValidator $attachmentValidator;

    public function __construct(
        BugReportRepository $bugRepo,
        TeamIdentityRepositoryInterface $teamRepo,
        AttachmentInputValidator $attachmentValidator
    ) {
        $this->bugRepo = $bugRepo;
        $this->teamRepo = $teamRepo;
        $this->attachmentValidator = $attachmentValidator;
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

        // Attachments are best-effort: validate + persist below the authz gate, but never let a
        // storage hiccup fail an enqueue that already succeeded. A dropped attachment degrades to a
        // text-only report. `attachments_stored` is the count of validated survivors (INSERT IGNORE
        // dedups on replay, so it is an accepted-for-storage count, not a rows-inserted count).
        $stored = 0;
        try {
            $rawAttachments = $body['attachments'] ?? [];
            if (is_array($rawAttachments)) {
                $rejectLog = null;
                $valid = $this->attachmentValidator->validateAll($rawAttachments, $rejectLog);
                if ($rejectLog !== null) {
                    error_log("enqueue attachments rejected for report {$reportId}: {$rejectLog}");
                }
                // Count validated survivors, not inserted rows: the field is frozen to the
                // pre-persist count so a degraded insert still reports what the caller sent.
                $stored = count($valid);
                if ($valid !== []) {
                    $this->bugRepo->insertAttachments($reportId, $valid);
                }
            }
        } catch (\Throwable $e) {
            error_log("enqueue attachment persistence failed for report {$reportId}: " . $e->getMessage());
        }

        $responder->success(['authorized' => true, 'report_id' => $reportId, 'attachments_stored' => $stored]);
    }
}
