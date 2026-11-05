<?php

declare(strict_types=1);

namespace TradeSuggestions;

use Auth\Contracts\AuthServiceInterface;
use League\League;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Security\CsrfGuard;
use TradeSuggestions\Contracts\TeamModeControllerInterface;

/**
 * @see TeamModeControllerInterface
 */
class TeamModeController implements TeamModeControllerInterface
{
    public const HOME = '/ibl5/index.php';

    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly TeamIdentityRepositoryInterface $teamIdentityRepo,
        private readonly TeamModeService $teamModeService,
    ) {
    }

    /**
     * @see TeamModeControllerInterface::handleSetMode()
     *
     * @param array<mixed> $post
     */
    public function handleSetMode(string $requestMethod, array $post): string
    {
        if (strtoupper($requestMethod) !== 'POST') {
            return self::HOME . '?trade_mode_error=method';
        }

        if (!CsrfGuard::validateSubmittedToken('trade_mode')) {
            return self::HOME . '?trade_mode_error=csrf';
        }

        $tid = $this->resolveActingTid();
        if ($tid === null) {
            return self::HOME . '?trade_mode_error=auth';
        }

        $raw = $post['trade_mode'] ?? null;
        $setting = is_string($raw) ? TeamModeSetting::tryFrom($raw) : null;
        if ($setting === null) {
            return self::HOME . '?trade_mode_error=value';
        }

        $this->teamModeService->setOverride($tid, $setting);

        return self::HOME . '#trade-suggestions';
    }

    /**
     * The acting GM's real-franchise team id, from the session only.
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
