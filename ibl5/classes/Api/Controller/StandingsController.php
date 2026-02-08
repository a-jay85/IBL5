<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Repository\ApiStandingsRepository;
use Api\Response\JsonResponder;
use Api\Transformer\StandingsTransformer;

class StandingsController implements ControllerInterface
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
        $conference = $this->normalizeConference($params['conference'] ?? null);
        $repo = new ApiStandingsRepository($this->db);
        $transformer = new StandingsTransformer();
        $etag = new ETagHandler();

        $rows = $repo->getStandings($conference);
        /** @phpstan-ignore argument.type (DB view guarantees array shape) */
        $data = array_map([$transformer, 'transform'], $rows);

        $tag = $etag->generateFromCollection($rows);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $meta = ['total' => count($data)];
        if ($conference !== null) {
            $meta['conference'] = $conference;
        }

        $responder->success($data, $meta, 200, $etag->getHeaders($tag));
    }

    /**
     * Normalize short conference names to DB values.
     * Accepts "East", "Eastern", "West", "Western".
     */
    private function normalizeConference(?string $conference): ?string
    {
        if ($conference === null) {
            return null;
        }

        $lower = strtolower($conference);
        if ($lower === 'east' || $lower === 'eastern') {
            return 'Eastern';
        }
        if ($lower === 'west' || $lower === 'western') {
            return 'Western';
        }

        return $conference;
    }
}
