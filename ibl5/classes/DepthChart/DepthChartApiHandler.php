<?php

declare(strict_types=1);

namespace DepthChart;

use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * HTMX endpoint handler for depth chart entry tab switching
 *
 * Returns the table HTML for a given display mode without the full page layout.
 * Emits HX-Push-Url header so HTMX pushes the user-friendly URL.
 */
class DepthChartApiHandler
{
    private const VALID_DISPLAY_MODES = [
        'ratings',
        'total_s',
        'avg_s',
        'per36mins',
        'chunk',
        'playoffs',
        'contracts',
        'split',
    ];

    private \mysqli $db;
    private TeamIdentityRepositoryInterface $commonRepo;
    private \League\LeagueContext $leagueContext;

    public function __construct(\mysqli $db, TeamIdentityRepositoryInterface $commonRepo, \League\LeagueContext $leagueContext)
    {
        $this->db = $db;
        $this->commonRepo = $commonRepo;
        $this->leagueContext = $leagueContext;
    }

    public function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $teamid = isset($_GET['teamid']) && is_string($_GET['teamid']) ? (int) $_GET['teamid'] : 0;

        $display = 'ratings';
        if (isset($_GET['display']) && is_string($_GET['display'])) {
            $rawDisplay = $_GET['display'];
            if (in_array($rawDisplay, self::VALID_DISPLAY_MODES, true)) {
                $display = $rawDisplay;
            }
        }

        // Resolve the split parameter when display=split (shared allow-list gate)
        $splitRepo = new \Team\SplitStatsRepository($this->db);
        [$display, $split] = $splitRepo->resolveDisplayAndSplit($display, $_GET['split'] ?? null);

        // Emit HX-Push-Url so HTMX pushes the user-friendly URL
        $pushUrl = 'modules.php?name=DepthChartEntry&display=' . $display;
        if ($split !== null) {
            $pushUrl .= '&split=' . $split;
        }
        header('HX-Push-Url: ' . $pushUrl);

        $repository = new DepthChartRepository($this->db);
        $service = new DepthChartService();
        $view = new DepthChartView($this->leagueContext, $service);
        $teamRepository = new \Team\TeamRepository($this->db);
        $teamTableService = new \Team\TeamTableService($this->db, $teamRepository);
        $submissionHandler = new DepthChartSubmissionHandler($this->db, $this->commonRepo);
        $controller = new DepthChartController($this->db, $this->commonRepo, $repository, $service, $view, $teamTableService, $submissionHandler, \Http\HttpRequest::fromGlobals());
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($controller->getTableOutput($teamid, $display, $split));
    }
}
