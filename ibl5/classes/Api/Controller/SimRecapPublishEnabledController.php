<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use SimRecap\PostingSwitch;

/**
 * POST sim-recap-publish/enabled: IBLbot asks whether the commissioner switch allows posting.
 *
 * The body is ignored. 200 {enabled: bool}, 403 for a non-service key. A database failure
 * surfaces as a 500, which IBLbot treats as off.
 */
class SimRecapPublishEnabledController implements ControllerInterface
{
    private PostingSwitch $postingSwitch;

    private ServiceKeyGuard $serviceKeys;

    public function __construct(PostingSwitch $postingSwitch, ApiKeyRepository $apiKeys)
    {
        $this->postingSwitch = $postingSwitch;
        $this->serviceKeys = new ServiceKeyGuard($apiKeys);
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        if (!$this->serviceKeys->callerIsServiceKey()) {
            $responder->error(403, 'forbidden', 'Only the recap publisher can read the posting switch.');
            return;
        }

        $responder->success(['enabled' => $this->postingSwitch->isOn()]);
    }
}
