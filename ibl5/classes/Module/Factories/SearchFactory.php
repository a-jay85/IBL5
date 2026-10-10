<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Search/index.php.
 *
 * The table prefix is a legacy global read at the boundary; index.php passes it in.
 */
final class SearchFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function repository(string $tablePrefix): \Search\SearchRepository
    {
        return new \Search\SearchRepository($this->services->db(), $tablePrefix);
    }

    public function view(): \Search\SearchView
    {
        return new \Search\SearchView();
    }
}
