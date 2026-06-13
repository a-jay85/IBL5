<?php

declare(strict_types=1);

namespace Watchlist;

use Watchlist\Contracts\WatchlistControllerInterface;
use Watchlist\Contracts\WatchlistServiceInterface;
use Watchlist\Contracts\WatchlistViewInterface;

/**
 * @see WatchlistControllerInterface
 */
class WatchlistController implements WatchlistControllerInterface
{
    private WatchlistServiceInterface $service;
    private WatchlistViewInterface $view;
    private \Utilities\NukeCompat $nukeCompat;

    public function __construct(
        WatchlistServiceInterface $service,
        WatchlistViewInterface $view,
        \Utilities\NukeCompat $nukeCompat
    ) {
        $this->service = $service;
        $this->view = $view;
        $this->nukeCompat = $nukeCompat;
    }

    /**
     * @see WatchlistControllerInterface::handleRequest()
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

        $isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
        if ($isPost && in_array($op, ['toggle', 'savenote', 'remove'], true)) {
            $this->handlePost($username, $op);
            return;
        }

        $this->renderPage($username);
    }

    /**
     * Validate CSRF, dispatch the write, and PRG-redirect.
     */
    private function handlePost(string $username, string $op): void
    {
        if (!\Security\CsrfGuard::validateSubmittedToken('watchlist')) {
            \Utilities\HtmxHelper::redirect(
                'modules.php?name=Watchlist&error=' . rawurlencode('Invalid or expired form submission. Please try again.')
            );
        }

        // pid is the only player input; the owning teamid is never read from the
        // request — the service resolves it server-side from $username.
        $pid = isset($_POST['pid']) && is_string($_POST['pid']) ? (int) $_POST['pid'] : 0;

        $result = match ($op) {
            'savenote' => $this->service->saveNote(
                $username,
                $pid,
                isset($_POST['note']) && is_string($_POST['note']) ? $_POST['note'] : ''
            ),
            'remove' => $this->service->removeWatch($username, $pid),
            default => $this->service->toggleWatch($username, $pid),
        };

        if (($result['success'] ?? false) === true) {
            $resultCode = $result['result'] ?? '';
            \Utilities\HtmxHelper::redirect(
                'modules.php?name=Watchlist&result=' . rawurlencode(is_string($resultCode) ? $resultCode : '')
            );
        }

        $errorCode = $result['error'] ?? '';
        \Utilities\HtmxHelper::redirect(
            'modules.php?name=Watchlist&error=' . rawurlencode(is_string($errorCode) ? $errorCode : '')
        );
    }

    /**
     * Render the My Watchlist page (GET).
     */
    private function renderPage(string $username): void
    {
        $hasTeam = $this->service->resolveOwnerTeamid($username) !== null;
        $rows = $hasTeam ? $this->service->getWatchlistView($username) : [];

        $result = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
        $error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
        $rawToken = \Security\CsrfGuard::generateRawToken('watchlist');

        \PageLayout\PageLayout::header();
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->renderWatchlistPage($rows, $result, $error, $rawToken, $hasTeam));
        \PageLayout\PageLayout::footer();
    }
}
