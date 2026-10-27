<?php

declare(strict_types=1);

namespace Module\Factories;

use AwardHistory\AwardHistoryRepository;
use AwardHistory\AwardHistoryService;
use AwardHistory\AwardHistoryValidator;
use AwardHistory\AwardHistoryView;

/**
 * Composition for the AwardHistory module entry point.
 */
final class AwardHistoryFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function service(): AwardHistoryService
    {
        $validator = new AwardHistoryValidator();
        $repository = new AwardHistoryRepository($this->services->db());

        return new AwardHistoryService($validator, $repository);
    }

    public function view(AwardHistoryService $service): AwardHistoryView
    {
        return new AwardHistoryView($service);
    }
}
