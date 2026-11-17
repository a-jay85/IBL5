<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use SimRecap\SimPublishProgressRepository;

/**
 * POST sim-recap-publish/finish — IBLbot posted the last step of a sim.
 *
 * Body {sim, token}. 200 when the claimed row was finished, 409 when it was not
 * (foreign token or already finished), 400 on bad input.
 */
class SimRecapPublishFinishController implements ControllerInterface
{
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
            $responder->error(403, 'forbidden', 'Only the recap publisher can finish a sim.');
            return;
        }

        $sim   = $body['sim']   ?? null;
        $token = $body['token'] ?? null;
        if (!is_int($sim) || $sim < 1
            || !is_string($token) || preg_match(SimRecapPublishClaimController::TOKEN_PATTERN, $token) !== 1
        ) {
            $responder->error(400, 'bad_request', 'Invalid sim or token.');
            return;
        }

        if (!$this->progress->finish($sim, $token)) {
            $responder->error(409, 'conflict', 'Finish refused: foreign token or already finished.');
            return;
        }

        $responder->success(['sim' => $sim]);
    }
}
