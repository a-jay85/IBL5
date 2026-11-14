<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the Contracts module entry point.
 */
final class ContractsFactory implements \Module\Contracts\ModuleFactoryInterface
{
    /** The tab nav wrapper needs no shared services. */
    public function __construct(\Module\ModuleServices $services)
    {
    }

    public function view(): \Contracts\ContractsView
    {
        return new \Contracts\ContractsView();
    }
}
