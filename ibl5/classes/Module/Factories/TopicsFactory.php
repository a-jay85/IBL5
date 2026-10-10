<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Topics/index.php.
 */
final class TopicsFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    /**
     * The service owns the Topics and Search repositories.
     */
    public function service(string $prefix): \Topics\TopicsService
    {
        return new \Topics\TopicsService($this->services->db(), $prefix);
    }

    public function view(): \Topics\TopicsView
    {
        return new \Topics\TopicsView();
    }
}
