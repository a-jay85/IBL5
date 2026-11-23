<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares;

use Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\ContractsArchive\LegacyStatsServiceInterface;

final class LegacyStatsService implements LegacyStatsServiceInterface
{
    /**
     * @return array<int, int>
     */
    public function getTotals(): array
    {
        return [];
    }

    public function getExtra(): int
    {
        return 0;
    }
}
