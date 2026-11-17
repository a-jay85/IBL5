<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use SimRecap\SimPublishProgressRepository;

/**
 * POST sim-recap-publish/progress — IBLbot reports one posted step.
 *
 * Body {sim, token, step, threadId}. 200 when the step advanced the claimed row, 409 when
 * it was refused (out-of-order step, foreign token, finished row), 400 on bad input.
 */
class SimRecapPublishProgressController implements ControllerInterface
{
    public const MAX_STEP = 10000;

    private const THREAD_ID_PATTERN = '/^\d{17,20}$/';

    private SimPublishProgressRepository $progress;

    private ServiceKeyGuard $serviceKeys;

    public function __construct(SimPublishProgressRepository $progress, ApiKeyRepository $apiKeys)
    {
        $this->progress = $progress;
        $this->serviceKeys = new ServiceKeyGuard($apiKeys);
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        if (!$this->serviceKeys->callerIsServiceKey()) {
            $responder->error(403, 'forbidden', 'Only the recap publisher can report progress.');
            return;
        }

        $sim      = $body['sim']      ?? null;
        $token    = $body['token']    ?? null;
        $step     = $body['step']     ?? null;
        $threadId = $body['threadId'] ?? null;

        // Snowflakes read as strings and are never (int)-cast.
        if (!is_int($sim) || $sim < 1
            || !is_string($token) || preg_match(SimRecapPublishClaimController::TOKEN_PATTERN, $token) !== 1
            || !is_int($step) || $step < 0 || $step > self::MAX_STEP
            || ($threadId !== null && (!is_string($threadId) || preg_match(self::THREAD_ID_PATTERN, $threadId) !== 1))
        ) {
            $responder->error(400, 'bad_request', 'Invalid sim, token, step, or threadId.');
            return;
        }

        if (!$this->progress->advance($sim, $token, $step, $threadId)) {
            $responder->error(409, 'conflict', 'Step refused: out of order, foreign token, or already finished.');
            return;
        }

        $responder->success(['sim' => $sim, 'step' => $step]);
    }
}
