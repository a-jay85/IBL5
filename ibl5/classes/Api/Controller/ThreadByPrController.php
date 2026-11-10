<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use BugPipeline\BugReportRepository;

class ThreadByPrController implements ControllerInterface
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
            $responder->error(403, 'forbidden', 'Only the pipeline bot can look up a thread by PR.');
            return;
        }

        // pr_number is a PR number (small int), NOT a snowflake — safe to coerce. Accept a JSON
        // int OR a numeric string: the GH-action → bot → PHP hop may stringify it via jq.
        $raw = $body['pr_number'] ?? null;
        if (!is_int($raw) && !(is_string($raw) && ctype_digit($raw))) {
            $responder->error(400, 'bad_request', 'Missing or invalid pr_number.');
            return;
        }
        $prNumber = (int) $raw;
        if ($prNumber <= 0) {
            $responder->error(400, 'bad_request', 'Missing or invalid pr_number.');
            return;
        }
        // null (no row for that PR, or no thread yet) is valid — PR #4's /prMerged no-ops on null.
        $responder->success(['thread_id' => $this->bugRepo->findThreadIdByPrNumber($prNumber)]);
    }
}
