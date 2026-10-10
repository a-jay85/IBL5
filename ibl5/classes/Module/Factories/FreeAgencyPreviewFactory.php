<?php

declare(strict_types=1);

namespace Module\Factories;

use FreeAgencyPreview\FreeAgencyPreviewRepository;
use FreeAgencyPreview\FreeAgencyPreviewService;
use FreeAgencyPreview\FreeAgencyPreviewView;

/**
 * Composition for the FreeAgencyPreview module entry point.
 */
final class FreeAgencyPreviewFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function season(): \Season\Season
    {
        return $this->services->season();
    }

    public function service(): FreeAgencyPreviewService
    {
        return new FreeAgencyPreviewService(new FreeAgencyPreviewRepository($this->services->db()));
    }

    public function view(): FreeAgencyPreviewView
    {
        return new FreeAgencyPreviewView();
    }
}
