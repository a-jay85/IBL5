<?php

declare(strict_types=1);

namespace ApiKeys;

use ApiKeys\Contracts\ApiKeysControllerInterface;
use ApiKeys\Contracts\ApiKeysServiceInterface;
use ApiKeys\Contracts\ApiKeysViewInterface;
use Auth\Contracts\AuthServiceInterface;

class ApiKeysController implements ApiKeysControllerInterface
{
    public function __construct(
        private ApiKeysServiceInterface $service,
        private ApiKeysViewInterface $view,
        private \Utilities\NukeCompat $nukeCompat,
        private AuthServiceInterface $authService,
    ) {}

    public function handle(string $op, mixed $user): void
    {
        if (!$this->nukeCompat->isUser($user)) {
            $this->nukeCompat->loginBox();
            return;
        }

        $userId = $this->authService->getUserId();

        // POST-redirect for revoke before PageLayout::header() sends output.
        if ($op === 'revoke' && $_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
            if (\Security\CsrfGuard::validateSubmittedToken('api_keys_revoke')) {
                $this->service->revokeKeyForUser($userId);
                \EventLog\EventLogger::setAction('api_key_revoked');
                (new \Api\Response\RedirectResponder())->redirect('modules.php?name=ApiKeys');
                return;
            }
        }

        // Non-POST requests to generate/revoke redirect to main.
        if (($op === 'generate' || $op === 'revoke') && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            (new \Api\Response\RedirectResponder())->redirect('modules.php?name=ApiKeys');
            return;
        }

        \PageLayout\PageLayout::header();

        $responder = new \Api\Response\HtmlResponder();

        if ($userId === null) {
            $responder->html('<div class="ibl-alert ibl-alert--error">Unable to determine user identity.</div>');
            \PageLayout\PageLayout::footer();
            return;
        }

        switch ($op) {
            case 'generate':
                $this->doGenerate($userId);
                break;
            case 'revoke':
                // CSRF validation failed (POST handled above).
                $responder->html('<div class="ibl-alert ibl-alert--error">Invalid or expired form submission. Please try again.</div>');
                break;
            default:
                $this->doMain($userId);
                break;
        }

        \PageLayout\PageLayout::footer();
    }

    private function doMain(int $userId): void
    {
        $responder = new \Api\Response\HtmlResponder();
        $keyStatus = $this->service->getUserKeyStatus($userId);
        if ($keyStatus === null) {
            $responder->html($this->view->renderNoKeyState());
        } else {
            $responder->html($this->view->renderActiveKeyState($keyStatus));
        }
    }

    private function doGenerate(int $userId): void
    {
        $responder = new \Api\Response\HtmlResponder();
        if (!\Security\CsrfGuard::validateSubmittedToken('api_keys_generate')) {
            $responder->html('<div class="ibl-alert ibl-alert--error">Invalid or expired form submission. Please try again.</div>');
            return;
        }

        $username = $this->authService->getUsername();
        if ($username === null) {
            $responder->html('<div class="ibl-alert ibl-alert--error">Unable to determine username.</div>');
            return;
        }

        try {
            $result = $this->service->generateKeyForUser($userId, $username);
            \EventLog\EventLogger::setAction('api_key_generated');
            $responder->html($this->view->renderNewKeyState($result['raw_key']));
        } catch (\RuntimeException $e) {
            $responder->html('<div class="ibl-alert ibl-alert--error">' . \Security\HtmlSanitizer::e($e->getMessage()) . '</div>');
        }
    }
}
