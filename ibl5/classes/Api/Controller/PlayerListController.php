<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Pagination\Paginator;
use Api\Repository\ApiPlayerRepository;
use Api\Response\JsonResponder;
use Api\Transformer\PlayerTransformer;

class PlayerListController implements ControllerInterface
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    private const ALLOWED_SORT_COLUMNS = ['name', 'age', 'position', 'points_per_game', 'experience'];

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder): void
    {
        $paginator = new Paginator($query, 'name', self::ALLOWED_SORT_COLUMNS);
        $repo = new ApiPlayerRepository($this->db);
        $transformer = new PlayerTransformer();
        $etag = new ETagHandler();

        $filters = [];
        if (isset($query['position']) && $query['position'] !== '') {
            $filters['position'] = $query['position'];
        }
        if (isset($query['team']) && $query['team'] !== '') {
            $filters['team'] = $query['team'];
        }
        if (isset($query['search']) && $query['search'] !== '') {
            $filters['search'] = $query['search'];
        }

        $total = $repo->countPlayers($filters);
        $rows = $repo->getPlayers($paginator, $filters);

        /** @phpstan-ignore argument.type (DB view guarantees array shape) */
        $data = array_map([$transformer, 'transform'], $rows);

        $tag = $etag->generateFromCollection($rows);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $responder->success($data, $paginator->getMeta($total), 200, $etag->getHeaders($tag));
    }
}
