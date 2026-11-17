<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Middleware\ServiceKeyGuard;
use Api\Repository\ApiKeyRepository;
use Api\Response\JsonResponder;
use SimRecap\PostingSwitch;
use SimRecap\PublishScriptBuilder;
use SimRecap\PublishScriptUnavailable;
use SimRecap\SimPublishProgressRepository;

/**
 * POST sim-recap-publish/claim-next — IBLbot asks for the next sim to post.
 *
 * Body {token}. 200 {sim, threadId, lastStep, script} when a sim is claimed, 204 when
 * nothing is claimable or the commissioner switch is off (checked before claiming, so no
 * attempt is spent), 422 when the claimed sim has nothing to publish (the row stays
 * claimed, so its attempts reach the cap after repeated sweeps), 400 on a bad token.
 * Service-key callers only: the response carries the whole publisher script.
 */
class SimRecapPublishClaimController implements ControllerInterface
{
    /** A UUID, lowercase hex, the shape IBLbot generates for each sweep. */
    public const TOKEN_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private SimPublishProgressRepository $progress;

    private PublishScriptBuilder $scriptBuilder;

    private ServiceKeyGuard $serviceKeys;

    private PostingSwitch $postingSwitch;

    public function __construct(
        SimPublishProgressRepository $progress,
        PublishScriptBuilder $scriptBuilder,
        ApiKeyRepository $apiKeys,
        PostingSwitch $postingSwitch
    ) {
        $this->progress = $progress;
        $this->scriptBuilder = $scriptBuilder;
        $this->postingSwitch = $postingSwitch;
        $this->serviceKeys = new ServiceKeyGuard($apiKeys);
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        // Runs before any body field is read, so a rejected request reads and writes nothing.
        if (!$this->serviceKeys->callerIsServiceKey()) {
            $responder->error(403, 'forbidden', 'Only the recap publisher can claim a sim.');
            return;
        }

        $token = $body['token'] ?? null;
        if (!is_string($token) || preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            $responder->error(400, 'bad_request', 'Missing or malformed token.');
            return;
        }

        if (!$this->postingSwitch->isOn()) {
            // Commissioner switch is off: nothing to do. Gate before claimNext() so no attempt is burned.
            $responder->raw([], 204);
            return;
        }

        $claim = $this->progress->claimNext($token);
        if ($claim === null) {
            $responder->raw([], 204);
            return;
        }

        try {
            $script = $this->scriptBuilder->build($claim['sim'], $claim['title_override']);
        } catch (PublishScriptUnavailable) {
            $responder->error(422, 'script_unavailable', 'The claimed sim has nothing to publish.');
            return;
        }

        $responder->success([
            'sim'      => $claim['sim'],
            'threadId' => $claim['thread_id'],
            'lastStep' => $claim['last_step'],
            'script'   => $script,
        ]);
    }
}
