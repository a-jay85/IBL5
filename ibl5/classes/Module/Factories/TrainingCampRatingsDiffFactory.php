<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/TrainingCampRatingsDiff/index.php.
 *
 * index.php resolves this factory only after its loginbox gate.
 */
final class TrainingCampRatingsDiffFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function service(): \TrainingCampRatingsDiff\TrainingCampRatingsDiffService
    {
        $repository = new \TrainingCampRatingsDiff\TrainingCampRatingsDiffRepository($this->services->db());

        return new \TrainingCampRatingsDiff\TrainingCampRatingsDiffService(
            $repository,
            $this->services->season()->endingYear
        );
    }

    public function view(): \TrainingCampRatingsDiff\TrainingCampRatingsDiffView
    {
        return new \TrainingCampRatingsDiff\TrainingCampRatingsDiffView();
    }
}
