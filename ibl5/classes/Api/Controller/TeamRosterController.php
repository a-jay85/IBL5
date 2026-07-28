<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Pagination\Paginator;
use Api\Repository\ApiPlayerRepository;
use Api\Response\JsonResponder;
use Api\Transformer\PlayerTransformer;

class TeamRosterController implements ControllerInterface
{
    private ApiPlayerRepository $repo;

    public function __construct(ApiPlayerRepository $repo)
    {
        $this->repo = $repo;
    }

    private const ALLOWED_SORT_COLUMNS = ['name', 'age', 'position', 'points_per_game', 'experience'];

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        $teamUuid = $params['uuid'] ?? '';
        $paginator = new Paginator($query, 'name', self::ALLOWED_SORT_COLUMNS);
        $repo = $this->repo;
        $transformer = new PlayerTransformer();
        $etag = new ETagHandler();

        $filters = ['team' => $teamUuid];

        $total = $repo->countPlayers($filters);
        if ($total === 0) {
            $responder->error(404, 'not_found', 'Team not found or has no players.');
            return;
        }

        $rows = $repo->getPlayers($paginator, $filters);
        $data = array_map([$transformer, 'transform'], $rows);

        $tag = $etag->generateFromCollection($rows);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $responder->success($data, $paginator->getMeta($total), 200, $etag->getHeaders($tag));
    }
}
