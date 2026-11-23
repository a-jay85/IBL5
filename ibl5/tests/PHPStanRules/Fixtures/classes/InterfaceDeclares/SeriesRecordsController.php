<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares;

use Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\Contracts\SeriesRecordsControllerInterface;

final class SeriesRecordsController implements SeriesRecordsControllerInterface
{
    public function main(): void
    {
    }

    public function handle(): void
    {
        $this->main();
    }
}
