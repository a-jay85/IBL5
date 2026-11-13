<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\SlotMapper;

/**
 * One side's ranks and money before and after a deal at one offset. It holds
 * ranks and money only, for the card's before/after block and the reasons.
 */
final readonly class ProfileDelta
{
    /**
     * @param array<string, array{before: int, after: int}> $dimensionRanks
     * @param array<string, array{before: ?int, after: ?int}> $starterRanks
     * @param array{before: int, after: int} $payrollRank
     * @param array{before: int, after: int} $payroll
     */
    public function __construct(
        public int $tid,
        public int $offset,
        public array $dimensionRanks,
        public array $starterRanks,
        public array $payrollRank,
        public array $payroll,
    ) {
    }

    public static function fromStates(TeamState $before, TeamState $after): self
    {
        $dimensions = [];
        foreach (ProfileDimension::KEYS as $key) {
            if (isset($before->profile->ranks[$key], $after->profile->ranks[$key])) {
                $dimensions[$key] = ['before' => $before->profile->ranks[$key], 'after' => $after->profile->ranks[$key]];
            }
        }
        $starters = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $starters[$slot] = ['before' => $before->starterRank[$slot] ?? null, 'after' => $after->starterRank[$slot] ?? null];
        }

        return new self(
            $after->tid,
            $after->offset,
            $dimensions,
            $starters,
            ['before' => $before->payrollRank, 'after' => $after->payrollRank],
            ['before' => $before->payroll, 'after' => $after->payroll],
        );
    }

    /**
     * Dimension keys whose rank moved by at least `$minPlaces`, largest move
     * first. Equal moves keep `ProfileDimension::KEYS` order.
     *
     * @return list<string>
     */
    public function movedDimensions(int $minPlaces = 1): array
    {
        $moves = [];
        foreach ($this->dimensionRanks as $key => $pair) {
            $move = abs($pair['after'] - $pair['before']);
            if ($move >= $minPlaces) {
                $moves[$key] = $move;
            }
        }
        $keys = array_keys($moves);
        $order = array_flip($keys);
        usort($keys, static fn (string $a, string $b): int => [$moves[$b], $order[$a]] <=> [$moves[$a], $order[$b]]);

        return $keys;
    }
}
