<?php

declare(strict_types=1);

namespace OnThisDay;

use OnThisDay\Contracts\OnThisDayRepositoryInterface;
use OnThisDay\Contracts\OnThisDayServiceInterface;
use Season\Contracts\SeasonQueryRepositoryInterface;

/**
 * OnThisDayService - picks the "on this day" moments for the latest sim.
 *
 * The center date is the sim's end date (never wall-clock time). The search
 * widens through OnThisDayCalendar::WIDENING_RADII until a moment exists, and
 * the previous sim's selection is excluded (one level, soft) so consecutive
 * sims rarely repeat a moment.
 *
 * @see OnThisDayServiceInterface
 */
final class OnThisDayService implements OnThisDayServiceInterface
{
    public function __construct(
        private readonly OnThisDayRepositoryInterface $repository,
        private readonly SeasonQueryRepositoryInterface $seasonQuery,
        private readonly WowScorer $scorer,
        private readonly OnThisDayCalendar $calendar,
    ) {
    }

    /**
     * @see OnThisDayServiceInterface::getMomentsForSim()
     */
    public function getMomentsForSim(int $limit = 3): OnThisDaySelection
    {
        $latest = $this->seasonQuery->getLastSimDatesArray();
        if ($latest['sim'] === 0 || $latest['end_date'] === '') {
            return new OnThisDaySelection([], 0, '', 0, 0);
        }

        $current = $this->calendar->seasonYearOf($latest['end_date']);

        $previousKeys = [];
        if ($latest['sim'] > 1) {
            $previousEndDate = $this->repository->findSimEndDate($latest['sim'] - 1);
            if ($previousEndDate !== null) {
                $previous = $this->select(
                    $previousEndDate,
                    $this->calendar->seasonYearOf($previousEndDate),
                    $limit,
                    [],
                );
                foreach ($previous['moments'] as $moment) {
                    $previousKeys[] = $moment->key;
                }
            }
        }

        $result = $this->select($latest['end_date'], $current, $limit, $previousKeys);
        if ($result['moments'] === [] && $previousKeys !== []) {
            $result = $this->select($latest['end_date'], $current, $limit, []);
        }

        return new OnThisDaySelection(
            $result['moments'],
            $current,
            $latest['end_date'],
            $result['radius'],
            $latest['sim'],
        );
    }

    /**
     * Walk the widening radii and return the first non-empty ranked selection.
     *
     * @param list<string> $excludeKeys
     * @return array{moments: list<Moment>, radius: int}
     */
    private function select(string $center, int $seasonYear, int $limit, array $excludeKeys): array
    {
        foreach (OnThisDayCalendar::WIDENING_RADII as $radius) {
            $window = $this->calendar->monthDayWindow($center, $radius);

            $merged = $this->repository->findMoments($window, $seasonYear);

            $moments = $this->scorer->rank($this->collapseSameGame($merged), $limit, $excludeKeys);
            if ($moments !== []) {
                return ['moments' => $moments, 'radius' => $radius];
            }
        }

        return ['moments' => [], 'radius' => max(OnThisDayCalendar::WIDENING_RADII)];
    }

    /**
     * One (date, pid) game keeps only its higher-scoring moment, so a single game
     * never fills two slots. Moments without a player (pid 0) are untouched.
     *
     * @param list<Moment> $moments
     * @return list<Moment>
     */
    private function collapseSameGame(array $moments): array
    {
        /** @var array<string, Moment> $best */
        $best = [];
        $result = [];
        foreach ($moments as $moment) {
            if ($moment->pid === 0) {
                $result[] = $moment;
                continue;
            }

            $gameKey = $moment->date . ':' . $moment->pid;
            if (!isset($best[$gameKey])) {
                $best[$gameKey] = $moment;
                continue;
            }
            if ($this->scorer->score($moment) > $this->scorer->score($best[$gameKey])) {
                $best[$gameKey] = $moment;
            }
        }

        return array_merge($result, array_values($best));
    }
}
