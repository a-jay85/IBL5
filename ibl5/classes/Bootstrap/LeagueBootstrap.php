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
        // Hydrate session from cookie if not set
        if (!isset($_SESSION['current_league']) && isset($_COOKIE[LeagueContext::COOKIE_NAME])) {
            $cookieLeague = $_COOKIE[LeagueContext::COOKIE_NAME];
            if (in_array($cookieLeague, [LeagueContext::LEAGUE_IBL, LeagueContext::LEAGUE_OLYMPICS], true)) {
                $_SESSION['current_league'] = $cookieLeague;
            }
        }

        // Initialize global LeagueContext instance
        $leagueContext = new LeagueContext();

        // Persist league selection when user switches via URL parameter
        if (isset($_GET['league']) && in_array($_GET['league'], [LeagueContext::LEAGUE_IBL, LeagueContext::LEAGUE_OLYMPICS], true)) {
            $leagueContext->setLeague($_GET['league']);
        }

        $container->set('leagueContext', $leagueContext);
        $GLOBALS['leagueContext'] = $leagueContext;
    }
}
