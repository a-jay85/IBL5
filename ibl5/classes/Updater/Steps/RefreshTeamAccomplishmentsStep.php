<?php

declare(strict_types=1);

namespace Updater\Steps;

use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Team\TeamAccomplishmentsCache;
use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Rebuild every real team's accomplishments cache row (playoff, HEAT and conference champions
 * plus ibl_team_awards) after the sim has written box scores, playoff series and season awards.
 *
 * Writes only DatabaseCache rows, so no transaction. When the run is over budget, or a rebuild
 * throws, it deletes every team's row so the next team-page view rebuilds from fresh data.
 *
 * IBL-only. The Olympics team page bypasses the cache.
 */
final class RefreshTeamAccomplishmentsStep implements PipelineStepInterface
{
    public const DEFAULT_BUDGET_SECONDS = 240.0;

    /**
     * @param \Closure(): TeamAccomplishmentsCache $cacheFactory
     * @param \Closure(): list<string> $teamNamesProvider
     */
    public function __construct(
        private readonly \Closure $cacheFactory,
        private readonly \Closure $teamNamesProvider,
        private readonly \Clock\ClockInterface $clock,
        private readonly float $startedAt,
        private readonly float $budgetSeconds = self::DEFAULT_BUDGET_SECONDS,
    ) {
    }

    /**
     * The identity repository is injected because ibl.directRepoInstantiation bans building it
     * inside classes/; scripts/updateAllTheThings.php constructs it.
     */
    public static function fromDb(
        \mysqli $db,
        \Clock\ClockInterface $clock,
        float $startedAt,
        TeamIdentityRepositoryInterface $teamIdentity,
    ): self {
        return new self(
            static fn (): TeamAccomplishmentsCache => new TeamAccomplishmentsCache(
                new \Team\TeamRepository($db),
                new \Cache\DatabaseCache($db),
            ),
            static fn (): array => self::realTeamNames($teamIdentity),
            $clock,
            $startedAt,
        );
    }

    /**
     * Real franchise team names in teamid order: the keys the team page reads the cache with.
     *
     * @return list<string>
     */
    public static function realTeamNames(TeamIdentityRepositoryInterface $teamIdentity): array
    {
        return array_map(
            static fn (array $team): string => $team['team_name'],
            $teamIdentity->getAllRealTeams('teamid ASC'),
        );
    }

    public function getLabel(): string
    {
        return 'team accomplishments cache refreshed';
    }

    public function execute(): StepResult
    {
        try {
            $cache = ($this->cacheFactory)();
            $teamNames = ($this->teamNamesProvider)();
        } catch (\Throwable $e) {
            return StepResult::failure($this->getLabel(), $e->getMessage());
        }

        $elapsed = $this->clock->microtime() - $this->startedAt;
        if ($elapsed > $this->budgetSeconds) {
            $cleared = $this->invalidateAll($cache, $teamNames);
            return StepResult::skipped(
                $this->getLabel(),
                sprintf(
                    'updater already ran %.1f s, over the %.0f s budget; cleared %d team rows so the next view rebuilds',
                    $elapsed,
                    $this->budgetSeconds,
                    $cleared,
                ),
            );
        }

        $before = $this->clock->microtime();
        try {
            foreach ($teamNames as $name) {
                $cache->rebuild($name);
            }
        } catch (\Throwable $e) {
            $clearedAfterFailure = $this->invalidateAll($cache, $teamNames);
            return StepResult::failure(
                $this->getLabel(),
                sprintf('%s; cleared %d team rows', $e->getMessage(), $clearedAfterFailure),
            );
        }

        return StepResult::success(
            $this->getLabel(),
            sprintf('%d teams in %.1f s', count($teamNames), $this->clock->microtime() - $before),
        );
    }

    /**
     * @param list<string> $teamNames
     */
    private function invalidateAll(TeamAccomplishmentsCache $cache, array $teamNames): int
    {
        $cleared = 0;
        foreach ($teamNames as $name) {
            try {
                $cache->invalidate($name);
                $cleared++;
            } catch (\Throwable) {
                // A dead connection fails every delete; TTL_SECONDS still bounds the staleness.
                continue;
            }
        }

        return $cleared;
    }
}
