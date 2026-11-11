<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

/**
 * F4 clamp bounds. The floor is 0. The ceiling per key is the observed league
 * maximum, so no magic ceiling is hardcoded.
 */
final readonly class RatingScale
{
    public const float MIN = 0.0;

    /**
     * @param array<string, float> $maxima
     */
    private function __construct(private array $maxima)
    {
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function fromRows(array $rows): self
    {
        $maxima = [];
        foreach (Rating::ALL as $key) {
            $maxima[$key] = 0.0;
        }
        foreach ($rows as $row) {
            foreach (Rating::ALL as $key) {
                $raw = $row[$key] ?? 0;
                $value = is_numeric($raw) ? (float) $raw : 0.0;
                if ($value > ($maxima[$key] ?? 0.0)) {
                    $maxima[$key] = $value;
                }
            }
        }

        return new self($maxima);
    }

    /**
     * @param array<string, float> $maxima
     */
    public static function fromMaxima(array $maxima): self
    {
        $values = [];
        foreach (Rating::ALL as $key) {
            $values[$key] = max(0.0, $maxima[$key] ?? 0.0);
        }

        return new self($values);
    }

    public function max(string $key): float
    {
        if (!array_key_exists($key, $this->maxima)) {
            throw new \InvalidArgumentException("Unknown rating key: {$key}");
        }

        return $this->maxima[$key];
    }

    public function clamp(string $key, float $value): float
    {
        return max(self::MIN, min($this->max($key), $value));
    }
}
