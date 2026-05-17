<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (is_link(__DIR__ . '/../vendor')) {
    $worktreeClasses = realpath(__DIR__ . '/../classes');
    $worktreeTests = realpath(__DIR__ . '/../tests');
    $worktreePhpstanRules = realpath(__DIR__ . '/../phpstan-rules');
    if ($worktreeClasses !== false || $worktreeTests !== false || $worktreePhpstanRules !== false) {
        spl_autoload_register(static function (string $class) use ($worktreeClasses, $worktreeTests, $worktreePhpstanRules): void {
            if ($worktreeTests !== false && str_starts_with($class, 'Tests\\')) {
                $relative = substr($class, 6);
                $file = $worktreeTests . '/' . str_replace('\\', '/', $relative) . '.php';
                if (file_exists($file)) {
                    require $file;
                    return;
                }
            }
            if ($worktreePhpstanRules !== false && str_starts_with($class, 'PHPStanRules\\')) {
                $relative = substr($class, 13);
                $file = $worktreePhpstanRules . '/' . str_replace('\\', '/', $relative) . '.php';
                if (file_exists($file)) {
                    require $file;
                    return;
                }
            }
            if ($worktreeClasses !== false) {
                $file = $worktreeClasses . '/' . str_replace('\\', '/', $class) . '.php';
                if (file_exists($file)) {
                    require $file;
                    return;
                }
            }
        }, true, true);
    }
}

\Bootstrap\TestApplicationFactory::build(__DIR__ . '/..')->boot();
