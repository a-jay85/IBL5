<?php

declare(strict_types=1);

namespace Module\Factories;

/**
 * Composition for the FranchiseRecordBook module entry point.
 */
final class FranchiseRecordBookFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function apiHandler(): \FranchiseRecordBook\FranchiseRecordBookApiHandler
    {
        return new \FranchiseRecordBook\FranchiseRecordBookApiHandler($this->services->db());
    }

    public function service(): \FranchiseRecordBook\FranchiseRecordBookService
    {
        return new \FranchiseRecordBook\FranchiseRecordBookService(
            new \FranchiseRecordBook\FranchiseRecordBookRepository($this->services->db())
        );
    }

    public function view(): \FranchiseRecordBook\FranchiseRecordBookView
    {
        return new \FranchiseRecordBook\FranchiseRecordBookView();
    }
}
