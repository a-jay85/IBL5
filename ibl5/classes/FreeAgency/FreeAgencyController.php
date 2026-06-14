<?php

declare(strict_types=1);

namespace FreeAgency;

use Auth\Contracts\AuthServiceInterface;
use Team\Team;
use Season\Season;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

class FreeAgencyController
{
    private \mysqli $db;
    private FreeAgencyRepository $repository;
    private FreeAgencyDemandRepository $demandRepository;
    private FreeAgencyService $service;
    private FreeAgencyView $view;
    private FreeAgencyProcessor $processor;
    private TeamIdentityRepositoryInterface $commonRepository;
    private \Utilities\NukeCompat $nukeCompat;
    private AuthServiceInterface $authService;

    /**
     * Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit').
     */
    private \Psr\Log\LoggerInterface $logger;
    /**
     * Optional injected Season. When null, methods fall back to new Season($db) (timing identical to today).
     */
    private ?Season $season = null;

    public function __construct(
        \mysqli $db,
        TeamIdentityRepositoryInterface $commonRepository,
        AuthServiceInterface $authService,
        ?\Utilities\NukeCompat $nukeCompat = null,
        ?\Psr\Log\LoggerInterface $logger = null,
        ?Season $season = null
    ) {
        $this->db = $db;
        $this->season = $season;
        $this->commonRepository = $commonRepository;
        $this->authService = $authService;
        $this->repository = new FreeAgencyRepository($db);
        $this->demandRepository = new FreeAgencyDemandRepository($db);
        $this->service = new FreeAgencyService($this->repository, $this->demandRepository, $db);
        $this->view = new FreeAgencyView($commonRepository);
        $this->processor = new FreeAgencyProcessor($db, $commonRepository);
        $this->nukeCompat = $nukeCompat ?? new \Utilities\NukeCompat();
        $this->logger = $logger ?? \Logging\LoggerFactory::getChannel('audit');
    }

    /**
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
        \PageLayout\PageLayout::header();

        $username = $this->authService->getUsername() ?? '';
        $teamName = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $team = Team::initialize($this->db, $teamName);
        $season = $this->season ?? new Season($this->db);

        $mainPageData = $this->service->getMainPageData($team, $season);
        $result = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->render($mainPageData, $result));

        \PageLayout\PageLayout::footer();
    }

    private function negotiate(int $pid): void
    {
        \PageLayout\PageLayout::header();

        $username = $this->authService->getUsername() ?? '';
        $userTeamName = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $teamid = $this->commonRepository->getTidFromTeamname($userTeamName) ?? 0;

        $team = Team::initialize($this->db, $teamid);
        $season = $this->season ?? new Season($this->db);

        $negotiationData = $this->service->getNegotiationData($pid, $team, $season);
        $negotiationData['team'] = $team;

        $error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;

        // On validation error redirect, restore submitted offer values into form
        if ($error !== null) {
            $submittedOffer = $this->extractSubmittedOfferFromQuery();
            if ($submittedOffer !== null) {
                $negotiationData['existingOffer'] = $submittedOffer;
            }
        }

        $formComponents = new FreeAgencyFormComponents($team->name, $negotiationData['player']);
        $negotiationView = new FreeAgencyOfferView($formComponents);
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($negotiationView->render($negotiationData, $error));

        \PageLayout\PageLayout::footer();
    }

    private function processOffer(): void
    {
        if (!\Security\CsrfGuard::validateSubmittedToken('free_agency')) {
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=csrf_error');
        }

        $username = $this->authService->getUsername() ?? '';
        $verifiedTeamName = $this->commonRepository->getTeamnameFromUsername($username);
        if ($verifiedTeamName === null || $verifiedTeamName === '' || $verifiedTeamName === \League\League::FREE_AGENTS_TEAM_NAME) {
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=error');
        }

        try {
            /** @var array<string, mixed> $postData */
            $postData = $_POST;
            $result = $this->processor->processOfferSubmission($postData, $verifiedTeamName);
        } catch (\Throwable $e) {
            $this->logger->error('fa_offer_error', [
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
            $offerParams = '';
            for ($i = 1; $i <= 6; $i++) {
                $raw = $postData['offeryear' . $i] ?? '';
                $value = is_numeric($raw) ? (int) $raw : 0;
                $offerParams .= '&offer' . $i . '=' . $value;
            }
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&pa=negotiate&pid=' . $pid . '&error=' . rawurlencode($result['message']) . $offerParams);
        }
    }

    /**
     * Extract submitted offer values from GET parameters (PRG validation error redirect)
     *
     * @return array<string, int>|null Offer values keyed as offer1-offer6, or null if not present
     */
    private function extractSubmittedOfferFromQuery(): ?array
    {
        if (!isset($_GET['offer1'])) {
            return null;
        }

        $offer = [];
        for ($i = 1; $i <= 6; $i++) {
            $key = 'offer' . $i;
            $raw = $_GET[$key] ?? null;
            $offer[$key] = is_numeric($raw) ? (int) $raw : 0;
        }
        return $offer;
    }

    private function deleteOffer(): void
    {
        if (!\Security\CsrfGuard::validateSubmittedToken('free_agency')) {
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=csrf_error');
        }

        $username = $this->authService->getUsername() ?? '';
        $verifiedTeamName = $this->commonRepository->getTeamnameFromUsername($username);
        if ($verifiedTeamName === null || $verifiedTeamName === '' || $verifiedTeamName === \League\League::FREE_AGENTS_TEAM_NAME) {
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=error');
        }

        try {
            $playerID = isset($_POST['playerID']) && is_numeric($_POST['playerID']) ? (int) $_POST['playerID'] : 0;
            $this->processor->deleteOffers($verifiedTeamName, $playerID);
        } catch (\Throwable $e) {
            $this->logger->error('fa_delete_error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=error');
        }

        \Utilities\HtmxHelper::redirect('modules.php?name=FreeAgency&result=deleted');
    }
}
