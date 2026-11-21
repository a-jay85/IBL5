<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\ContractsArchive;

interface LegacyStatsServiceInterface
{
    /**
     * @return array<int, int>
     */
    public function getTotals(): array;
}
