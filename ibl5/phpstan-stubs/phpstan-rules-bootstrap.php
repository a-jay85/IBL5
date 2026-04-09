<?php

declare(strict_types=1);

// Preload every PHPStanRules\* class from the phpstan-rules/ directory so PHPStan's
// container can instantiate them even when running in a git worktree (where vendor/
// is symlinked to the main repo and composer's static classmap does not include new
// rule files added in the worktree). Idempotent — no effect if classes are already
// autoloaded.
$rulesDir = __DIR__ . '/../phpstan-rules';
if (is_dir($rulesDir)) {
    foreach (glob($rulesDir . '/*.php') ?: [] as $ruleFile) {
        require_once $ruleFile;
    }
}
