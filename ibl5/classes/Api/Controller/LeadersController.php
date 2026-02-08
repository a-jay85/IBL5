<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Cache\ETagHandler;
use Api\Contracts\ControllerInterface;
use Api\Pagination\Paginator;
use Api\Repository\ApiLeadersRepository;
use Api\Response\JsonResponder;
use Api\Transformer\LeaderTransformer;

class LeadersController implements ControllerInterface
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    private const ALLOWED_CATEGORIES = ['ppg', 'rpg', 'apg', 'spg', 'bpg', 'fgp', 'ftp', 'tgp', 'qa'];

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder): void
    {
        // Leaders use category-based sort, not column sort from Paginator
        // We still use Paginator for page/per_page only
        $paginator = new Paginator($query, 'ppg', self::ALLOWED_CATEGORIES);
        $repo = new ApiLeadersRepository($this->db);
        $transformer = new LeaderTransformer();
        $etag = new ETagHandler();

        $filters = [];
        if (isset($query['season']) && $query['season'] !== '') {
            $filters['season'] = $query['season'];
        }
        $category = $this->normalizeCategory($query['category'] ?? 'ppg');
        $filters['category'] = $category;

        if (isset($query['min_games']) && $query['min_games'] !== '') {
            $filters['min_games'] = $query['min_games'];
        }

        $total = $repo->countLeaders($filters);
        $rows = $repo->getLeaders($paginator, $filters);

        /** @phpstan-ignore argument.type (DB row guarantees array shape) */
        $data = array_map([$transformer, 'transform'], $rows);

        $tag = $etag->generateFromCollection($rows);
        if ($etag->matches($tag)) {
            $responder->notModified();
            return;
        }

        $meta = $paginator->getMeta($total);
        $meta['category'] = $category;
        if (isset($filters['season'])) {
            $meta['season'] = (int) $filters['season'];
        }

        $responder->success($data, $meta, 200, $etag->getHeaders($tag));
    }

    /**
     * Normalize category to valid value, defaulting to 'ppg'.
     */
    private function normalizeCategory(string $category): string
    {
        $lower = strtolower($category);
        if (in_array($lower, self::ALLOWED_CATEGORIES, true)) {
            return $lower;
        }

        return 'ppg';
    }
}
