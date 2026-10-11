<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the PlayerSearch module entry point.
 */
final class PlayerSearchFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function service(): \PlayerSearch\PlayerSearchService
    {
        $db = $this->services->db();
        $validator = new \PlayerSearch\PlayerSearchValidator();
        $repository = new \PlayerSearch\PlayerSearchRepository($db);
        $playerRepository = new \Player\PlayerRepository($db);

        return new \PlayerSearch\PlayerSearchService($validator, $repository, $playerRepository);
    }

    public function view(): \PlayerSearch\PlayerSearchView
    {
        return new \PlayerSearch\PlayerSearchView();
    }
}
