<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

/**
 * The ids of every veto one deal fired, sorted and without repeats.
 */
final readonly class VetoReport
{
    /** @var list<string> */
    private array $firedIds;

    /**
     * @param list<string> $firedIds
     */
    public function __construct(array $firedIds)
    {
        $ids = array_unique($firedIds);
        sort($ids); // sort() renumbers the keys, so the result is a list
        $this->firedIds = $ids;
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @return list<string>
     */
    public function firedIds(): array
    {
        return $this->firedIds;
    }

    public function fired(): bool
    {
        return $this->firedIds !== [];
    }

    public function has(string $id): bool
    {
        return in_array($id, $this->firedIds, true);
    }
}
