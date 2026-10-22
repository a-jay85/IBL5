<?php

declare(strict_types=1);

namespace Rivalries;

use League\League;
use Rivalries\Contracts\RivalriesRepositoryInterface;
use Rivalries\Contracts\RivalriesServiceInterface;
use Rivalries\Contracts\RivalriesViewInterface;

/**
 * RivalriesController - Validates GET input and routes to the right view.
 *
 * Every id and name is checked before any service or DB call. No limit or
 * loop bound is read from input, and raw input is never echoed.
 */
final class RivalriesController
{
    public const OPS = ['index', 'teams', 'gms', 'gm'];
    public const MAX_GM_NAME_LENGTH = 60;
    public const TOP_LIMIT = 10;

    public function __construct(
        private readonly RivalriesServiceInterface $service,
        private readonly RivalriesViewInterface $view,
        private readonly RivalriesRepositoryInterface $repo,
    ) {
    }

    /**
     * @param array<mixed> $get
     */
    public function main(array $get, ?string $userTeamKey, ?string $userGm): void
    {
        // @phpstan-ignore ibl.echoInNonView
        echo $this->handle($get, $userTeamKey, $userGm);
    }

    /**
     * @param array<mixed> $get
     */
    public function handle(array $get, ?string $userTeamKey, ?string $userGm): string
    {
        $op = is_string($get['op'] ?? null) && in_array($get['op'], self::OPS, true) ? $get['op'] : 'index';

        switch ($op) {
            case 'teams':
                $a = $this->parseTeamId($get['a'] ?? null);
                $b = $this->parseTeamId($get['b'] ?? null);
                if ($a === null || $b === null || $a === $b) {
                    return $this->view->renderEmpty('Pick two different franchises.');
                }
                return $this->view->renderPair($this->service->getFranchisePair($a, $b), 'teams');

            case 'gms':
                $a = $this->parseGmName($get['a'] ?? null);
                $b = $a === null ? null : $this->parseGmName($get['b'] ?? null);
                if ($a === null || $b === null || $a === $b) {
                    return $this->view->renderEmpty('Pick two different general managers.');
                }
                return $this->view->renderPair($this->service->getGmPair($a, $b), 'gms');

            case 'gm':
                $name = $this->parseGmName($get['gm'] ?? null);
                if ($name === null) {
                    return $this->view->renderEmpty('No general manager by that name.');
                }
                return $this->view->renderGm($name, $this->service->getTopGmRivals($name, self::TOP_LIMIT), true, $userGm);

            default:
                return $this->view->renderIndex($this->service->getLeagueTop(self::TOP_LIMIT), $userTeamKey, $userGm);
        }
    }

    private function parseTeamId(mixed $raw): ?int
    {
        if (!is_string($raw) || preg_match('/^[1-9][0-9]?$/', $raw) !== 1) {
            return null;
        }
        $id = (int) $raw;

        return $id <= League::MAX_REAL_TEAMID ? $id : null;
    }

    private function parseGmName(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $name = trim($raw);
        if ($name === '' || mb_strlen($name) > self::MAX_GM_NAME_LENGTH) {
            return null;
        }

        return $this->repo->findGmName($name);
    }
}
