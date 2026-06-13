<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradingControllerInterface;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradeProcessorInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeOfferInterface;
use Trading\Contracts\TradingViewInterface;

/**
 * @see TradingControllerInterface
 */
class TradingController implements TradingControllerInterface
{
    private TradingServiceInterface $service;
    private TradeProcessorInterface $processor;
    private TradeOfferRepositoryInterface $offerRepository;
    private TradeOfferInterface $tradeOffer;
    private TradingViewInterface $view;
    private \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepo;
    private \Utilities\NukeCompat $nukeCompat;
    private \mysqli $db;

    public function __construct(
        TradingServiceInterface $service,
        TradeProcessorInterface $processor,
        TradeOfferRepositoryInterface $offerRepository,
        TradeOfferInterface $tradeOffer,
        TradingViewInterface $view,
        \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepo,
        \Utilities\NukeCompat $nukeCompat,
        \mysqli $db
    ) {
        $this->service = $service;
        $this->processor = $processor;
        $this->offerRepository = $offerRepository;
        $this->tradeOffer = $tradeOffer;
        $this->view = $view;
        $this->teamIdentityRepo = $teamIdentityRepo;
        $this->nukeCompat = $nukeCompat;
        $this->db = $db;
    }

    /**
     * @see TradingControllerInterface::handleTradeReview()
     */
    public function handleTradeReview(mixed $user): void
    {
        if (!$this->nukeCompat->isUser($user)) {
            $this->nukeCompat->loginBox();
            return;
        }

        $season = new \Season\Season($this->db);

        if (!$season->areTradesAllowed()) {
            \PageLayout\PageLayout::header();
            $responder = new \Api\Response\HtmlResponder();
            $responder->html($this->view->renderTradesClosed($season));
            \PageLayout\PageLayout::footer();
            return;
        }

        $cookie = $this->nukeCompat->cookieDecode($user);
        $username = is_string($cookie[1] ?? null) ? $cookie[1] : '';
        $this->renderTradeReview($username);
    }

    /**
     * @see TradingControllerInterface::handleTradeOffer()
     */
    public function handleTradeOffer(mixed $user, ?string $partner): void
    {
        if (!$this->nukeCompat->isUser($user)) {
            $this->nukeCompat->loginBox();
            return;
        }

        $decoded = $this->nukeCompat->cookieDecode($user);
        $username = is_string($decoded[1] ?? null) ? $decoded[1] : '';
        $this->renderTradeOffer($username, $partner);
    }

    /**
     * @see TradingControllerInterface::handleRosterPreviewApi()
     */
    public function handleRosterPreviewApi(mixed $user): void
    {
        if (!$this->nukeCompat->isUser($user)) {
            header('Content-Type: application/json; charset=utf-8');
            $responder = new \Api\Response\HtmlResponder();
            $responder->json(['html' => '']);
            return;
        }

        $decoded = $this->nukeCompat->cookieDecode($user);
        $loggedInUsername = is_string($decoded[1] ?? null) ? $decoded[1] : '';
        $loggedInTeamID = 0;

        if ($loggedInUsername !== '') {
            $loggedInTeamName = $this->teamIdentityRepo->getTeamnameFromUsername($loggedInUsername);
            if ($loggedInTeamName !== null) {
                $loggedInTeamID = $this->teamIdentityRepo->getTidFromTeamname($loggedInTeamName) ?? 0;
            }
        }

        $tradeAssetRepo = new TradeAssetRepository($this->db);
        $handler = new TradeRosterPreviewApiHandler($this->db, $tradeAssetRepo, $loggedInTeamID);
        $handler->handle();
    }

