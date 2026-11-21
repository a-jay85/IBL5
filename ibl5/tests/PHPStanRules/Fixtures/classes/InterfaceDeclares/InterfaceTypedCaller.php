<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares;

use Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\Contracts\SeriesRecordsServiceInterface;

final class InterfaceTypedCaller
{
    public function __construct(
        private readonly SeriesRecordsServiceInterface $service,
        private readonly SeriesRecordsService $concrete,
    ) {
    }

    public function run(): int
    {
        return count($this->service->getSeriesRecords()) + count($this->concrete->getSeriesRecords());
    }
}
