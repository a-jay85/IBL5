<?php

declare(strict_types=1);

namespace OnThisDay;

use OnThisDay\Contracts\OnThisDayRepositoryInterface;
use OnThisDay\Contracts\OnThisDayServiceInterface;
use OnThisDay\Contracts\ShownMomentHistoryInterface;
use Season\Contracts\SeasonQueryRepositoryInterface;

/**
 * OnThisDayService - picks the "on this day" moments for the latest sim.
 *
 * The search covers every month/day of the latest sim, start date through end
 * date (never wall-clock time), and widens from both edges through
 * OnThisDayCalendar::WIDENING_RADII until a moment exists. Any moment key shown
 * in an earlier sim (ShownMomentHistoryInterface) is left out, so a moment never
 * repeats across seasons. When that leaves nothing, the candidates shown longest
 * ago are picked. The selection's centerDate stays the sim's end date: it is the
 * card's date label.
 *
 * @see OnThisDayServiceInterface
 */
final class OnThisDayService implements OnThisDayServiceInterface
{
    public function __construct(
        private readonly OnThisDayRepositoryInterface $repository,
        private readonly ShownMomentHistoryInterface $history,
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

        $startDate = $latest['start_date'] !== '' ? $latest['start_date'] : $latest['end_date'];
        $shown = $this->history->findShownBefore($latest['sim']);

        $result = $this->select($startDate, $latest['end_date'], $current, $limit, array_keys($shown));
        if ($result['moments'] === [] && $shown !== []) {
            $result = $this->selectOldestShown($startDate, $latest['end_date'], $current, $limit, $shown);
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
    private function select(string $startDate, string $endDate, int $seasonYear, int $limit, array $excludeKeys): array
    {
        foreach (OnThisDayCalendar::WIDENING_RADII as $radius) {
            $window = $this->calendar->spanWindow($startDate, $endDate, $radius);

            $merged = $this->repository->findMoments($window, $seasonYear);

            $moments = $this->scorer->rank($this->collapseSameGame($merged), $limit, $excludeKeys);
            if ($moments !== []) {
                return ['moments' => $moments, 'radius' => $radius];
            }
        }

        return ['moments' => [], 'radius' => max(OnThisDayCalendar::WIDENING_RADII)];
    }

    /**
     * Every candidate in the widest window was shown before. Walk the distinct
     * last-shown sims oldest first; at each cutoff, leave out keys shown after it
     * and rank the rest. The first non-empty ranking wins. Zero-score moments are
     * dropped by the scorer, so a cutoff whose moments all score 0 falls through
     * to the next. May return fewer than $limit moments.
     *
     * @param array<string, int> $shown moment_key => last shown sim, all before the current sim
     * @return array{moments: list<Moment>, radius: int}
     */
    private function selectOldestShown(string $startDate, string $endDate, int $seasonYear, int $limit, array $shown): array
    {
        $radius = max(OnThisDayCalendar::WIDENING_RADII);
        $candidates = $this->collapseSameGame(
            $this->repository->findMoments($this->calendar->spanWindow($startDate, $endDate, $radius), $seasonYear)
        );

        $cutoffs = [];
        foreach ($candidates as $moment) {
            if (isset($shown[$moment->key])) {
                $cutoffs[$shown[$moment->key]] = true;
            }
        }
        $cutoffs = array_keys($cutoffs);
        sort($cutoffs);

        foreach ($cutoffs as $cutoff) {
            $newer = array_keys(array_filter($shown, static fn (int $sim): bool => $sim > $cutoff));
            $moments = $this->scorer->rank($candidates, $limit, $newer);
            if ($moments !== []) {
                return ['moments' => $moments, 'radius' => $radius];
            }
        }

        return ['moments' => [], 'radius' => $radius];
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
