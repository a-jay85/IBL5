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
        private ?\GoogleSheets\GoogleOAuthFlowHandler $googleFlow = null,
        private ?\GoogleSheets\GoogleSheetExportService $googleExport = null,
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

        if ($op === 'google_start' && $_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
            if ($this->googleFlow === null) {
                $this->flash('error', 'Google Sheets sync is not configured on this server.');
            } elseif (\Security\CsrfGuard::validateSubmittedToken('google_start')) {
                (new \Api\Response\RedirectResponder())->redirect($this->googleFlow->start($userId));
                return;
            } else {
                $this->flash('error', 'Invalid or expired form submission. Please try again.');
            }
            (new \Api\Response\RedirectResponder())->redirect('modules.php?name=ApiKeys');
            return;
        }
        if ($op === 'google_callback' && $userId !== null) {
            $q = \Http\HttpRequest::fromGlobals();
            $result = $this->googleFlow === null
                ? ['type' => 'error', 'text' => 'Google Sheets sync is not configured on this server.']
                : $this->googleFlow->callback($userId, $q->get('state'), $q->get('code'), $q->get('error'));
            $this->flash($result['type'], $result['text']);
            \EventLog\EventLogger::setAction($result['type'] === 'success' ? 'google_sheet_connected' : 'google_sheet_connect_failed');
            (new \Api\Response\RedirectResponder())->redirect('modules.php?name=ApiKeys');
            return;
        }
        if (($op === 'google_refresh' || $op === 'google_disconnect') && $_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
            if ($this->googleExport === null) {
                $this->flash('error', 'Google Sheets sync is not configured on this server.');
            } elseif (!\Security\CsrfGuard::validateSubmittedToken($op)) {
                $this->flash('error', 'Invalid or expired form submission. Please try again.');
            } elseif ($op === 'google_refresh') {
                $status = $this->googleExport->refreshForUser($userId);
                $this->flash($status === 'ok' ? 'success' : 'error', match ($status) {
                    'ok' => 'Your Google Sheet has been refreshed.',
                    'broken' => 'Your Google connection needs attention. Use Reconnect Google below.',
                    'missing' => 'No Google Sheet is connected.',
                    default => 'Refresh failed. The status below shows why; try again in a minute.',
                });
                \EventLog\EventLogger::setAction('google_sheet_refresh_' . $status);
            } else {
                $this->googleExport->disconnect($userId);
                $this->flash('success', 'Google disconnected. The spreadsheet stays in your Drive.');
                \EventLog\EventLogger::setAction('google_sheet_disconnected');
            }
            (new \Api\Response\RedirectResponder())->redirect('modules.php?name=ApiKeys');
            return;
        }

        // Non-POST requests to state-changing ops redirect to main.
        if (in_array($op, ['generate', 'revoke', 'google_start', 'google_refresh', 'google_disconnect'], true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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

        $responder->html($this->view->renderExportGuide());

        \PageLayout\PageLayout::footer();
    }

    private function doMain(int $userId): void
    {
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->renderFlash($this->takeFlash()));
        $keyStatus = $this->service->getUserKeyStatus($userId);
        if ($keyStatus === null) {
            $responder->html($this->view->renderNoKeyState());
        } else {
            $responder->html($this->view->renderActiveKeyState($keyStatus));
        }
        $responder->html($this->view->renderGoogleSheetCard(
            $this->googleExport?->connectionSummaryFor($userId),
            $this->googleExport !== null
        ));
    }

    private function flash(string $type, string $text): void
    {
        $_SESSION['_apikeys_flash'] = ['type' => $type, 'text' => $text];
    }

    /**
     * @return array{type: string, text: string}|null
     */
    private function takeFlash(): ?array
    {
        $flash = $_SESSION['_apikeys_flash'] ?? null;
        unset($_SESSION['_apikeys_flash']);
        if (!is_array($flash) || !is_string($flash['type'] ?? null) || !is_string($flash['text'] ?? null)) {
            return null;
        }

        return ['type' => $flash['type'], 'text' => $flash['text']];
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
