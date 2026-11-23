<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/News/index.php.
 *
 * News/article.php and News/categories.php compose through service() and view().
 */
final class NewsFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    /**
     * The entry point reads the legacy config globals and passes them in, because
     * a factory never reads globals.
     */
    public function controller(
        int $storyHome,
        int $userNews,
        int $articleComm,
        string $siteName,
        int $multilingual,
        string $currentLang,
        string $tableBgcolor1 = '#EEEEEE',
        string $tableBgcolor2 = '#CCCCCC',
    ): \Topics\News\NewsController {
        $db = $this->services->db();

        return new \Topics\News\NewsController(
            new \Topics\News\NewsPageConfig(
                storyHome: $storyHome,
                userNews: $userNews,
                articleComm: $articleComm,
                siteName: $siteName,
                multilingual: $multilingual,
                currentLang: $currentLang,
                tableBgcolor1: $tableBgcolor1,
                tableBgcolor2: $tableBgcolor2,
            ),
            $this->services->auth(),
            $this->services->teamIdentity(),
            new \LastSimRecap\LastSimRecapService(
                new \LastSimRecap\LastSimRecapRepository($db, $this->services->leagueContext()),
                $this->services->playerLookup(),
            ),
            new \LastSimRecap\LastSimRecapView(),
            new \Topics\News\NewsService($db),
            new \Topics\News\NewsView(),
            onThisDayService: \OnThisDay\OnThisDayFactory::createService($db, $this->services->leagueContext()),
            onThisDayView: new \OnThisDay\OnThisDayView(),
        );
    }

    public function service(): \Topics\News\NewsService
    {
        return new \Topics\News\NewsService($this->services->db());
    }

    public function view(): \Topics\News\NewsView
    {
        return new \Topics\News\NewsView();
    }
}
