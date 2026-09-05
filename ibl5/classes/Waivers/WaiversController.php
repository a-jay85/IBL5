<?php

declare(strict_types=1);

namespace Waivers;

use Player\Player;
use Team\Team;
use Season\Season;
use UI\Components\TableViewSwitcher;
use Auth\Contracts\AuthServiceInterface;
use Waivers\Contracts\WaiversControllerInterface;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\Contracts\WaiversViewInterface;
use EventLog\EventLogger;
use Http\HttpRequest;

/**
 * @see WaiversControllerInterface
 *
 * @phpstan-import-type UserRow from \Repositories\Contracts\TeamIdentityRepositoryInterface
 * @phpstan-import-type WaiverFormData from WaiversServiceInterface
 */
class WaiversController implements WaiversControllerInterface
{
    public const WAIVER_POOL_MOVES_CATEGORY_ID = 1;

    private WaiversServiceInterface $service;
    private \Waivers\Contracts\WaiversSubmissionServiceInterface $submissionService;
    private WaiversViewInterface $view;
    private \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepo;
    private \Utilities\NukeCompat $nukeCompat;
    private \mysqli $db;
    private AuthServiceInterface $authService;
    private HttpRequest $request;

    /**
     * Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit').
     */
    private \Psr\Log\LoggerInterface $logger;
    /**
     * Optional injected Season. When null, methods fall back to new Season($db) (timing identical to today).
     */
    private ?Season $season = null;

    public function __construct(
        WaiversServiceInterface $service,
        WaiversProcessorInterface $processor,
        WaiversViewInterface $view,
        \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepo,
        \Repositories\Contracts\SalaryCapRepositoryInterface $salaryCapRepo,
        \Utilities\NukeCompat $nukeCompat,
        \mysqli $db,
        AuthServiceInterface $authService,
        HttpRequest $request,
        ?\Psr\Log\LoggerInterface $logger = null,
        ?Season $season = null,
        ?\Waivers\Contracts\WaiversSubmissionServiceInterface $submissionService = null
    ) {
        $this->service = $service;
        $this->view = $view;
        $this->teamIdentityRepo = $teamIdentityRepo;
        $this->nukeCompat = $nukeCompat;
        $this->db = $db;
        $this->authService = $authService;
        $this->request = $request;
        $this->logger = $logger ?? \Logging\LoggerFactory::getChannel('audit');
        $this->season = $season;
        $this->submissionService = $submissionService
            ?? new \Waivers\WaiversSubmissionService($processor, $salaryCapRepo);
    }

    /**
     * @see WaiversControllerInterface::handleWaiverRequest()
     */
    public function handleWaiverRequest($user, string $action): void
    {
        if (!$this->nukeCompat->isUser($user)) {
            $this->nukeCompat->loginBox();
            return;
        }

        $season = $this->season ?? new Season($this->db);

        if (!$season->areWaiversAllowed()) {
            \PageLayout\PageLayout::header();
            $responder = new \Api\Response\HtmlResponder();
            $responder->html($this->view->renderWaiversClosed());
            \PageLayout\PageLayout::footer();
            return;
        }

        $username = $this->authService->getUsername() ?? '';
        $this->executeWaiverOperation($username, $action);
    }

    /**
     * @see WaiversControllerInterface::executeWaiverOperation()
     */
    public function executeWaiverOperation(string $username, string $action): void
    {
        $userInfo = $this->teamIdentityRepo->getUserByUsername($username);

        if ($userInfo === null) {
            $this->nukeCompat->loginBox();
            return;
        }

        // PRG: Process POST submission, then redirect
        $action_ = $this->request->post('Action'); // underscore avoids shadowing $action param
        if ($action_ === 'add' || $action_ === 'waive') {
            $postAction = $action_;

            if (!\Security\CsrfGuard::validateSubmittedToken('waivers')) {
                \Utilities\HtmxHelper::redirect('modules.php?name=Waivers&action=' . rawurlencode($postAction) . '&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
            }

            $verifiedTeamName = $this->teamIdentityRepo->getTeamnameFromUsername($username);
            if ($verifiedTeamName === null || $verifiedTeamName === '' || $verifiedTeamName === \League\League::FREE_AGENTS_TEAM_NAME) {
                $verifiedTeamName = null;
            }

            try {
                $postData = $this->request->allPost();
                $result = $this->submissionService->submit($postData, $verifiedTeamName);
            } catch (\Throwable $e) {
                $this->logger->error('waiver_submission_error', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $result = ['success' => false, 'error' => 'An unexpected error occurred. Please try again.'];
            }

            if ($result['success'] === true) {
                $resultParam = $result['result'] ?? '';
                EventLogger::setAction($postAction === 'add' ? 'waiver_claim_submitted' : 'waiver_release_submitted');
                \Utilities\HtmxHelper::redirect('modules.php?name=Waivers&action=' . rawurlencode($postAction) . '&result=' . rawurlencode($resultParam));
            } else {
                $errorParam = $result['error'] ?? '';
                \Utilities\HtmxHelper::redirect('modules.php?name=Waivers&action=' . rawurlencode($postAction) . '&error=' . rawurlencode($errorParam));
            }
        }

        // Display the waiver form (GET request)
        $this->displayWaiverForm($userInfo, $action);
    }

    /**
     * @param UserRow $userInfo
     */
    private function displayWaiverForm(array $userInfo, string $action): void
    {
        $rawDisplay = $this->request->request('display');
        $display = is_string($rawDisplay) ? $rawDisplay : 'ratings';
        $username = is_string($userInfo['username'] ?? null) ? $userInfo['username'] : '';

        $rawResult = $this->request->get('result');
        $resultParam = is_string($rawResult) ? $rawResult : null;
        $rawError = $this->request->get('error');
        $errorParam = is_string($rawError) ? $rawError : null;

        $formData = $this->service->getWaiverFormData($username, $action);

        \PageLayout\PageLayout::header();

        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->renderWaiverForm(
            $formData['team']->name,
            $formData['team']->teamid,
            $action,
            $formData['players'],
            $formData['openRosterSpots'],
            $formData['healthyOpenRosterSpots'],
            $resultParam,
            $errorParam
        ));

        $tabDefinitions = [
            'ratings' => 'Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
            'per36mins' => 'Per 36 Minutes',
        ];

        $baseUrl = 'modules.php?name=Waivers&action=' . $action;
        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $formData['styleTeam']->color1, $formData['styleTeam']->color2);
        $tableHtml = $this->renderTableForDisplay($display, $formData['tableResult'], $formData['styleTeam'], $formData['season']);
        $responder->html($switcher->wrap($tableHtml));

        \PageLayout\PageLayout::footer();
    }

    /**
     * @param array<int, array<string, mixed>|Player> $result
     */
    private function renderTableForDisplay(string $display, array $result, Team $team, Season $season): string
    {
        return match ($display) {
            'total_s' => \BasketballStats\Tables\SeasonTotals::render($this->db, $result, $team, ''),
            'avg_s' => \BasketballStats\Tables\SeasonAverages::render($this->db, $result, $team, ''),
            'per36mins' => \BasketballStats\Tables\Per36Minutes::render($this->db, $result, $team, ''),
            default => \UI\Tables\Ratings::render($this->db, $result, $team, '', $season),
        };
    }
}
