<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares;

final class SeriesRecordsView
{
    public function __construct(private readonly SeriesRecordsService $service)
    {
    }

    public function render(): string
    {
        $record = $this->service->getRecordFromMatrix([], 1, 2);
        $this->service->getSeriesRecords();
        SeriesRecordsService::fromConfig();

        return $record . $this->service->__toString();
    }
}
