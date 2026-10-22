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
        $season = $this->services->season();
        $teamIdentity = $this->services->teamIdentity();

        $tradeFormRepo = new \Trading\TradeFormRepository($db);
        $tradeValidator = new \Trading\TradeValidator($db, $season);
        $teamModeRepo = new \TradeSuggestions\TeamModeRepository($db);

        $tradeSuggestionsService = new \TradeSuggestions\TradeSuggestionsService(
            new \TradeSuggestions\TeamModeService($teamModeRepo, new \TradeSuggestions\TeamModeGuesser()),
            $teamModeRepo,
            new \TradeSuggestions\SuggestionAssetRepository($db),
            new \ProjectedDraftOrder\ProjectedDraftOrderService(new \ProjectedDraftOrder\ProjectedDraftOrderRepository($db)),
            $tradeFormRepo,
            new \Settings\SettingsService($season),
            $season,
            new \TradeSuggestions\PickValuer(),
            new \TradeSuggestions\CandidateGenerator(),
            new \TradeSuggestions\TradeImpactEvaluator(),
            new \TradeSuggestions\SuggestionValidationFilter(
                $tradeValidator,
                new \Trading\TradeCapCalculator(
                    $teamIdentity,
                    new \Trading\BuyoutLedgerRepository($db),
                    $season,
                    $tradeValidator,
                ),
                $tradeFormRepo,
                $season,
            ),
            new \TradeSuggestions\SuggestionRanker(),
            new \Negotiation\NegotiationRepository($db, $this->services->salaryCap()),
        );

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
            $teamIdentity,
            new \LastSimRecap\LastSimRecapService(
                new \LastSimRecap\LastSimRecapRepository($db),
                $this->services->playerLookup(),
            ),
            new \LastSimRecap\LastSimRecapView(),
            new \Topics\News\NewsService($db),
            new \Topics\News\NewsView(),
            tradeSuggestionsService: $tradeSuggestionsService,
            tradeSuggestionsView: new \TradeSuggestions\TradeSuggestionsView(),
        );
    }
}
