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
        $season = $this->services->season();
        $leagueContext = $this->services->leagueContext();
        $tradeFormRepo = new \Trading\TradeFormRepository($db);
        $tradeValidator = new \Trading\TradeValidator($db, $season);
        $teamModeRepo = new \TradeSuggestions\TeamModeRepository($db, $leagueContext);

        $assetRepo = new \TradeSuggestions\SuggestionAssetRepository($db, $leagueContext);
        $draftOrder = new \ProjectedDraftOrder\ProjectedDraftOrderService(new \ProjectedDraftOrder\ProjectedDraftOrderRepository($db, $leagueContext));
        // One resolver for the loader, the provider and the service, so the
        // panel's mode line reads the run the provider just made.
        $modeResolver = new \TradeSuggestions\Engine\ModeResolver(
            $assetRepo,
            $draftOrder,
            $teamModeRepo,
            $tradeFormRepo,
            new \TradeSuggestions\TeamModeService($teamModeRepo, new \TradeSuggestions\TeamModeGuesser()),
            $season,
        );
        $snapshots = new \TradeSuggestions\Engine\CachedLeagueSnapshotProvider(
            new \TradeSuggestions\Engine\LeagueSnapshotLoader(
                new \TradeSuggestions\Projection\ProjectionPlayerRepository($db, $leagueContext),
                new \TradeSuggestions\Valuation\ValuationTeamFactorsRepository($db, $leagueContext),
                new \Standings\StandingsRepository($db, $leagueContext),
                $draftOrder,
                $assetRepo,
                $tradeFormRepo,
                $modeResolver,
                $season,
            ),
            new \Cache\DatabaseCache($db),
            new \TradeSuggestions\Engine\SnapshotKeyRepository($db, $leagueContext),
            $modeResolver,
            $season,
        );

        $tradeSuggestionsService = new \TradeSuggestions\TradeSuggestionsService(
            $snapshots,
            new \TradeSuggestions\Engine\CandidateGenerator([
                new \TradeSuggestions\Engine\Shape\OneForOne(),
                new \TradeSuggestions\Engine\Shape\TwoForOne(),
                new \TradeSuggestions\Engine\Shape\OneForTwo(),
                new \TradeSuggestions\Engine\Shape\StarForYoungAndPicks(),
                new \TradeSuggestions\Engine\Shape\DumpPaidWithPick(),
                new \TradeSuggestions\Engine\Shape\ExpiringForLong(),
                new \TradeSuggestions\Engine\Shape\PickSwap(),
                new \TradeSuggestions\Engine\Shape\ChangeOfScenery(),
            ]),
            \TradeSuggestions\Veto\VetoSet::standard(),
            new \TradeSuggestions\Engine\DealRanker(),
            new \TradeSuggestions\SuggestionValidationFilter(
                $tradeValidator,
                new \Trading\TradeCapCalculator(
                    $this->services->teamIdentity(),
                    new \Trading\BuyoutLedgerRepository($db, $leagueContext),
                    $season,
                    $tradeValidator,
                ),
                $tradeFormRepo,
                $season,
            ),
            $tradeFormRepo,
            new \Settings\SettingsService($season),
            $modeResolver,
            new \TradeSuggestions\Feedback\FeedbackRepository($db, $leagueContext),
            \TradeSuggestions\Feedback\PanelSessionState::current(),
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
            tradeSuggestionsService: $tradeSuggestionsService,
            tradeSuggestionsView: new \TradeSuggestions\TradeSuggestionsView(),
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
