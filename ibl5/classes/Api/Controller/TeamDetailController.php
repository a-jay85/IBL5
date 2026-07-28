<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Repository\ApiTeamRepository;
use Api\Response\JsonResponder;
use Api\Transformer\TeamTransformer;

class TeamDetailController implements ControllerInterface
{
    private ApiTeamRepository $repo;

    public function __construct(ApiTeamRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        $uuid = $params['uuid'] ?? '';
        $repo = $this->repo;
        $transformer = new TeamTransformer();
        $etag = new ETagHandler();

        $row = $repo->getTeamByUuid($uuid);
        if ($row === null) {
            $responder->error(404, 'not_found', 'Team not found.');
            return;
        }

        $tag = $etag->generate('team-' . $uuid);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $data = $transformer->transformDetail($row);
        $responder->success($data, [], 200, $etag->getHeaders($tag));
    }
}
