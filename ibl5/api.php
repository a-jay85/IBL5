<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// In git worktrees, vendor/ is symlinked to the main repo. Prepend the worktree's
// classes/ directory so modified files are used at runtime.
if (is_link(__DIR__ . '/vendor')) {
    $worktreeClasses = realpath(__DIR__ . '/classes');
    if ($worktreeClasses !== false) {
        spl_autoload_register(static function (string $class) use ($worktreeClasses): void {
            $file = $worktreeClasses . '/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }, true, true);
    }
}

$app = \Bootstrap\ApiApplicationFactory::build(__DIR__);

// Register controller factory in the entry point (composition root)
$app->getContainer()->set('api.controllerFactory', static function (): \Closure {
    return static function (string $controllerClass): \Api\Contracts\ControllerInterface {
        /** @var \mysqli $db */
        $db = $GLOBALS['mysqli_db'];

        if ($controllerClass === \Api\Controller\HealthController::class) {
            return new \Api\Controller\HealthController(new \Api\Repository\HealthRepository($db));
        }

        // Group A — ApiGameRepository
        if (in_array($controllerClass, [
            \Api\Controller\GameBoxscoreController::class,
            \Api\Controller\GameDetailController::class,
            \Api\Controller\GameListController::class,
        ], true)) {
            return new $controllerClass(new \Api\Repository\ApiGameRepository($db));
        }

        if ($controllerClass === \Api\Controller\InjuriesController::class) {
            return new \Api\Controller\InjuriesController(new \Api\Repository\ApiInjuriesRepository($db));
        }
        if ($controllerClass === \Api\Controller\LeadersController::class) {
            return new \Api\Controller\LeadersController(new \Api\Repository\ApiLeadersRepository($db));
        }

        // Group A — ApiPlayerRepository
        if (in_array($controllerClass, [
            \Api\Controller\PlayerDetailController::class,
            \Api\Controller\PlayerExportController::class,
            \Api\Controller\PlayerListController::class,
            \Api\Controller\TeamRosterController::class,
        ], true)) {
            return new $controllerClass(new \Api\Repository\ApiPlayerRepository($db));
        }

        // Group A — ApiPlayerStatsRepository
        if (in_array($controllerClass, [
            \Api\Controller\PlayerHistoryController::class,
            \Api\Controller\PlayerStatsController::class,
        ], true)) {
            return new $controllerClass(new \Api\Repository\ApiPlayerStatsRepository($db));
        }

        if ($controllerClass === \Api\Controller\StandingsController::class) {
            return new \Api\Controller\StandingsController(new \Api\Repository\ApiStandingsRepository($db));
        }

        // Group A — ApiTeamRepository
        if (in_array($controllerClass, [
            \Api\Controller\TeamDetailController::class,
            \Api\Controller\TeamListController::class,
        ], true)) {
            return new $controllerClass(new \Api\Repository\ApiTeamRepository($db));
        }

        // Group B — SeasonController
        if ($controllerClass === \Api\Controller\SeasonController::class) {
            return new \Api\Controller\SeasonController(new \Season\Season($db));
        }

        // Group C — TradeAcceptController
        if ($controllerClass === \Api\Controller\TradeAcceptController::class) {
            $commonRepo = new \Repositories\TeamIdentityRepository($db);
            return new \Api\Controller\TradeAcceptController(
                $commonRepo,
                new \Trading\TradeOfferRepository($db, ''),
                new \Trading\TradeProcessor($db, $commonRepo)
            );
        }

        // Group C — TradeDeclineController
        if ($controllerClass === \Api\Controller\TradeDeclineController::class) {
            $commonRepo = new \Repositories\TeamIdentityRepository($db);
            return new \Api\Controller\TradeDeclineController(
                $commonRepo,
                new \Trading\TradeOfferRepository($db, '')
            );
        }

        if ($controllerClass === \Api\Controller\EnqueueController::class) {
            return new \Api\Controller\EnqueueController(
                new \BugPipeline\BugReportRepository($db),
                new \Repositories\TeamIdentityRepository($db),
                new \BugPipeline\AttachmentInputValidator()
            );
        }

        $bugPipelineControllers = [
            \Api\Controller\ThreadReplyController::class,
            \Api\Controller\ReactionController::class,
            \Api\Controller\LastSeenController::class,
            \Api\Controller\PipelineStateController::class,
            \Api\Controller\ThreadByPrController::class,
            \Api\Controller\SourceUpdatedController::class,
            \Api\Controller\SourceDeletedController::class,
        ];
        if (in_array($controllerClass, $bugPipelineControllers, true)) {
            return new $controllerClass(new \BugPipeline\BugReportRepository($db));
        }

        return new $controllerClass($db);
    };
});

$app->boot();
if ($app->isTerminated()) {
    exit;
}
