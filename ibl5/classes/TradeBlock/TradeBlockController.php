<?php

declare(strict_types=1);

namespace TradeBlock;

use TradeBlock\Contracts\TradeBlockControllerInterface;
use TradeBlock\Contracts\TradeBlockProcessorInterface;
use TradeBlock\Contracts\TradeBlockServiceInterface;
use TradeBlock\Contracts\TradeBlockValidatorInterface;
use TradeBlock\Contracts\TradeBlockViewInterface;

/**
 * @see TradeBlockControllerInterface
 *
 * @phpstan-import-type UserRow from \Repositories\Contracts\TeamIdentityRepositoryInterface
 */
class TradeBlockController implements TradeBlockControllerInterface
{
    private TradeBlockServiceInterface $service;
    private TradeBlockProcessorInterface $processor;
    private TradeBlockViewInterface $view;
    private TradeBlockValidatorInterface $validator;
    private \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepo;
    private \Utilities\NukeCompat $nukeCompat;

    public function __construct(
        TradeBlockServiceInterface $service,
        TradeBlockProcessorInterface $processor,
        TradeBlockViewInterface $view,
        TradeBlockValidatorInterface $validator,
        \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepo,
        \Utilities\NukeCompat $nukeCompat
    ) {
        $this->service = $service;
        $this->processor = $processor;
        $this->view = $view;
        $this->validator = $validator;
        $this->teamIdentityRepo = $teamIdentityRepo;
        $this->nukeCompat = $nukeCompat;
    }

    /**
     * @see TradeBlockControllerInterface::handleRequest()
     */
    public function handleRequest($user, string $op): void
    {
        if (!$this->nukeCompat->isUser($user)) {
            $this->nukeCompat->loginBox();
            return;
        }

        // Decoded return of cookieDecode($user) — a local, not the header-populated
        // $cookie superglobal, so it is safe to read before PageLayout::header().
        $decodedCookie = $this->nukeCompat->cookieDecode($user);
        $username = is_string($decodedCookie[1] ?? null) ? $decodedCookie[1] : '';

        // PRG: process the bulk edit submission, then redirect.
        if (isset($_POST['Action']) && $_POST['Action'] === 'save') {
            $this->handleSave($username);
            return;
        }

        if ($op === 'edit') {
            $this->displayEditForm($username);
            return;
        }

        $this->displayBrowse();
    }

    private function handleSave(string $username): void
    {
        if (!\Security\CsrfGuard::validateSubmittedToken('tradeblock')) {
            \Utilities\HtmxHelper::redirect('modules.php?name=TradeBlock&op=edit&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
        }

        $teamId = $this->resolveTeamId($username);
        if ($teamId === null) {
            $this->nukeCompat->loginBox();
            return;
        }

        try {
            /** @var array<string, mixed> $post */
            $post = $_POST;
            $sanitized = $this->validator->sanitizeEdit($post);
            $result = $this->processor->processEdit(
                $teamId,
                $sanitized['pids'],
                $sanitized['notes'],
                $sanitized['seekingNote']
            );
        } catch (\Throwable $e) {
            \Logging\LoggerFactory::getChannel('audit')->error('trade_block_submission_error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $result = ['success' => false, 'error' => 'An unexpected error occurred. Please try again.'];
        }

        if ($result['success'] === true) {
            $resultParam = $result['result'] ?? '';
            \Utilities\HtmxHelper::redirect('modules.php?name=TradeBlock&op=edit&result=' . rawurlencode($resultParam));
        } else {
            $errorParam = $result['error'] ?? '';
            \Utilities\HtmxHelper::redirect('modules.php?name=TradeBlock&op=edit&error=' . rawurlencode($errorParam));
        }
    }

    private function displayEditForm(string $username): void
    {
        $teamId = $this->resolveTeamId($username);
        if ($teamId === null) {
            $this->nukeCompat->loginBox();
            return;
        }

        $resultParam = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
        $errorParam = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;

        $formData = $this->service->getEditFormData($teamId);

        \PageLayout\PageLayout::header();
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->renderEditForm(
            $formData['team'],
            $formData['roster'],
            $formData['blockPids'],
            $formData['seekingNote'],
            $resultParam,
            $errorParam
        ));
        \PageLayout\PageLayout::footer();
    }

    private function displayBrowse(): void
    {
        $browseData = $this->service->getBrowseData();

        \PageLayout\PageLayout::header();
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->renderBrowse($browseData));
        \PageLayout\PageLayout::footer();
    }

    /**
     * Resolve the authoritative team id for the logged-in user from the session.
     * Never trusts POST input for ownership.
     */
    private function resolveTeamId(string $username): ?int
    {
        $userInfo = $this->teamIdentityRepo->getUserByUsername($username);
        if ($userInfo === null) {
            return null;
        }

        $teamName = $this->teamIdentityRepo->getTeamnameFromUsername($username);
        if ($teamName === null || $teamName === '') {
            return null;
        }

        return $this->teamIdentityRepo->getTidFromTeamname($teamName);
    }
}
