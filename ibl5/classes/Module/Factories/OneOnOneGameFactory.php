<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/OneOnOneGame/index.php.
 */
final class OneOnOneGameFactory implements ModuleFactoryInterface
{
    private ?\OneOnOneGame\OneOnOneGameRepository $repository = null;

    public function __construct(private readonly ModuleServices $services)
    {
    }

    /**
     * One instance per factory: the entry point reads it directly and the service wraps it.
     */
    public function repository(): \OneOnOneGame\OneOnOneGameRepository
    {
        if ($this->repository === null) {
            $this->repository = new \OneOnOneGame\OneOnOneGameRepository($this->services->db());
        }

        return $this->repository;
    }

    public function service(): \OneOnOneGame\OneOnOneGameService
    {
        return new \OneOnOneGame\OneOnOneGameService(
            $this->repository(),
            new \OneOnOneGame\OneOnOneGameEngine()
        );
    }

    public function view(): \OneOnOneGame\OneOnOneGameView
    {
        return new \OneOnOneGame\OneOnOneGameView();
    }
}
