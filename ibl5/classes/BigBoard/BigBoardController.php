<?php

declare(strict_types=1);

namespace BigBoard;

use BigBoard\Contracts\BigBoardControllerInterface;
use BigBoard\Contracts\BigBoardServiceInterface;
use BigBoard\Contracts\BigBoardViewInterface;

/**
 * @see BigBoardControllerInterface
 */
class BigBoardController implements BigBoardControllerInterface
{
    /** POST ops that mutate the board (each CSRF-guarded + PRG-redirected). */
    private const MUTATING_OPS = ['add', 'setrank', 'setnote', 'remove'];

    private BigBoardServiceInterface $service;
    private BigBoardViewInterface $view;
    private \Utilities\NukeCompat $nukeCompat;
    private \mysqli $db;

    public function __construct(
        BigBoardServiceInterface $service,
        BigBoardViewInterface $view,
        \Utilities\NukeCompat $nukeCompat,
        \mysqli $db
    ) {
        $this->service = $service;
        $this->view = $view;
        $this->nukeCompat = $nukeCompat;
        $this->db = $db;
    }

    /**
     * @see BigBoardControllerInterface::handleRequest()
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
        if ($isPost && in_array($op, self::MUTATING_OPS, true)) {
            $this->handlePost($username, $op);
            return;
        }

        if ($op === 'mock') {
            $this->renderMock($username);
            return;
        }

        $this->renderBoard($username);
    }

    /**
     * Validate CSRF, dispatch the scoped write, and PRG-redirect.
     */
    private function handlePost(string $username, string $op): void
    {
        if (!\Security\CsrfGuard::validateSubmittedToken('bigboard')) {
            \Utilities\HtmxHelper::redirect(
                'modules.php?name=BigBoard&error=' . rawurlencode('Invalid or expired form submission. Please try again.')
            );
        }

        // teamid is resolved server-side inside the service from $username; it is
        // NEVER read from the request. Only the entity-scoped ids/text come in.
        $prospectId = isset($_POST['prospect_id']) && is_string($_POST['prospect_id']) ? (int) $_POST['prospect_id'] : 0;
        $entryId = isset($_POST['entry_id']) && is_string($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        $rank = isset($_POST['rank']) && is_string($_POST['rank']) ? (int) $_POST['rank'] : 0;
        $note = isset($_POST['note']) && is_string($_POST['note']) ? $_POST['note'] : '';

        $result = match ($op) {
            'add' => $this->service->addEntry($username, $prospectId, $rank, $note),
            'setrank' => $this->service->setRank($username, $entryId, $rank),
            'setnote' => $this->service->setNote($username, $entryId, $note),
            default => $this->service->removeEntry($username, $entryId),
        };

        if (($result['success'] ?? false) === true) {
            $resultCode = $result['result'] ?? '';
            \Utilities\HtmxHelper::redirect(
                'modules.php?name=BigBoard&result=' . rawurlencode(is_string($resultCode) ? $resultCode : '')
            );
        }

        $errorCode = $result['error'] ?? '';
        \Utilities\HtmxHelper::redirect(
            'modules.php?name=BigBoard&error=' . rawurlencode(is_string($errorCode) ? $errorCode : '')
        );
    }

    /**
     * Render the Big Board page (GET).
     */
    private function renderBoard(string $username): void
    {
        $hasTeam = $this->service->resolveOwnerTeamid($username) !== null;
        $rows = $hasTeam ? $this->service->getBoardView($username) : [];
        $addable = $hasTeam ? $this->service->getAddableProspects($username) : [];

        $result = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
        $error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
        $rawToken = \Security\CsrfGuard::generateRawToken('bigboard');

        \PageLayout\PageLayout::header();
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->renderBigBoardPage($rows, $addable, $result, $error, $rawToken, $hasTeam));
        \PageLayout\PageLayout::footer();
    }

    /**
     * Render the Mock Draft page (GET).
     */
    private function renderMock(string $username): void
    {
        $hasTeam = $this->service->resolveOwnerTeamid($username) !== null;
        $season = new \Season\Season($this->db);
        $picks = $hasTeam ? $this->service->getMockDraft($username, $season->endingYear) : [];

        \PageLayout\PageLayout::header();
        $responder = new \Api\Response\HtmlResponder();
        $responder->html($this->view->renderMockDraftPage($picks, $hasTeam));
        \PageLayout\PageLayout::footer();
    }
}
