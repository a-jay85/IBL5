<?php

declare(strict_types=1);

namespace Module\Factories;

use Module\Contracts\ModuleFactoryInterface;
use Module\ModuleServices;

/**
 * Composition root for modules/Trading/index.php.
 *
 * maketradeoffer.php, accepttradeoffer.php and rejecttradeoffer.php are separate
 * ?file= entry points and stay outside this factory.
 */
final class TradingFactory implements ModuleFactoryInterface
{
    public function __construct(private readonly ModuleServices $services)
    {
    }

    public function controller(string $serverName): \Trading\TradingController
    {
        $db = $this->services->db();
        $teamIdentityRepo = $this->services->teamIdentity();
        $offerRepo = new \Trading\TradeOfferRepository($db, $serverName);
        $assetRepo = new \Trading\TradeAssetRepository($db);
        $formRepo = new \Trading\TradeFormRepository($db);
        $cashRepo = new \Trading\TradeCashRepository($db);
        $offerGrouper = new \Trading\TradeOfferGrouper($assetRepo, $cashRepo);
        $futureSalaryCalc = new \Trading\FutureSalaryCalculator();
        $service = new \Trading\TradingService($offerRepo, $formRepo, $teamIdentityRepo, $db, $offerGrouper, $futureSalaryCalc);
        $processor = new \Trading\TradeProcessor($db, $teamIdentityRepo, $serverName, $offerRepo, $assetRepo);
        $tradeOffer = new \Trading\TradeOffer($db, $teamIdentityRepo, $serverName);
        $view = new \Trading\TradingView();
        $nukeCompat = $this->services->nukeCompat();
        $validator = new \Trading\TradeValidator($db);
        $salaryCapRepo = $this->services->salaryCap();
        $season = $this->services->season();
        $executionService = new \Trading\TradeExecutionService(
            $offerRepo,
            $processor,
            $validator,
            $salaryCapRepo,
            $teamIdentityRepo,
            $cashRepo,
            $season
        );

        return new \Trading\TradingController(
            $service,
            $offerRepo,
            $tradeOffer,
            $view,
            $teamIdentityRepo,
            $nukeCompat,
            $db,
            $executionService,
            $this->services->auth()
        );
    }
}
