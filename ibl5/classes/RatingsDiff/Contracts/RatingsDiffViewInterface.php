<?php

declare(strict_types=1);

namespace RatingsDiff\Contracts;

use RatingsDiff\RatingRow;

/**
 * RatingsDiffViewInterface — HTML rendering for the ratings diff page.
 */
interface RatingsDiffViewInterface
{
    /**
     * Renders the full ratings diff page content.
     *
     * When $baselineYear is null or $rows is empty, renders an empty-state block.
     *
     * @param list<RatingRow> $rows
     */
    public function render(?int $baselineYear, array $rows, string $filterStatus = ''): string;
}
