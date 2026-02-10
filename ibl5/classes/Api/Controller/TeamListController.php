<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Pagination\Paginator;
use Api\Repository\ApiTeamRepository;
use Api\Response\JsonResponder;
use Api\Transformer\TeamTransformer;

class TeamListController implements ControllerInterface
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    private const ALLOWED_SORT_COLUMNS = ['team_name', 'team_city', 'owner_name', 'conference', 'division'];

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        $paginator = new Paginator($query, 'team_name', self::ALLOWED_SORT_COLUMNS);
        $repo = new ApiTeamRepository($this->db);
        $transformer = new TeamTransformer();
        $etag = new ETagHandler();

        $total = $repo->countTeams();
        $rows = $repo->getTeams($paginator);

        /** @phpstan-ignore argument.type (DB query guarantees array shape) */
        $data = array_map([$transformer, 'transform'], $rows);

        $tag = $etag->generateFromCollection($rows);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $responder->success($data, $paginator->getMeta($total), 200, $etag->getHeaders($tag));
    }
}
