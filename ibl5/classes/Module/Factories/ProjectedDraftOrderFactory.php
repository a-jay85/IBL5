<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the ProjectedDraftOrder module entry point.
 *
 * The admin gate lives in index.php above every factory call; this class only
 * builds the object graph.
 */
final class ProjectedDraftOrderFactory implements \Module\Contracts\ModuleFactoryInterface
{
    private ?\ProjectedDraftOrder\ProjectedDraftOrderRepository $repository = null;

    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function season(): \Season\Season
    {
        return $this->services->season();
    }

    public function repository(): \ProjectedDraftOrder\ProjectedDraftOrderRepository
    {
        if ($this->repository === null) {
            $this->repository = new \ProjectedDraftOrder\ProjectedDraftOrderRepository($this->services->db());
        }

        return $this->repository;
    }

    public function service(): \ProjectedDraftOrder\ProjectedDraftOrderService
    {
        return new \ProjectedDraftOrder\ProjectedDraftOrderService($this->repository());
    }

    public function view(): \ProjectedDraftOrder\ProjectedDraftOrderView
    {
        return new \ProjectedDraftOrder\ProjectedDraftOrderView();
    }
}
