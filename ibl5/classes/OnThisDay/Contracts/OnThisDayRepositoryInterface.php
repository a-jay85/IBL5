<?php

declare(strict_types=1);

namespace OnThisDay\Contracts;

use OnThisDay\Moment;

/**
 * Contract for "on this day" moment queries. One method per moment source.
 *
 * Every method takes the month-day window (`MM-DD` strings) and the current
 * season ending year; only moments from seasons strictly before it are returned.
 *
 * @package OnThisDay\Contracts
 */
interface OnThisDayRepositoryInterface
{
    /**
     * @param list<string> $monthDays
     * @return list<Moment>
     */
    public function findFiftyPointGames(array $monthDays, int $beforeSeasonYear): array;

    /**
     * @param list<string> $monthDays
     * @return list<Moment>
     */
    public function findTripleDoubles(array $monthDays, int $beforeSeasonYear): array;

    /**
     * @param list<string> $monthDays
     * @return list<Moment>
     */
    public function findRecordGames(array $monthDays, int $beforeSeasonYear): array;

    /**
     * @param list<string> $monthDays
     * @return list<Moment>
     */
    public function findSeriesClinchers(array $monthDays, int $beforeSeasonYear): array;

    /**
     * @param list<string> $monthDays
     * @return list<Moment>
     */
    public function findTrades(array $monthDays, int $beforeSeasonYear): array;

    /**
     * End date (Y-m-d) of the given sim, or null when the sim has no row or no end date.
     */
    public function findSimEndDate(int $sim): ?string;
}
