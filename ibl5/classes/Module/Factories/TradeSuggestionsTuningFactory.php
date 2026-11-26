<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/TradeSuggestionsTuning/index.php.
 *
 * The engine graph copies NewsFactory::controller(), so the page reads the same
 * cached snapshot (same key and TTL) as the GM panel.
 */
final class TradeSuggestionsTuningFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(): \TradeSuggestions\Tuning\TuningController
    {
        $db = $this->services->db();
        $season = $this->services->season();
        $tradeFormRepo = new \Trading\TradeFormRepository($db);
        $tradeValidator = new \Trading\TradeValidator($db, $season);
        $teamModeRepo = new \TradeSuggestions\TeamModeRepository($db);

        $assetRepo = new \TradeSuggestions\SuggestionAssetRepository($db);
        $draftOrder = new \ProjectedDraftOrder\ProjectedDraftOrderService(new \ProjectedDraftOrder\ProjectedDraftOrderRepository($db));
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
                new \TradeSuggestions\Projection\ProjectionPlayerRepository($db),
                new \TradeSuggestions\Valuation\ValuationTeamFactorsRepository($db),
                new \Standings\StandingsRepository($db),
                $draftOrder,
                $assetRepo,
                $tradeFormRepo,
                $modeResolver,
                $season,
            ),
            new \Cache\DatabaseCache($db),
            new \TradeSuggestions\Engine\SnapshotKeyRepository($db),
            $modeResolver,
            $season,
        );

        $assembler = new \TradeSuggestions\Tuning\TuningAssembler(
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
                    new \Trading\BuyoutLedgerRepository($db),
                    $season,
                    $tradeValidator,
                ),
                $tradeFormRepo,
                $season,
            ),
            $tradeFormRepo,
            new \TradeSuggestions\Tuning\TuningFeedbackRepository($db),
        );

        return new \TradeSuggestions\Tuning\TuningController($assembler, new \TradeSuggestions\Tuning\TuningView());
    }
}
