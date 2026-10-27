<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * HeadToHeadRecordsController - Orchestrates filter resolution and output for the H2H matrix.
 *
 * @phpstan-import-type AxisEntry from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 */
class HeadToHeadRecordsController
{
    /** @var list<string> */
    private const VALID_SCOPES = ['current', 'all'];

    /** @var list<string> */
    private const VALID_DIMENSIONS = ['franchises', 'teams', 'gms'];

    /** @var list<string> */
    private const VALID_PHASES = ['heat', 'regular', 'playoffs', 'all'];

    /** @var array<string, string> */
    private const SEASON_PHASE_TO_FILTER = [
        'HEAT'           => 'heat',
        'Regular Season' => 'regular',
        'Playoffs'       => 'playoffs',
    ];

    private HeadToHeadRecordsRepositoryInterface $repo;
    private HeadToHeadRecordsView $view;
    private \Season\Season $season;
    private object $user;
    private TeamIdentityRepositoryInterface $teamRepo;

    public function __construct(
        HeadToHeadRecordsRepositoryInterface $repo,
        HeadToHeadRecordsView $view,
        \Season\Season $season,
        object $user,
        TeamIdentityRepositoryInterface $teamRepo
    ) {
        $this->repo   = $repo;
        $this->view   = $view;
        $this->season = $season;
        $this->user   = $user;
        $this->teamRepo = $teamRepo;
    }

    /**
     * Return a copy that highlights the given team's row, or no row for null.
     */
    public function withHighlightedTeam(?int $teamId): static
    {
        $copy = clone $this;
        $user = new \stdClass();
        if ($teamId !== null) {
            $user->teamid = $teamId;
        }
        $copy->user = $user;

        return $copy;
    }

    /**
     * Resolve filter values from a raw POST array.
     *
     * Unknown or non-string values fall back to defaults.
     *
     * @param array<string, mixed> $post
     * @return array{dimension: string, phase: string, scope: string}
     */
    public function resolveFilters(array $post): array
    {
        $v = $post['dimension'] ?? null;
        $dimension = (is_string($v) && in_array($v, self::VALID_DIMENSIONS, true)) ? $v : 'franchises';

        $v = $post['phase'] ?? null;
        $phase = (is_string($v) && in_array($v, self::VALID_PHASES, true))
            ? $v
            : (self::SEASON_PHASE_TO_FILTER[$this->season->phase] ?? 'all');

        $v = $post['scope'] ?? null;
        $scope = (is_string($v) && in_array($v, self::VALID_SCOPES, true))
            ? $v
            : ($this->repo->currentSeasonHasGames() ? 'current' : 'all');

        return ['dimension' => $dimension, 'phase' => $phase, 'scope' => $scope];
    }

    /**
     * Main entry point: resolve filters, build matrix, render output.
     */
    public function main(): void
    {
        /** @var array<string, mixed> $post */
        $post = $_POST;
        $f = $this->resolveFilters($post);

        $payload = match ($f['dimension']) {
            'franchises' => $this->repo->buildFranchisesMatrix($f['phase'], $f['scope']),
            'teams'      => $this->repo->buildTeamsMatrix($f['phase'], $f['scope']),
            'gms'        => $this->repo->buildGmsMatrix($f['phase'], $f['scope']),
            default      => $this->repo->buildFranchisesMatrix($f['phase'], $f['scope']),
        };

        // @phpstan-ignore ibl.echoInNonView
        echo $this->view->renderFilterForm($f['dimension'], $f['phase'], $f['scope'])
            . $this->view->renderMatrix($payload, $this->resolveUserMatchKeys($f['dimension'], $payload))
            . $this->view->renderTapTooltipScript();
    }

    /**
     * Return the axis keys that belong to the logged-in user.
     *
     * Anonymous users (no teamid or teamid <= 0) always return [].
     *
     * @param MatrixPayload $payload
     * @return list<string>
     */
    public function resolveUserMatchKeys(string $dimension, array $payload): array
    {
        $teamid = $this->resolveTeamId();
        if ($teamid <= 0) {
            return [];
        }

        $axis = $payload['axis'];

        if ($dimension === 'franchises') {
            return [(string) $teamid];
        }

        if ($dimension === 'teams') {
            $keys = [];
            foreach ($axis as $entry) {
                if ($entry['franchise_id'] === $teamid) {
                    $keys[] = $entry['key'];
                }
            }
            return $keys;
        }

        // gms dimension: look up the owner_name for this teamid
        $ownerName = $this->teamRepo->getOwnerName($teamid);
        if ($ownerName === null) {
            return [];
        }

        foreach ($axis as $entry) {
            if ($entry['key'] === $ownerName) {
                return [$ownerName];
            }
        }

        return [];
    }

    /**
     * Extract the teamid from the user object, or return 0 for anonymous.
     */
    private function resolveTeamId(): int
    {
        if (!isset($this->user->teamid)) {
            return 0;
        }
        $tid = $this->user->teamid;
        if (is_string($tid) && ctype_digit($tid)) {
            $tid = (int) $tid;
        }
        return (is_int($tid) && $tid > 0) ? $tid : 0;
    }
}
