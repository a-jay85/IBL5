<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Repository\ApiPlayerStatsRepository;
use Api\Response\JsonResponder;
use Api\Transformer\PlayerStatsTransformer;

class PlayerStatsController implements ControllerInterface
{
    private ApiPlayerStatsRepository $repo;

    public function __construct(ApiPlayerStatsRepository $repo)
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
        $transformer = new PlayerStatsTransformer();
        $etag = new ETagHandler();

        $row = $repo->getCareerStats($uuid);
        if ($row === null) {
            $responder->error(404, 'not_found', 'Player not found.');
            return;
        }

        $updatedAt = is_string($row['updated_at'] ?? null) ? $row['updated_at'] : '';
        $tag = $etag->generate($updatedAt);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $data = $transformer->transformCareer($row);
        $responder->success($data, [], 200, $etag->getHeaders($tag));
    }
}
