<?php

declare(strict_types=1);

namespace RatingsDiff\Contracts;

use RatingsDiff\RatingRow;

/**
 * RatingsDiffServiceInterface — business logic for the ratings diff page.
 */
interface RatingsDiffServiceInterface
{
    /**
     * Returns player rating rows sorted by largest single rating change.
     *
     * Real rows (player has baseline) come first, sorted by maxAbsDelta DESC,
     * then sumAbsDelta DESC, then lastname ASC. New players (no baseline) follow,
     * sorted lastname ASC.
     *
     * Returns an empty array when no baseline year is resolvable.
     *
     * @return list<RatingRow>
     */
    public function getDiffs(?int $overrideYear = null, ?int $filterTid = null, string $filterStatus = ''): array;

    /**
     * Returns the resolved baseline year (overrideYear if provided, else the
     * latest end-of-season snapshot year from the repository). Returns null
     * when no baseline is available and no override is given.
     */
    public function getBaselineYear(?int $overrideYear = null): ?int;
}
