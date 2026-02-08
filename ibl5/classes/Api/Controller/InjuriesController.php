<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Repository\ApiInjuriesRepository;
use Api\Response\JsonResponder;
use Api\Transformer\InjuryTransformer;

class InjuriesController implements ControllerInterface
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder): void
    {
        $repo = new ApiInjuriesRepository($this->db);
        $transformer = new InjuryTransformer();
        $etag = new ETagHandler();

        $rows = $repo->getInjuredPlayers();

        /** @phpstan-ignore argument.type (DB row guarantees array shape) */
        $data = array_map([$transformer, 'transform'], $rows);

        $tag = $etag->generateFromCollection($rows);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $meta = ['total' => count($data)];
        $responder->success($data, $meta, 200, $etag->getHeaders($tag));
    }
}
