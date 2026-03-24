<?php

declare(strict_types=1);

namespace LeagueStarters;

use League\League;
use Season\Season;
use Team\Team;
use Utilities\NukeCompat;

/**
 * HTMX endpoint handler for league starters display tab switching.
 *
 * Returns the position tables HTML for a given display mode without the full page layout.
 * Emits HX-Push-Url for browser history.
 */
class LeagueStartersApiHandler
{
    private const VALID_DISPLAY_MODES = ['ratings', 'total_s', 'avg_s', 'per36mins'];

    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $display = 'ratings';
        if (isset($_GET['display']) && is_string($_GET['display'])) {
            $rawDisplay = $_GET['display'];
            if (in_array($rawDisplay, self::VALID_DISPLAY_MODES, true)) {
                $display = $rawDisplay;
            }
        }

        header('HX-Push-Url: modules.php?name=LeagueStarters&display=' . $display);

        // Resolve user team from session cookie (same pattern as full-page flow)
        /** @var mixed $user */
        global $user;
        $nuke = new NukeCompat();
        $cookieData = $nuke->cookieDecode($user);
        $username = $cookieData[1] ?? '';

        $commonRepo = new \Services\CommonMysqliRepository($this->db);
        $userTeamName = $commonRepo->getTeamnameFromUsername($username);
        $userTeam = Team::initialize($this->db, $userTeamName ?? '');

        $season = new Season($this->db);
        $league = new League($this->db);
        $service = new LeagueStartersService($this->db, $league);
        $view = new LeagueStartersView($this->db, $season, 'LeagueStarters');

        $startersByPosition = $service->getAllStartersByPosition();
        echo $view->renderTableContent($startersByPosition, $userTeam, $display);
    }
}
