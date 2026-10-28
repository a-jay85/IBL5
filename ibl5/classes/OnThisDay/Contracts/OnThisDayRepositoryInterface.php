<?php

declare(strict_types=1);

namespace OnThisDay\Contracts;

use OnThisDay\Moment;

/**
 * Contract for "on this day" reads over the precomputed moments table.
 *
 * Moments are built nightly by OnThisDayMomentBuilder; this read path never
 * touches box-score history.
 *
 * @package OnThisDay\Contracts
 */
interface OnThisDayRepositoryInterface
{
    /**
     * Stored moments on any of the given 'MM-DD' days, from seasons before $beforeSeasonYear.
     * Returns [] when the table is empty or missing; never reads box-score history.
     *
     * @param list<string> $monthDays 1-15 'MM-DD' strings from OnThisDayCalendar::monthDayWindow()
     * @return list<Moment>
     */
    public function findMoments(array $monthDays, int $beforeSeasonYear): array;

    /**
     * End date (Y-m-d) of the given sim, or null when the sim has no row or no end date.
     */
    public function findSimEndDate(int $sim): ?string;
}
