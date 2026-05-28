<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;
use League\LeagueContext;

/**
 * League context hydration from cookie/GET parameter.
 *
 * Extracted from mainfile.php lines 150-164.
 */
class LeagueBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        // Initialize global LeagueContext instance.
        // Persistence across requests is read directly from the cookie by
        // LeagueContext::getCurrentLeague(); we intentionally do NOT hydrate
        // $_SESSION from the cookie. The E2E harness shares one server-side
        // session across all authenticated workers, so any session write of the
        // current league leaks one test's Olympics switch into every other
        // concurrent test.
        $leagueContext = new LeagueContext();

        // Persist league selection when user switches via URL parameter
        if (isset($_GET['league']) && in_array($_GET['league'], [LeagueContext::LEAGUE_IBL, LeagueContext::LEAGUE_OLYMPICS], true)) {
            $leagueContext->setLeague($_GET['league']);
        }

        $container->set('leagueContext', $leagueContext);
        $GLOBALS['leagueContext'] = $leagueContext;
        \BaseMysqliRepository::setSharedLeagueContext($leagueContext);
    }
}
