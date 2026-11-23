<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares;

use Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares\Contracts\SeriesRecordsServiceInterface;

final class SeriesRecordsService implements SeriesRecordsServiceInterface
{
    /**
     * @return array<int, int>
     */
    public function getSeriesRecords(): array
    {
        return [];
    }

    /**
     * @param array<int, int> $matrix
     */
    public function getRecordFromMatrix(array $matrix, int $a, int $b): string
    {
        return $this->helper();
    }

    public static function fromConfig(): self
    {
        return new self();
    }

    public function __toString(): string
    {
        return '';
    }

    private function helper(): string
    {
        return '';
    }
}
