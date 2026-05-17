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

        $tradeControllers = [
            \Api\Controller\TradeAcceptController::class,
            \Api\Controller\TradeDeclineController::class,
        ];

        if (in_array($controllerClass, $tradeControllers, true)) {
            $commonRepo = new \Repositories\TeamIdentityRepository($db);
            return new $controllerClass($db, $commonRepo);
        }

        return new $controllerClass($db);
    };
});

$app->boot();
if ($app->isTerminated()) {
    exit;
}
