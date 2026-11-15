<?php

declare(strict_types=1);

namespace TradeSuggestions\Feedback;

use Auth\Contracts\AuthServiceInterface;
use League\League;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Security\CsrfGuard;
use TradeSuggestions\Feedback\Contracts\FeedbackRepositoryInterface;
use TradeSuggestions\Feedback\Contracts\SuggestionFeedbackControllerInterface;

/**
 * @see SuggestionFeedbackControllerInterface
 */
class SuggestionFeedbackController implements SuggestionFeedbackControllerInterface
{
    public const HOME = '/ibl5/index.php';
    public const MAX_NOTE_LENGTH = 500;

    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly TeamIdentityRepositoryInterface $teamIdentityRepo,
        private readonly FeedbackRepositoryInterface $feedback,
        private readonly PanelSessionState $session,
    ) {
    }

    /**
     * @see SuggestionFeedbackControllerInterface::handle()
     *
     * @param array<mixed> $post
     */
    public function handle(string $requestMethod, array $post): string
    {
        if (strtoupper($requestMethod) !== 'POST') {
            return self::error('method');
        }

        if (!CsrfGuard::validateSubmittedToken('trade_suggestion_action')) {
            return self::error('csrf');
        }

        $tid = $this->resolveActingTid();
        if ($tid === null) {
            return self::error('auth');
        }

        $action = $post['action'] ?? null;
        if ($action === 'refresh') {
            $this->session->advance();

            return self::HOME . '#trade-suggestions';
        }
        if ($action === 'hide') {
            return $this->hide($tid, $post);
        }

        return self::error('action');
    }

    /**
     * @param array<mixed> $post
     */
    private function hide(int $tid, array $post): string
    {
        $dealKey = $post['deal_key'] ?? null;
        $entry = is_string($dealKey) && preg_match('/^[0-9a-f]{40}$/', $dealKey) === 1
            ? $this->session->entry($dealKey)
            : null;
        if (!is_string($dealKey) || $entry === null) {
            return self::error('stale');
        }

        $reasons = HideReason::fromCodes($post['reasons'] ?? null);
        if ($reasons === null) {
            return self::error('reason');
        }

        $rawText = $post['free_text'] ?? '';
        if (!is_string($rawText)) {
            return self::error('length');
        }
        $text = trim($rawText);
        if (mb_strlen($text) > self::MAX_NOTE_LENGTH) {
            return self::error('length');
        }

        $this->feedback->record(
            $tid,
            $entry['partnerTid'],
            new DealKey($dealKey, $entry['core']),
            $entry['dealJson'],
            HideReason::join($reasons),
            $text,
        );
        $this->session->forget($dealKey);

        return self::HOME . '?trade_suggestion_hidden=1#trade-suggestions';
    }

    private static function error(string $code): string
    {
        return self::HOME . '?trade_suggestion_error=' . $code;
    }

    /**
     * The acting GM's real-franchise team id, from the session only.
     * Same body as `TeamModeController::resolveActingTid()`.
     */
    private function resolveActingTid(): ?int
    {
        $username = $this->authService->getUsername() ?? '';
        if ($username === '') {
            return null;
        }

        $teamName = $this->teamIdentityRepo->getTeamnameFromUsername($username);
        if ($teamName === null || $teamName === League::FREE_AGENTS_TEAM_NAME) {
            return null;
        }

        $tid = $this->teamIdentityRepo->getTidFromTeamname($teamName);
        if ($tid === null || !League::isRealFranchise($tid)) {
            return null;
        }

        return $tid;
    }
}