    /**
     * @see TradingControllerInterface::submitTradeOffer()
     *
     * @param array<string, mixed> $post
     */
    public function submitTradeOffer(array $post): void
    {
        if (!\Security\CsrfGuard::validateSubmittedToken('trade_offer')) {
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
        }

        $rawFieldsCounter = $post['fieldsCounter'] ?? 0;
        $fieldsCounter = (is_numeric($rawFieldsCounter) ? (int) $rawFieldsCounter : 0) + 1;
        $offeringTeam = is_string($post['offeringTeam'] ?? null) ? $post['offeringTeam'] : '';
        $listeningTeam = is_string($post['listeningTeam'] ?? null) ? $post['listeningTeam'] : '';
        $rawSwitch = $post['switchCounter'] ?? 0;
        $switchCounter = is_numeric($rawSwitch) ? (int) $rawSwitch : 0;

        /** @var array<int, int> $userSendsCash */
        $userSendsCash = [];
        /** @var array<int, int> $partnerSendsCash */
        $partnerSendsCash = [];
        /** @var array<int, string|null> $check */
        $check = [];
        /** @var array<int, string> $contract */
        $contract = [];
        /** @var array<int, string> $index */
        $index = [];
        /** @var array<int, string> $type */
        $type = [];

        $i = 0;
        while ($i < 7) {
            $cashUser = $post['userSendsCash' . $i] ?? 0;
            $cashPartner = $post['partnerSendsCash' . $i] ?? 0;
            $userSendsCash[$i] = is_numeric($cashUser) ? (int) $cashUser : 0;
            $partnerSendsCash[$i] = is_numeric($cashPartner) ? (int) $cashPartner : 0;
            $i++;
        }

        for ($j = 0; $j < $fieldsCounter; $j++) {
            $rawCheck = $post['check' . $j] ?? null;
            $check[$j] = is_string($rawCheck) ? $rawCheck : null;
            $rawContract = $post['contract' . $j] ?? '0';
            $contract[$j] = is_string($rawContract) || is_int($rawContract) ? (string) $rawContract : '0';
            $rawIndex = $post['index' . $j] ?? '0';
            $index[$j] = is_string($rawIndex) || is_int($rawIndex) ? (string) $rawIndex : '0';
            $rawType = $post['type' . $j] ?? '0';
            $type[$j] = is_string($rawType) || is_int($rawType) ? (string) $rawType : '0';
        }

        $tradeData = [
            'offeringTeam' => $offeringTeam,
            'listeningTeam' => $listeningTeam,
            'switchCounter' => $switchCounter,
            'fieldsCounter' => $fieldsCounter,
            'userSendsCash' => $userSendsCash,
            'partnerSendsCash' => $partnerSendsCash,
            'check' => $check,
            'contract' => $contract,
            'index' => $index,
            'type' => $type,
        ];

        try {
            $result = $this->tradeOffer->createTradeOffer($tradeData);
        } catch (\Exception $e) {
            \Logging\LoggerFactory::getChannel('trade')->error('Failed to create trade offer', ['error' => $e->getMessage()]);
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        if ($result['success']) {
            \Logging\LoggerFactory::getChannel('audit')->info('trade_offer_created', [
                'offering_team' => $tradeData['offeringTeam'],
                'listening_team' => $tradeData['listeningTeam'],
            ]);
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=offer_sent');
        }

        $checkedItems = [];
        for ($j = 0; $j < $fieldsCounter; $j++) {
            if (($check[$j] ?? null) === 'on') {
                $itemKey = ($type[$j] ?? '0') . ':' . ($index[$j] ?? '0');
                $checkedItems[$itemKey] = true;
            }
        }
        $_SESSION['tradeFormData'] = [
            'checkedItems' => $checkedItems,
            'userSendsCash' => $userSendsCash,
            'partnerSendsCash' => $partnerSendsCash,
        ];

        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [];
        $error = is_string($result['error'] ?? null)
            ? $result['error']
            : ($errors !== [] ? implode('; ', $errors) : 'Unknown error');
        \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=offertrade&partner=' . rawurlencode($tradeData['listeningTeam']) . '&error=' . rawurlencode($error));
    }

    /**
     * @see TradingControllerInterface::acceptTradeOffer()
     *
     * @param array<string, mixed> $post
     */
    public function acceptTradeOffer(array $post): void
    {
        if (!\Security\CsrfGuard::validateSubmittedToken('trade_accept')) {
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
        }

        $offerRaw = $post['offer'] ?? null;

        if (!is_numeric($offerRaw)) {
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade');
        }

        $offerId = (int) $offerRaw;

        $tradeRows = $this->offerRepository->getTradesByOfferId($offerId);

        if ($tradeRows === []) {
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=already_processed');
        }

        try {
            $result = $this->processor->processTrade($offerId);

            if ($result['success']) {
                \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=trade_accepted');
            } else {
                $errorMsg = is_string($result['error'] ?? null) ? ($result['error'] ?? '') : 'Unknown error';
                \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=accept_error&error=' . rawurlencode($errorMsg));
            }
        } catch (\Exception $e) {
            \Logging\LoggerFactory::getChannel('trade')->error('Failed to process trade', ['error' => $e->getMessage()]);
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=accept_error&error=' . rawurlencode($e->getMessage()));
        }
    }

    /**
     * @see TradingControllerInterface::rejectTradeOffer()
     *
     * @param array<string, mixed> $post
     */
    public function rejectTradeOffer(array $post): void
    {
        if (!\Security\CsrfGuard::validateSubmittedToken('trade_reject')) {
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
        }

        $offerRaw = $post['offer'] ?? null;

        if (!is_numeric($offerRaw)) {
            \Logging\LoggerFactory::getChannel('trade')->warning('Missing offer ID in POST data');
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade');
        }

        $offerId = (int) $offerRaw;
        $teamRejecting = is_string($post['teamRejecting'] ?? null) ? $post['teamRejecting'] : '';
        $teamReceiving = is_string($post['teamReceiving'] ?? null) ? $post['teamReceiving'] : '';

        $tradeRows = $this->offerRepository->getTradesByOfferId($offerId);

        if ($tradeRows === []) {
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=already_processed');
        }

        $this->offerRepository->deleteTradeOffer($offerId);

        \Logging\LoggerFactory::getChannel('audit')->info('trade_offer_rejected', [
            'offer_id' => $offerId,
        ]);

        try {
            // Primary in-app notification for the receiving (offering) team, whose
            // offer was rejected. Written BEFORE the Discord side-effect so a
            // Discord failure cannot skip it. Failure here is silently swallowed —
            // the rejection itself has already succeeded.
            $teamReceivingId = $this->teamIdentityRepo->getTidFromTeamname($teamReceiving) ?? 0;
            if ($teamReceivingId > 0) {
                $notificationService = new \Notifications\NotificationService(
                    new \Notifications\NotificationRepository($this->db)
                );
                $notificationService->notify(
                    $teamReceivingId,
                    \Notifications\NotificationType::TRADE_REJECTED,
                    "{$teamRejecting} rejected your trade offer.",
                    'modules.php?name=Trading&op=reviewtrade'
                );
            }

            $discord = new \Discord\Discord($this->teamIdentityRepo);
            $rejectingUserDiscordID = $discord->getDiscordIDFromTeamname($teamRejecting);
            $receivingUserDiscordID = $discord->getDiscordIDFromTeamname($teamReceiving);
            $declineMessage = TradingService::buildDeclineMessage($rejectingUserDiscordID, $teamRejecting);
            \Discord\Discord::sendDM($receivingUserDiscordID, $declineMessage);
        } catch (\Exception $e) {
            // Silently fail if Discord notification fails
            // The trade rejection itself has already succeeded
        }

        \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=trade_rejected');
    }

    private function renderTradeReview(string $username): void
    {
        $pageData = $this->service->getTradeReviewPageData($username);
        $pageData['result'] = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
        $pageData['error'] = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
        \PageLayout\PageLayout::header();
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->renderTradeReview($pageData));
        \PageLayout\PageLayout::footer();
    }

    private function renderTradeOffer(string $username, ?string $partner): void
    {
        $pageData = $this->service->getTradeOfferPageData($username, $partner ?? '');
        $pageData['result'] = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
        $pageData['error'] = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
        $pageData['previousFormData'] = $_SESSION['tradeFormData'] ?? null;
        unset($_SESSION['tradeFormData']);
        \PageLayout\PageLayout::header();
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->renderTradeOfferForm($pageData));
        \PageLayout\PageLayout::footer();
    }
}
