<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Pagination\Paginator;
use Api\Repository\ApiGameRepository;
use Api\Response\JsonResponder;
use Api\Transformer\GameTransformer;

class GameListController implements ControllerInterface
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    private const ALLOWED_SORT_COLUMNS = ['game_date', 'visitor_score', 'home_score'];

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder): void
    {
        if (!isset($query['order'])) {
            $query['order'] = 'desc';
        }
        $paginator = new Paginator($query, 'game_date', self::ALLOWED_SORT_COLUMNS);
        $repo = new ApiGameRepository($this->db);
        $transformer = new GameTransformer();
        $etag = new ETagHandler();

        $filters = [];
        if (isset($query['season']) && $query['season'] !== '') {
            $filters['season'] = $query['season'];
        }
        if (isset($query['status']) && $query['status'] !== '') {
            $filters['status'] = $query['status'];
        }
        if (isset($query['team']) && $query['team'] !== '') {
            $filters['team'] = $query['team'];
        }
        if (isset($query['date']) && $query['date'] !== '') {
            $filters['date'] = $query['date'];
        }

        $total = $repo->countGames($filters);
        $rows = $repo->getGames($paginator, $filters);

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
