<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares;

use Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\Contracts\SeriesRecordsFactoryInterface;

final class SeriesRecordsFactory implements SeriesRecordsFactoryInterface
{
    public function build(): void
    {
    }

    public function service(): SeriesRecordsService
    {
        return new SeriesRecordsService();
    }
}
