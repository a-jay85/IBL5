<?php

declare(strict_types=1);

namespace LeagueStarters;

use Auth\Contracts\AuthServiceInterface;
use League\League;
use Season\Season;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Team\Team;

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
    private TeamIdentityRepositoryInterface $commonRepo;
    private AuthServiceInterface $authService;

    public function __construct(\mysqli $db, TeamIdentityRepositoryInterface $commonRepo, AuthServiceInterface $authService)
    {
        $this->db = $db;
        $this->commonRepo = $commonRepo;
        $this->authService = $authService;
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

        $username = $this->authService->getUsername() ?? '';

        $userTeamName = $this->commonRepo->getTeamnameFromUsername($username);
        $userTeam = Team::initialize($this->db, $userTeamName ?? '');

        $season = new Season($this->db);
        $league = new League($this->db);
        $service = new LeagueStartersService($this->db, $league);
        $view = new LeagueStartersView('LeagueStarters');

        $startersByPosition = $service->getAllStartersByPosition();
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($view->renderTableContent($this->db, $season, $startersByPosition, $userTeam, $display));
    }
}
