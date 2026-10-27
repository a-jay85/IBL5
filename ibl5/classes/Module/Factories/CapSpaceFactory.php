<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the CapSpace module entry point.
 */
final class CapSpaceFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function season(): \Season\Season
    {
        return $this->services->season();
    }

    public function service(): \CapSpace\CapSpaceService
    {
        $db = $this->services->db();

        return new \CapSpace\CapSpaceService(new \CapSpace\CapSpaceRepository($db), $db);
    }

    public function view(): \CapSpace\CapSpaceView
    {
        return new \CapSpace\CapSpaceView();
    }
}
