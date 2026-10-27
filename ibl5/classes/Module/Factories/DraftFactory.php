<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Draft/index.php.
 *
 * The CSRF check lives in DraftController::submitSelection(); this factory only builds the graph.
 */
final class DraftFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \Draft\DraftController
    {
        $db = $this->services->db();
        $commonRepository = $this->services->teamIdentity();
        $season = $this->services->season();
        $validator = new \Draft\DraftValidator();
        $repository = new \Draft\DraftRepository($db, $commonRepository);
        $processor = new \Draft\DraftProcessor();
        $view = new \Draft\DraftView();
        $service = new \Draft\DraftService($db, $commonRepository, $season);

        return new \Draft\DraftController(
            $db,
            $commonRepository,
            $season,
            $validator,
            $repository,
            $processor,
            $view,
            $service,
            null,
            null,
            $this->services->nukeCompat()
        );
    }
}
