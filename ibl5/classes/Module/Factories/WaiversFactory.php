<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Waivers/index.php.
 */
final class WaiversFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \Waivers\WaiversController
    {
        $db = $this->services->db();
        $teamIdentityRepo = $this->services->teamIdentity();

        $repo = new \Waivers\WaiversRepository($db);
        $validator = new \Waivers\WaiversValidator();
        $newsService = new \Topics\News\NewsRepository($db);
        $processor = new \Waivers\WaiversProcessor(
            $repo,
            $teamIdentityRepo,
            $this->services->playerLookup(),
            $validator,
            $newsService,
            $db
        );
        $view = new \Waivers\WaiversView();
        $teamQueryRepo = new \Team\TeamQueryRepository($db);
        $service = new \Waivers\WaiversService($teamIdentityRepo, $processor, $view, $teamQueryRepo, $db);

        return new \Waivers\WaiversController(
            $service,
            $processor,
            $view,
            $teamIdentityRepo,
            $this->services->salaryCap(),
            $this->services->nukeCompat(),
            $db,
            $this->services->auth(),
            $this->services->request()
        );
    }
}
