<?php

declare(strict_types=1);

namespace TrainingCampRatingsDiff\Contracts;

use TrainingCampRatingsDiff\RatingRow;

/**
 * TrainingCampRatingsDiffViewInterface — HTML rendering for the ratings diff page.
 */
interface TrainingCampRatingsDiffViewInterface
{
    /**
     * Renders the full ratings diff page content.
     *
     * When $baselineYear is null or $rows is empty, renders an empty-state block.
     * $baselinePhase picks the intro text's baseline label (playoffs,
     * end-of-season, or mid-season ratings).
     *
     * @param list<RatingRow> $rows
     */
    public function render(?int $baselineYear, ?string $baselinePhase, array $rows, string $filterStatus = ''): string;
}
