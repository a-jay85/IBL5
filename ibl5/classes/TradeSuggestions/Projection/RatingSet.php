<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * One player's 21 ratings, keyed by `Rating::ALL`.
 *
 * It has no aggregate method on purpose: ratings are compared one by one (F3).
 */
final readonly class RatingSet
{
    /**
     * @param array<string, float> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $values = [];
        foreach (Rating::ALL as $key) {
            $raw = $row[$key] ?? 0;
            $values[$key] = is_numeric($raw) ? (float) $raw : 0.0;
        }

        return new self($values);
    }

    public function get(string $key): float
    {
        if (!array_key_exists($key, $this->values)) {
            throw new \InvalidArgumentException("Unknown rating key: {$key}");
        }

        return $this->values[$key];
    }

    public function with(string $key, float $value): self
    {
        if (!array_key_exists($key, $this->values)) {
            throw new \InvalidArgumentException("Unknown rating key: {$key}");
        }
        $values = $this->values;
        $values[$key] = $value;

        return new self($values);
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
