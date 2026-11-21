<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\Contracts;

interface SeriesRecordsServiceInterface
{
    /**
     * @return array<int, int>
     */
    public function getSeriesRecords(): array;
}
