<?php

declare(strict_types=1);

namespace Module\Factories;

use ActivityTracker\ActivityTrackerRepository;
use ActivityTracker\ActivityTrackerView;

/**
 * Composition for the ActivityTracker module entry point.
 */
final class ActivityTrackerFactory implements \Module\Contracts\ModuleFactoryInterface
{
    public function __construct(private readonly \Module\ModuleServices $services)
    {
    }

    public function repository(): ActivityTrackerRepository
    {
        return new ActivityTrackerRepository($this->services->db());
    }

    public function view(): ActivityTrackerView
    {
        return new ActivityTrackerView();
    }
}
