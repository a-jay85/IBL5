<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Repository\ApiPlayerStatsRepository;
use Api\Response\JsonResponder;
use Api\Transformer\PlayerStatsTransformer;

class PlayerHistoryController implements ControllerInterface
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        $uuid = $params['uuid'] ?? '';
        $repo = new ApiPlayerStatsRepository($this->db);
        $transformer = new PlayerStatsTransformer();
        $etag = new ETagHandler();

        $rows = $repo->getSeasonHistory($uuid);
        if ($rows === []) {
            $responder->error(404, 'not_found', 'Player not found or has no history.');
            return;
        }

        /** @phpstan-ignore argument.type (DB row guarantees array shape) */
        $data = array_map([$transformer, 'transformSeason'], $rows);

        $tag = $etag->generateFromCollection($rows);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $meta = ['total' => count($data)];
        $responder->success($data, $meta, 200, $etag->getHeaders($tag));
    }
}
