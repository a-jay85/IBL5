<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyControllerInterface;
use Team\Team;
use Season\Season;

/**
 * @see FreeAgencyControllerInterface
 */
class FreeAgencyController implements FreeAgencyControllerInterface
{
    private \mysqli $db;
    private FreeAgencyRepository $repository;
    private FreeAgencyDemandRepository $demandRepository;
    private FreeAgencyService $service;
    private FreeAgencyView $view;
    private FreeAgencyProcessor $processor;
    private \Services\CommonMysqliRepository $commonRepository;
    private \Utilities\NukeCompat $nukeCompat;

    public function __construct(\mysqli $db, ?\Utilities\NukeCompat $nukeCompat = null)
    {
        $this->db = $db;
        $this->repository = new FreeAgencyRepository($db);
        $this->demandRepository = new FreeAgencyDemandRepository($db);
        $this->service = new FreeAgencyService($this->repository, $this->demandRepository, $db);
        $this->view = new FreeAgencyView($db);
        $this->processor = new FreeAgencyProcessor($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
        $this->nukeCompat = $nukeCompat ?? new \Utilities\NukeCompat();
    }

    /**
     * @see FreeAgencyControllerInterface::handleRequest()
     */
    public function handleRequest(mixed $user, string $action, int $pid): void
    {
        if (!$this->nukeCompat->isUser($user)) {
            $this->nukeCompat->loginBox();
            return;
        }

        match ($action) {
            'negotiate' => $this->negotiate($pid),
            'processoffer' => $this->processOffer(),
            'deleteoffer' => $this->deleteOffer(),
            default => $this->display(),
        };
    }

    private function display(): void
    {
        /** @var array<int, string> $cookie */
        global $cookie;
        $username = (string) ($cookie[1] ?? '');
        $teamName = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $team = Team::initialize($this->db, $teamName);
        $season = new Season($this->db);

        \PageLayout\PageLayout::header();

        $mainPageData = $this->service->getMainPageData($team, $season);
        $result = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
        echo $this->view->render($mainPageData, $result);

        \PageLayout\PageLayout::footer();
    }

    private function negotiate(int $pid): void
    {
        /** @var array<int, string> $cookie */
        global $cookie;

        \PageLayout\PageLayout::header();

        $username = (string) ($cookie[1] ?? '');
        $userTeamName = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $teamID = $this->commonRepository->getTidFromTeamname($userTeamName) ?? 0;

        $team = Team::initialize($this->db, $teamID);
        $season = new Season($this->db);

        $negotiationData = $this->service->getNegotiationData($pid, $team, $season);
        $negotiationData['team'] = $team;

        $formComponents = new FreeAgencyFormComponents($team->name, $negotiationData['player']);
        $negotiationView = new FreeAgencyNegotiationView($formComponents);
        $error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
        echo $negotiationView->render($negotiationData, $error);

        \PageLayout\PageLayout::footer();
    }

    private function processOffer(): void
    {
        if (!\Utilities\CsrfGuard::validateSubmittedToken('free_agency')) {
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=csrf_error');
        }

        try {
            /** @var array<string, mixed> $postData */
            $postData = $_POST;
            $result = $this->processor->processOfferSubmission($postData);
        } catch (\Throwable $e) {
            \Logging\LoggerFactory::getChannel('audit')->error('fa_offer_error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=error');
        }

        $pid = $result['playerID'];

        if ($result['success']) {
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=offer_success');
        } elseif ($result['type'] === 'already_signed') {
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=already_signed');
        } else {
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&pa=negotiate&pid=' . $pid . '&error=' . rawurlencode($result['message']));
        }
    }

    private function deleteOffer(): void
    {
        if (!\Utilities\CsrfGuard::validateSubmittedToken('free_agency')) {
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=csrf_error');
        }

        try {
            $playerID = isset($_POST['playerID']) && is_numeric($_POST['playerID']) ? (int) $_POST['playerID'] : 0;
            $teamName = isset($_POST['teamname']) && is_string($_POST['teamname']) ? $_POST['teamname'] : '';
            $this->processor->deleteOffers($teamName, $playerID);
        } catch (\Throwable $e) {
            \Logging\LoggerFactory::getChannel('audit')->error('fa_delete_error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=error');
        }

        \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=deleted');
    }
}
