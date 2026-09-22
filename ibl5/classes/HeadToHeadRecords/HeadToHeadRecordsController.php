<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;

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
    private \mysqli $db;

    public function __construct(
        HeadToHeadRecordsRepositoryInterface $repo,
        HeadToHeadRecordsView $view,
        \Season\Season $season,
        object $user,
        \mysqli $db
    ) {
        $this->repo   = $repo;
        $this->view   = $view;
        $this->season = $season;
        $this->user   = $user;
        $this->db     = $db;
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
        $ownerName = $this->lookupOwnerName($teamid);
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
     * Look up the owner_name for a given teamid.
     * Extracted for testability via anonymous subclass override.
     */
    protected function lookupOwnerName(int $teamid): ?string
    {
        $stmt = $this->db->prepare('SELECT owner_name FROM `ibl_team_info` WHERE teamid = ?');
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('i', $teamid);
        $stmt->execute();
        $stmt->bind_result($ownerName);
        $fetched = $stmt->fetch();
        $stmt->close();

        return ($fetched === true && is_string($ownerName)) ? $ownerName : null;
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
