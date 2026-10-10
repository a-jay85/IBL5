<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/News/index.php.
 *
 * News/article.php and News/categories.php are separate ?file= entry points and
 * stay outside this factory.
 */
final class NewsFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    /**
     * The page config is built from legacy globals by the entry point, so it is
     * passed in rather than resolved here.
     */
    public function controller(\Topics\News\NewsPageConfig $config): \Topics\News\NewsController
    {
        $db = $this->services->db();

        return new \Topics\News\NewsController(
            $config,
            $this->services->auth(),
            $this->services->teamIdentity(),
            new \LastSimRecap\LastSimRecapService(
                new \LastSimRecap\LastSimRecapRepository($db),
                $this->services->playerLookup(),
            ),
            new \LastSimRecap\LastSimRecapView(),
            new \Topics\News\NewsService($db),
            new \Topics\News\NewsView(),
        );
    }
}
