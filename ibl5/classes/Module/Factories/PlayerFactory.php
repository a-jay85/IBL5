<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Player/index.php.
 *
 * index.php keeps the closures that RookieOptionController takes on
 * handleSubmission(), so the auth and CSRF checks stay at the boundary.
 */
final class PlayerFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function actionController(): \Player\PlayerActionController
    {
        return new \Player\PlayerActionController(
            $this->services->db(),
            $this->services->teamIdentity(),
            $this->services->salaryCap()
        );
    }

    public function rookieOptionController(): \RookieOption\RookieOptionController
    {
        $db = $this->services->db();

        return new \RookieOption\RookieOptionController(
            $db,
            $this->services->teamIdentity(),
            new \RookieOption\RookieOptionRepository($db),
            new \Topics\News\NewsRepository($db)
        );
    }

    public function pageController(): \Player\PlayerPageController
    {
        $db = $this->services->db();
        $commonRepo = $this->services->teamIdentity();

        return new \Player\PlayerPageController(
            $db,
            $commonRepo,
            new \Player\PlayerPageService($db, $commonRepo),
            $this->services->request()
        );
    }
}
