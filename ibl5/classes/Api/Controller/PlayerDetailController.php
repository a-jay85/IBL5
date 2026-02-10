<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Repository\ApiPlayerRepository;
use Api\Response\JsonResponder;
use Api\Transformer\PlayerTransformer;

class PlayerDetailController implements ControllerInterface
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
        $repo = new ApiPlayerRepository($this->db);
        $transformer = new PlayerTransformer();
        $etag = new ETagHandler();

        $row = $repo->getPlayerByUuid($uuid);
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

        /** @phpstan-ignore argument.type (DB view guarantees array shape) */
        $data = $transformer->transformDetail($row);
        $responder->success($data, [], 200, $etag->getHeaders($tag));
    }
}
