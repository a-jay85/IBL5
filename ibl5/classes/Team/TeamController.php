<?php

declare(strict_types=1);

namespace Team;

use Auth\Contracts\AuthServiceInterface;
use Http\HttpRequest;
use League\League;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Team\Contracts\TeamControllerInterface;
use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamViewInterface;

/**
 * @phpstan-import-type TeamPageData from Contracts\TeamServiceInterface
 *
 * @see TeamControllerInterface
 */
class TeamController implements TeamControllerInterface
{
    private \mysqli $db;
    private TeamServiceInterface $service;
    private TeamViewInterface $view;
    private TeamIdentityRepositoryInterface $commonRepo;
    private AuthServiceInterface $authService;
    private HttpRequest $request;

    public function __construct(\mysqli $db, TeamIdentityRepositoryInterface $commonRepo, AuthServiceInterface $authService, TeamServiceInterface $service, TeamViewInterface $view, HttpRequest $request)
    {
        $this->db = $db;
        $this->service = $service;
        $this->view = $view;
        $this->commonRepo = $commonRepo;
        $this->authService = $authService;
        $this->request = $request;
    }

    /**
     * Valid display modes for team page
     */
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

    /**
     * @see TeamControllerInterface::displayTeamPage()
     */
    public function displayTeamPage(int $teamid): void
    {
        // Validate and sanitize year parameter
        $yr = null;
        $rawYr = $this->request->request('yr');
        if (is_string($rawYr) && $rawYr !== '') {
            // Year should be a 4-digit year or a season range like "2024-25"
            if (preg_match('/^\d{4}(-\d{2})?$/', $rawYr) === 1) {
                $yr = $rawYr;
            }
            // Invalid year format is silently ignored (falls back to current season)
        }

        // Validate display parameter against whitelist
        $display = 'ratings';
        $rawDisplay = $this->request->request('display');
        if (is_string($rawDisplay)) {
            if (in_array($rawDisplay, self::VALID_DISPLAY_MODES, true)) {
                $display = $rawDisplay;
            }
            // Invalid display value is silently ignored (falls back to 'ratings')
        }

        // Validate split parameter when display=split
        $split = null;
        $rawSplit = $this->request->request('split');
        if ($display === 'split' && is_string($rawSplit)) {
            $splitRepo = new SplitStatsRepository($this->db);
            if (in_array($rawSplit, $splitRepo->getValidSplitKeys(), true)) {
                $split = $rawSplit;
            } else {
                // Invalid split key falls back to ratings
                $display = 'ratings';
            }
        } elseif ($display === 'split') {
            // display=split without a split key falls back to ratings
            $display = 'ratings';
        }

        \PageLayout\PageLayout::header();

        $userTeamName = '';
        $username = $this->authService->getUsername();
        if ($username !== null && $username !== '') {
            $userTeamName = $this->commonRepo->getTeamnameFromUsername($username) ?? '';
        }

        $responder = new \Api\Response\HtmlResponder();

        try {
            $pageData = $this->service->getTeamPageData($teamid, $yr, $display, $userTeamName, $split);
            $rawResult = $this->request->get('result');
            $pageData['extensionResult'] = is_string($rawResult) ? $rawResult : null;
            $rawMsg = $this->request->get('msg');
            $pageData['extensionMsg'] = is_string($rawMsg) ? $rawMsg : null;
        } catch (\RuntimeException $e) {
            $responder->html('<div class="ibl-alert ibl-alert--error">Team not found.</div>');
            \PageLayout\PageLayout::footer();
            return;
        }

        $responder->html($this->view->render($pageData));
        $responder->html('<script src="jslib/contract-hint.js"></script>');

        \PageLayout\PageLayout::footer();
    }

    /**
     * Display main menu
     */
    public function displayMenu(): void
    {
        \PageLayout\PageLayout::header();
        \PageLayout\PageLayout::footer();
    }
}
