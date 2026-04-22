<?php

declare(strict_types=1);

namespace Waivers;

use Player\Player;
use Team\Team;
use Season\Season;
use UI\Components\TableViewSwitcher;
use Waivers\Contracts\WaiversControllerInterface;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\Contracts\WaiversViewInterface;

/**
 * @see WaiversControllerInterface
 *
 * @phpstan-import-type UserRow from \Services\CommonMysqliRepository
 * @phpstan-import-type WaiverFormData from WaiversServiceInterface
 */
class WaiversController implements WaiversControllerInterface
{
    public const WAIVER_POOL_MOVES_CATEGORY_ID = 1;

    private WaiversServiceInterface $service;
    private WaiversProcessorInterface $processor;
    private WaiversViewInterface $view;
    private \Services\CommonMysqliRepository $commonRepository;
    private \Utilities\NukeCompat $nukeCompat;
    private \mysqli $db;

    public function __construct(
        WaiversServiceInterface $service,
        WaiversProcessorInterface $processor,
        WaiversViewInterface $view,
        \Services\CommonMysqliRepository $commonRepository,
        \Utilities\NukeCompat $nukeCompat,
        \mysqli $db
    ) {
        $this->service = $service;
        $this->processor = $processor;
        $this->view = $view;
        $this->commonRepository = $commonRepository;
        $this->nukeCompat = $nukeCompat;
        $this->db = $db;
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

        $season = new Season($this->db);

        if (!$season->areWaiversAllowed()) {
            \PageLayout\PageLayout::header();
            echo $this->view->renderWaiversClosed();
            \PageLayout\PageLayout::footer();
            return;
        }

        /** @var array<int, string> $cookie */
        global $cookie;
        $username = is_string($cookie[1] ?? null) ? $cookie[1] : '';
        $this->executeWaiverOperation($username, $action);
    }

    /**
     * @see WaiversControllerInterface::executeWaiverOperation()
     */
    public function executeWaiverOperation(string $username, string $action): void
    {
        $userInfo = $this->commonRepository->getUserByUsername($username);

        if ($userInfo === null) {
            $this->nukeCompat->loginBox();
            return;
        }

        // PRG: Process POST submission, then redirect
        if (isset($_POST['Action']) && ($_POST['Action'] === 'add' || $_POST['Action'] === 'waive')) {
            $postAction = is_string($_POST['Action']) ? $_POST['Action'] : 'add';

            if (!\Utilities\CsrfGuard::validateSubmittedToken('waivers')) {
                \Utilities\HtmxHelper::redirect('modules.php?name=Waivers&action=' . rawurlencode($postAction) . '&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
            }

            try {
                /** @var array<string, string> $postData */
                $postData = $_POST;
                $result = $this->processWaiverSubmission($postData);
            } catch (\Throwable $e) {
                \Logging\LoggerFactory::getChannel('audit')->error('waiver_submission_error', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $result = ['success' => false, 'error' => 'An unexpected error occurred. Please try again.'];
            }

            if ($result['success'] === true) {
                $resultParam = $result['result'] ?? '';
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
     * @param array<string, string> $postData
     * @return array{success: bool, result?: string, error?: string}
     */
    private function processWaiverSubmission(array $postData): array
    {
        $teamName = $postData['Team_Name'] ?? '';
        $action = $postData['Action'] ?? '';
        $playerID = isset($postData['Player_ID']) ? (int) $postData['Player_ID'] : null;
        $rosterSlots = isset($postData['rosterslots']) ? (int) $postData['rosterslots'] : 0;
        $healthyRosterSlots = isset($postData['healthyrosterslots']) ? (int) $postData['healthyrosterslots'] : 0;

        if ($teamName === '' || !in_array($action, ['add', 'waive'], true)) {
            return ['success' => false, 'error' => 'Invalid submission data.'];
        }

        $totalSalary = $this->commonRepository->getTeamTotalSalary($teamName);

        if ($action === 'waive') {
            return $this->processor->processDrop($playerID, $teamName, $rosterSlots, $totalSalary);
        }

        return $this->processor->processAdd($playerID, $teamName, $healthyRosterSlots, $totalSalary);
    }

    /**
     * @param UserRow $userInfo
     */
    private function displayWaiverForm(array $userInfo, string $action): void
    {
        $display = isset($_REQUEST['display']) && is_string($_REQUEST['display']) ? $_REQUEST['display'] : 'ratings';
        $username = is_string($userInfo['username'] ?? null) ? $userInfo['username'] : '';

        $resultParam = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
        $errorParam = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;

        $formData = $this->service->getWaiverFormData($username, $action);

        \PageLayout\PageLayout::header();

        echo $this->view->renderWaiverForm(
            $formData['team']->name,
            $formData['team']->teamid,
            $action,
            $formData['players'],
            $formData['openRosterSpots'],
            $formData['healthyOpenRosterSpots'],
            $resultParam,
            $errorParam
        );

        $tabDefinitions = [
            'ratings' => 'Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
            'per36mins' => 'Per 36 Minutes',
        ];

        $baseUrl = 'modules.php?name=Waivers&action=' . $action;
        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $formData['styleTeam']->color1, $formData['styleTeam']->color2);
        $tableHtml = $this->renderTableForDisplay($display, $formData['tableResult'], $formData['styleTeam'], $formData['season']);
        echo $switcher->wrap($tableHtml);

        \PageLayout\PageLayout::footer();
    }

    /**
     * @param array<int, array<string, mixed>|Player> $result
     */
    private function renderTableForDisplay(string $display, array $result, Team $team, Season $season): string
    {
        return match ($display) {
            'total_s' => \UI\Tables\SeasonTotals::render($this->db, $result, $team, ''),
            'avg_s' => \UI\Tables\SeasonAverages::render($this->db, $result, $team, ''),
            'per36mins' => \UI\Tables\Per36Minutes::render($this->db, $result, $team, ''),
            default => \UI\Tables\Ratings::render($this->db, $result, $team, '', $season),
        };
    }
}
