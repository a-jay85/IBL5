<?php

declare(strict_types=1);

namespace Api\Contracts;

/**
 * @template TRow of array<string, mixed>
 */
interface TransformerInterface
{
    /**
     * Transform one repository row into an API-shaped array.
     *
     * @param TRow $row
     * @return array<array-key, mixed>
     */
    public function transform(array $row): array;
}
